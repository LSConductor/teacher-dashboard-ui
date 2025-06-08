"use client";

import { useEffect, useState, useMemo } from "react";
import { Label } from "@/ui/form/label";
import { Textarea } from "@/ui/form/textarea";
import { Input } from "@/ui/form/input";
import { Button } from "@/ui/button";
import dynamic from "next/dynamic";

const CKEditor = dynamic(
  () => import("@/ui/editor/CKEditorClientWrapper"),
  { ssr: false }
);

interface Outcome {
  id: number;
  code: string;
  description: string;
  subject_area: string;
  stage: string;
}

interface Props {
  program: any;
  outcomes: Outcome[];
  selectedOutcomes: string[];
  setSelectedOutcomes: (val: string[]) => void;
  handleFieldChange: (field: string, value: any) => void;
}

const SUBJECT_ORDER = [
  "Circle/Guardian",
  "English",
  "Mathematics",
  "Science",
  "Science & Technology",
  "HSIE",
  "Geography",
  "History",
  "PDHPE",
  "Visual Art",
  "Music",
  "Drama",
  "Creative Arts",
  "Outdoor Education",
  "Electives",
  "Other",
];

export default function OutcomesTab({
  program,
  outcomes,
  selectedOutcomes,
  setSelectedOutcomes,
  handleFieldChange,
}: Props) {
  // Filter outcomes by current stage
  const [filteredOutcomes, setFilteredOutcomes] = useState<Outcome[]>([]);
  const stage = program?.stage || program?.attributes?.stage || "";
  useEffect(() => {
    setFilteredOutcomes(outcomes.filter((o) => o.stage === stage));
  }, [outcomes, stage]);

  // Local state for combined and subject text
  const [combinedText, setCombinedText] = useState(program?.nesa_outcomes_combined || "");
  const [englishText, setEnglishText] = useState(program?.english_outcomes || "");
  const [mathsText, setMathsText] = useState(program?.maths_outcomes || "");
  const [scienceText, setScienceText] = useState(program?.science_outcomes || "");

  // AI loading flags
  const [loadingIdeas, setLoadingIdeas] = useState({ english: false, maths: false, science: false });

  // Sandbox modal state
  const [sandboxOpen, setSandboxOpen] = useState({ english: false, maths: false, science: false });
  const [sandboxContent, setSandboxContent] = useState({ english: "", maths: "", science: "" });

  // Quick lookup of outcomes by code
  const outcomesMap = useMemo(
    () => Object.fromEntries(outcomes.map((o) => [o.code, o])),
    [outcomes]
  );

  // Generate AI ideas into the sandbox
  const generateSandboxIdeas = async (
    subject: keyof typeof sandboxContent,
    codes: string[]
  ) => {
    setLoadingIdeas((s) => ({ ...s, [subject]: true }));
    try {
      const res = await fetch("/api/generate-ideas", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ subject, outcomes: codes, stage }),
      });
      const data = await res.json();
      setSandboxContent((s) => ({ ...s, [subject]: data.ideas || "" }));
    } catch (error) {
      console.error(error);
      alert("Failed to generate ideas.");
    } finally {
      setLoadingIdeas((s) => ({ ...s, [subject]: false }));
    }
  };

  // Handle multi-select change
  const handleSelect = (values: string[]) => {
    setSelectedOutcomes(values);
    const selected = filteredOutcomes.filter((o) => values.includes(o.code));
    const grouped: Record<string, Outcome[]> = {};
    selected.forEach((o) => {
      const key = o.subject_area.trim().toLowerCase();
      if (!grouped[key]) grouped[key] = [];
      grouped[key].push(o);
    });

    const formatList = (list: Outcome[]) =>
      list.sort((a, b) => a.code.localeCompare(b.code))
          .map((o) => `${o.code} — ${o.description}`)
          .join("\n");

    const combined = SUBJECT_ORDER.map((subj) => {
      const list = grouped[subj.toLowerCase()];
      if (!list || !list.length) return null;
      return `<h1><strong>${subj}</strong></h1>` +
             list.map((o) => `<p>${o.code} — ${o.description}</p>`).join("");
    }).filter(Boolean).join("\n");

    // Individual subject texts
    const engList = grouped["english"] || [];
    const mathsList = grouped["mathematics"] || [];
    const sciList = grouped["science"] || grouped["science & technology"] || [];

    setCombinedText(combined);
    setEnglishText(formatList(engList));
    setMathsText(formatList(mathsList));
    setScienceText(formatList(sciList));

    // Persist
    handleFieldChange("nesa_outcomes_combined", combined);
    handleFieldChange("english_outcomes", formatList(engList));
    handleFieldChange("maths_outcomes", formatList(mathsList));
    handleFieldChange("science_outcomes", formatList(sciList));
  };

  const saveSandbox = (subject: keyof typeof sandboxContent) => {
    handleFieldChange(`${subject}_ideas`, sandboxContent[subject]);
    setSandboxOpen((s) => ({ ...s, [subject]: false }));
  };

  return (
    <>
      <div className="space-y-6 font-[Montserrat]">
        {/* Select outcomes */}
        <div>
          <Label className="text-lg font-semibold">Select NESA Outcomes</Label>
          <select
            multiple
            size={14}
            className="w-full p-2 mt-2 border rounded text-sm"
            value={selectedOutcomes}
            onChange={(e) =>
              handleSelect(
                Array.from(e.target.selectedOptions).map((o) => o.value)
              )
            }
          >
            {SUBJECT_ORDER.map((subject) => {
              const list = filteredOutcomes.filter(
                (o) => o.subject_area === subject
              );
              if (!list.length) return null;
              return [
                <option key={subject} disabled className="font-bold">
                  {subject}
                </option>,
                ...list.map((o) => (
                  <option key={o.code} value={o.code}>
                    {o.code} — {o.description}
                  </option>
                )),
              ];
            })}
          </select>
        </div>

        {/* Combined outcomes */}
        <div>
          <Label className="text-sm font-semibold">Combined NESA Outcomes</Label>
          <div className="text-sm border p-2 rounded max-h-60 overflow-auto">
            <CKEditor
              value={combinedText}
              onChange={(val) => handleFieldChange("nesa_outcomes_combined", val)}
            />
          </div>
        </div>

        {/* Subjects grid */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {(["english", "maths", "science"] as const).map((subject) => (
            <div key={subject} className="space-y-4">
              <Label className="capitalize font-semibold text-base">
                {subject} Outcomes
              </Label>
              <Textarea
                value={
                  subject === "english"
                    ? englishText
                    : subject === "maths"
                    ? mathsText
                    : scienceText
                }
                className="min-h-[120px] text-sm"
                onChange={(e) =>
                  handleFieldChange(`${subject}_outcomes`, e.target.value)
                }
              />

              {/* Ideas CKEditor */}
              <div className="flex justify-between items-center">
                <Label className="font-semibold text-base">
                  {subject} Ideas
                </Label>
                <Button
                  size="sm"
                  onClick={() =>
                    setSandboxOpen((s) => ({ ...s, [subject]: true }))
                  }
                >
                  Open Sandbox
                </Button>
              </div>
              <CKEditor
                value={program?.[`${subject}_ideas`] || sandboxContent[subject]}
                onChange={(val) => handleFieldChange(`${subject}_ideas`, val)}
              />

              {/* Resources */}
              <Label>Resources</Label>
              <Input
                type="file"
                onChange={(e) =>
                  handleFieldChange(
                    `${subject}_resources`,
                    e.target.files?.[0] || null
                  )
                }
              />
            </div>
          ))}
        </div>
      </div>

      {/* Sandbox modals */}
      {(["english", "maths", "science"] as const).map((subject) =>
        sandboxOpen[subject] ? (
          <div
            key={subject}
            className="fixed inset-0 z-50 bg-black/75 flex items-center justify-center overflow-auto"
          >
            <div className="bg-white p-6 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-auto">
              <h1 className="font-bold text-2xl uppercase mb-4">
                {subject} Ideas Sandbox
              </h1>
              <div className="border p-2 mb-4 rounded text-sm">
                <CKEditor
                  value={sandboxContent[subject]}
                  onChange={(val) =>
                    setSandboxContent((s) => ({ ...s, [subject]: val }))
                  }
                />
              </div>
              <div className="flex flex-wrap justify-end gap-2">
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() =>
                    setSandboxOpen((s) => ({ ...s, [subject]: false }))
                  }
                >
                  Cancel
                </Button>
                <Button
                  size="sm"
                  onClick={() =>
                    generateSandboxIdeas(
                      subject,
                      selectedOutcomes.filter((code) =>
                        outcomesMap[code]?.subject_area
                          ?.toLowerCase()
                          .startsWith(subject)
                      )
                    )
                  }
                  disabled={loadingIdeas[subject]}
                >
                  {loadingIdeas[subject]
                    ? "Generating…"
                    : "Generate AI Ideas"}
                </Button>
                <Button size="sm" onClick={() => saveSandbox(subject)}>
                  Save
                </Button>
              </div>
            </div>
          </div>
        ) : null
      )}
    </>
  );
}
