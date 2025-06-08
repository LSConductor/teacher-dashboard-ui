'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent } from '@/ui/card';
import { Input } from '@ui/form/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@ui/form/select';
import { Button } from '@ui/button';
import Link from 'next/link';

export default function TeacherProgramDashboard() {
  const [programs, setPrograms] = useState<any[]>([]);
  const [filters, setFilters] = useState({
    term: '',
    subject: '',
    teacher: '',
    title: '',
  });
  const [page, setPage] = useState(1);

  const fetchPrograms = async () => {
    const params = new URLSearchParams();
    if (filters.term) params.append('filters[term_focus][$eq]', filters.term);
    if (filters.subject) params.append('filters[subject_area][name][$containsi]', filters.subject);
    if (filters.teacher) params.append('filters[teacher_name][$containsi]', filters.teacher);
    if (filters.title) params.append('filters[title][$containsi]', filters.title);

    params.append('pagination[page]', page.toString());
    params.append('pagination[pageSize]', '10');
    params.append('populate', '*');
    params.append('publicationState', 'preview');

    const res = await fetch(
      `${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs?${params.toString()}`
    );

    const data = await res.json();
    setPrograms(data.data || []);
  };

  const handleClone = async (program: any) => {
    const res = await fetch(`${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        data: {
          ...program.attributes,
          title: `${program.attributes.title} (Copy)`,
          slug: `${program.attributes.slug}-copy-${Date.now()}`,
        },
      }),
    });

    if (res.ok) {
      await fetchPrograms();
    } else {
      alert('Cloning failed.');
    }
  };

  useEffect(() => {
    fetchPrograms();
  }, [filters, page]);

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-2xl font-bold text-red-600">🔥 Dashboard is LIVE</h1>

      {/* Filters */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <Select onValueChange={(value) => setFilters((f) => ({ ...f, term: value }))}>
          <SelectTrigger>
            <SelectValue placeholder="Filter by Term" />
          </SelectTrigger>
          <SelectContent>
            {[
              'Term 1 – Identity',
              'Term 2 – Nature',
              'Term 3 – Life and Living',
              'Term 4 – Civilisation',
              'Term 5 – Phenomena',
              'Term 6 – Service',
            ].map((term) => (
              <SelectItem key={term} value={term}>
                {term}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Input placeholder="Filter by Subject" onChange={(e) => setFilters((f) => ({ ...f, subject: e.target.value }))} />
        <Input placeholder="Filter by Teacher" onChange={(e) => setFilters((f) => ({ ...f, teacher: e.target.value }))} />
        <Input placeholder="Filter by Title" onChange={(e) => setFilters((f) => ({ ...f, title: e.target.value }))} />
      </div>

      {/* Program cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {programs.length === 0 ? (
          <p className="text-center col-span-full text-gray-500 mt-12">No programs found.</p>
        ) : (
          programs.map((program) => {
            const attr = program.attributes;
            return (
              <Card key={program.id}>
                <CardContent className="p-4 space-y-2">
                  <h3 className="text-xl font-semibold">{attr.title || 'Untitled Program'}</h3>
                  <p className="text-sm text-muted-foreground">Term: {attr.term_focus || 'N/A'}</p>
                  <p className="text-sm">Subject: {attr.subject_area?.data?.attributes?.name || 'N/A'}</p>
                  <p className="text-sm">Excursion: {attr.excursions_incursions?.substring(0, 50) || 'None'}</p>

                  <div className="flex gap-2">
                    <Link href={`/editor/${attr.slug}`}>
                      <Button variant="outline" size="sm">Edit</Button>
                    </Link>
                    <Button variant="secondary" size="sm" onClick={() => handleClone(program)}>Clone</Button>
                  </div>
                </CardContent>
              </Card>
            );
          })
        )}
      </div>

      {/* Pagination */}
      <div className="flex justify-center gap-4 mt-6">
        <Button variant="outline" onClick={() => setPage((p) => Math.max(p - 1, 1))}>Previous</Button>
        <span>Page {page}</span>
        <Button variant="outline" onClick={() => setPage((p) => p + 1)}>Next</Button>
      </div>
    </div>
  );
}