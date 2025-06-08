"use client";

import { useEffect, useState } from "react";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@ui/tabs";
import { Button } from "@ui/button";
import BasicInfoTab from "@/components/program-editor/BasicInfoTab";
import OutcomesTab from "@/components/program-editor/OutcomesTab";
import WeeklyPlanTab from "@/components/program-editor/WeeklyPlanTab";
import AssessmentTab from "@/components/program-editor/AssessmentTab";
import ReportTab from "@/components/program-editor/ReportTab";

interface Props {
  programId: string;
}

export default function ProgramEditFormMultiStep({ programId }: Props) {
  const [program, setProgram] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [currentTab, setCurrentTab] = useState("basic");

  const [outcomes, setOutcomes] = useState<any[]>([]);
  const [selectedOutcomes, setSelectedOutcomes] = useState<string[]>([]);
  const [isSaving, setIsSaving] = useState(false);
  const [saveMessage, setSaveMessage] = useState("");
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  // Load program
  const fetchProgram = async () => {
    setLoading(true);
    try {
      const res = await fetch(
        `${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs?filters[slug][$eq]=${programId}&populate=*`
      );
      const data = await res.json();

      if (data?.data?.length > 0) {
        setProgram(data.data[0]);
      } else {
        setError("Program not found.");
      }
    } catch (err) {
      console.error("❌ Fetch error:", err);
      setError("Failed to load program.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProgram();
  }, [programId]);

  // Load NESA outcomes once stage is known
  useEffect(() => {
    if (program?.attributes?.Stage) {
      const fetchOutcomes = async () => {
        try {
          const allOutcomes: any[] = [];
          let page = 1;
          let hasMore = true;

          while (hasMore) {
            const res = await fetch(
              `${process.env.NEXT_PUBLIC_STRAPI_URL}/api/outcomes?filters[Stage][$eq]=${program.attributes.Stage}&pagination[page]=${page}&pagination[pageSize]=100`
            );
            const data = await res.json();
            if (data?.data?.length > 0) {
              allOutcomes.push(...data.data);
              page++;
            } else {
              hasMore = false;
            }
          }

          setOutcomes(allOutcomes);
        } catch (err) {
          console.error("❌ Outcome fetch failed:", err);
        }
      };

      fetchOutcomes();
    }
  }, [program?.attributes?.Stage]);

  // Handle field changes
  const handleFieldChange = (field: string, value: any) => {
    if (!program) return;
    const updated = {
      ...program,
      attributes: {
        ...program.attributes,
        [field]: value,
      },
    };
    setProgram(updated);
    setHasUnsavedChanges(true);
  };

  // Save to Strapi
  const handleSave = async () => {
    if (!program?.id) return;
    setIsSaving(true);
    setSaveMessage("");

    try {
      const res = await fetch(`${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs/${program.id}`, {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ data: program.attributes }),
      });

      const data = await res.json();

      if (!res.ok) {
        console.error("❌ Save failed:", data?.error?.message || data);
        setSaveMessage("❌ Save failed.");
      } else {
        console.log("✅ Saved successfully");
        setSaveMessage("✅ Changes saved");
        setHasUnsavedChanges(false);
      }
    } catch (err) {
      console.error("❌ Save error:", err);
      setSaveMessage("❌ Save error.");
    } finally {
      setIsSaving(false);
      setTimeout(() => setSaveMessage(""), 3000);
    }
  };

  // Change tab and autosave
  const handleTabChange = async (tab: string) => {
    if (hasUnsavedChanges) {
      await handleSave();
    }
    setCurrentTab(tab);
  };

  // UI states
  if (loading) return <p className="text-center py-8 text-gray-600">Loading program...</p>;
  if (error || !program)
    return <p className="text-red-600 font-medium text-center py-8">{error || "Program not found."}</p>;

  return (
    <section className="max-w-6xl mx-auto px-6 py-10">
      <h1 className="text-4xl font-bold mb-8 text-center text-gray-800">Program Builder</h1>

      <Tabs value={currentTab} onValueChange={handleTabChange} className="space-y-6">
        <TabsList className="flex flex-wrap gap-3 justify-center p-2 bg-gray-100 rounded-xl shadow-inner">
          <TabsTrigger value="basic">Basic Info</TabsTrigger>
          <TabsTrigger value="outcomes">Outcomes</TabsTrigger>
          <TabsTrigger value="weekly">Weekly Plan</TabsTrigger>
          <TabsTrigger value="assessment">Assessment</TabsTrigger>
          <TabsTrigger value="report">Report</TabsTrigger>
        </TabsList>

        <TabsContent value="basic">
          <BasicInfoTab program={program} handleFieldChange={handleFieldChange} />
        </TabsContent>
        <TabsContent value="outcomes">
          <OutcomesTab
            program={program}
            outcomes={outcomes}
            selectedOutcomes={selectedOutcomes}
            setSelectedOutcomes={setSelectedOutcomes}
            handleFieldChange={handleFieldChange}
          />
        </TabsContent>
        <TabsContent value="weekly">
          <WeeklyPlanTab program={program} handleFieldChange={handleFieldChange} />
        </TabsContent>
        <TabsContent value="assessment">
          <AssessmentTab program={program} handleFieldChange={handleFieldChange} />
        </TabsContent>
        <TabsContent value="report">
          <ReportTab program={program} handleFieldChange={handleFieldChange} />
        </TabsContent>
      </Tabs>

      <div className="mt-6 flex justify-end items-center gap-4">
        {saveMessage && <span className="text-sm text-gray-600">{saveMessage}</span>}
        <Button
          disabled={isSaving || !hasUnsavedChanges}
          onClick={handleSave}
          className="bg-green-600 hover:bg-green-700 text-white"
        >
          {isSaving ? "Saving..." : "💾 Save Changes"}
        </Button>
      </div>
    </section>
  );
}