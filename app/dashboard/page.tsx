"use client";

import { useEffect, useState } from "react";
import { Card, CardContent } from "@/components/ui/layout/card";
import { Input } from "@/components/ui/form/input";
import { Button } from "@/components/ui/button";
import Link from "next/link";

export default function TeacherProgramDashboard() {
  const [programs, setPrograms] = useState<any[]>([]);
  const [filters, setFilters] = useState({ term: "", subject: "", teacher: "", title: "" });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);

  const fetchPrograms = async () => {
    setLoading(true);
    const params = new URLSearchParams();
    if (filters.term) params.append("filters[term_focus][$eq]", filters.term);
    if (filters.subject) params.append("filters[subject_area][name][$containsi]", filters.subject);
    if (filters.teacher) params.append("filters[teacher_name][$containsi]", filters.teacher);
    if (filters.title) params.append("filters[title][$containsi]", filters.title);
    params.append("pagination[page]", page.toString());
    params.append("pagination[pageSize]", "10");
    params.append("populate", "*");
    params.append("publicationState", "preview");

    const res = await fetch(`${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs?${params}`);
    const data = await res.json();
    setPrograms(data?.data || []);
    setLoading(false);
  };

  const handleClone = async (program: any) => {
    const timestamp = Date.now();
    const clonedTitle = `${program.attributes.title || "Untitled"} (Copy ${timestamp})`;

    const res = await fetch(`${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        data: {
          ...program.attributes,
          title: clonedTitle,
        },
      }),
    });

    if (res.ok) await fetchPrograms();
    else alert("❌ Cloning failed.");
  };

const handleCreateProgram = async () => {
  const teacher = "John Stewart";
  const term = "Term 1 – Identity";
  let year = new Date().getFullYear();

  // Step 1: Construct base title
  let title = `${teacher} – ${term} (${year})`;

  // Step 2: Check if title already exists
  let exists = true;
  while (exists) {
    const checkRes = await fetch(`${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs?filters[title][$eq]=${encodeURIComponent(title)}`);
    const checkJson = await checkRes.json();
    if (checkJson?.data?.length > 0) {
      year++; // Try next year
      title = `${teacher} – ${term} (${year})`;
    } else {
      exists = false;
    }
  }

  // Step 3: Generate a fallback slug
  const fallbackSlug = title.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");

  // Step 4: Create the program
  const res = await fetch(`${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      data: {
        title,
        slug: fallbackSlug, // fallback, optional
        stage: "S3",
        term_focus: term,
        teacher_name: teacher,
        program_year: year, // optional: create this number field in Strapi
      },
    }),
  });

  const json = await res.json();
  console.log("Program creation response:", json);

  if (!res.ok) {
    console.dir(json, { depth: null });
    alert("❌ Failed to create program.");
    return;
  }

  const newSlug = json?.data?.attributes?.slug ?? fallbackSlug;

  if (newSlug) {
    window.location.href = `/editor/${newSlug}`;
  } else {
    alert("Program created but missing slug.");
  }
};

  useEffect(() => {
    fetchPrograms();
  }, [filters, page]);

  return (
    <div className="p-6 space-y-6">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-red-600">🔥 Program Dashboard</h1>
        <Button className="bg-blue-600 text-white" onClick={handleCreateProgram}>
          ➕ Create New Program
        </Button>
      </div>

      {/* Filters */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <select
          className="w-full p-2 border rounded"
          onChange={(e) => setFilters((f) => ({ ...f, term: e.target.value }))}
        >
          <option value="">Filter by Term</option>
          {[
            "Term 1 – Identity",
            "Term 2 – Nature",
            "Term 3 – Life and Living",
            "Term 4 – Civilisation",
            "Term 5 – Phenomena",
            "Term 6 – Service",
          ].map((term) => (
            <option key={term} value={term}>
              {term}
            </option>
          ))}
        </select>
        <Input placeholder="Filter by Subject" onChange={(e) => setFilters((f) => ({ ...f, subject: e.target.value }))} />
        <Input placeholder="Filter by Teacher" onChange={(e) => setFilters((f) => ({ ...f, teacher: e.target.value }))} />
        <Input placeholder="Filter by Title" onChange={(e) => setFilters((f) => ({ ...f, title: e.target.value }))} />
      </div>

      {/* Program Cards */}
      {loading ? (
        <p className="text-center text-gray-500 mt-6">Loading programs...</p>
      ) : programs.length === 0 ? (
        <p className="text-center text-gray-500 mt-6">No programs found.</p>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {programs.map((program) => {
            const attr = program.attributes;
            return (
              <Card key={program.id}>
                <CardContent className="p-4 space-y-2">
                  <h3 className="text-xl font-semibold">{attr?.title?.trim?.() || "Untitled Program"}</h3>
                  <p className="text-sm text-muted">Term: {attr?.term_focus?.toString?.() || "N/A"}</p>
                  <p className="text-sm">Subject: {attr?.subject_area?.data?.attributes?.name?.toString?.() || "N/A"}</p>
                  <div className="flex gap-2">
                    {attr?.slug ? (
                      <Link href={`/editor/${attr.slug}`}>
                        <Button size="sm">Edit</Button>
                      </Link>
                    ) : (
                      <Button size="sm" disabled>No Slug</Button>
                    )}
                    <Button variant="secondary" size="sm" onClick={() => handleClone(program)}>
                      Clone
                    </Button>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}