'use client';

import { useEffect, useState, useMemo } from "react";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@ui/layout/tabs";
import BasicInfoTab from "./BasicInfoTab";
import OutcomesTab from "./OutcomesTab";
import WeeklyPlanTab from "./WeeklyPlanTab";
import AssessmentTab from "./AssessmentTab";
import ReportTab from "./ReportTab";
import { Button } from "@ui/button";

interface ProgramData {
  id: number;
  title: string;
  teacher_name: string;
  keystone_question: string;
  big_idea?: any;
  questions_to_explore?: any;
  term_focus: string;
  stage: string;
  outcomes?: { data: { id: number; attributes: { code: string } }[] };
  [key: string]: any;
}

interface Outcome {
  id: number;
  code: string;
  description: string;
  subject_area: string;
  stage: string;
}

interface Props {
  programSlug: string;
}

export default function ProgramEditClientWrapper({ programSlug }: Props) {
  const [program, setProgram] = useState<ProgramData | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [activeTab, setActiveTab] = useState("basic-info");
  const [outcomes, setOutcomes] = useState<Outcome[]>([]);
  const [selectedOutcomes, setSelectedOutcomes] = useState<string[]>([]);
  const [message, setMessage] = useState<string | null>(null);
  const [messageType, setMessageType] = useState<'success' | 'error' | null>(null);

  useEffect(() => {
    const fetchProgram = async () => {
      try {
        const res = await fetch(
          `${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs?filters[slug][$eq]=${programSlug}&populate=outcomes`
        );
        const json = await res.json();
        const entry = json?.data?.[0];

        if (!entry) {
          setProgram(null);
          return;
        }
        setProgram({ id: entry.id, ...entry.attributes });
        const rawOutcomes = entry.attributes?.outcomes?.data || [];

        if (Array.isArray(rawOutcomes) && rawOutcomes.length > 0) {
          const initialSelected = rawOutcomes.map(
            (outcome: any) => outcome?.attributes?.code ?? ""
          ).filter(Boolean);
          setSelectedOutcomes(initialSelected);
        }
      } catch (err) {
        console.error("Error fetching program:", err);
        setProgram(null);
        setMessage("Failed to load program details.");
        setMessageType('error');
      } finally {
        setLoading(false);
      }
    };

    if (programSlug) fetchProgram();
  }, [programSlug]);

  useEffect(() => {
    const fetchOutcomes = async () => {
      let page = 1;
      const pageSize = 100;
      let allOutcomes: Outcome[] = [];
      let more = true;

      try {
        while (more) {
          const res = await fetch(
            `${process.env.NEXT_PUBLIC_STRAPI_URL}/api/outcomes?pagination[page]=${page}&pagination[pageSize]=${pageSize}`
          );
          const json = await res.json();
          const entries = json?.data || [];

          if (!entries.length) break;

          allOutcomes.push(
            ...entries.map((entry: any) => {
              const a = entry.attributes ?? entry;
              return {
                id: entry.id,
                code: a.code || "",
                description: a.description || "",
                subject_area: a.subject_area || "Other",
                stage: a.stage || "",
              };
            })
          );

          page++;
          more = entries.length === pageSize;
        }
        setOutcomes(allOutcomes);
      } catch (err) {
        console.error("❌ Error loading outcomes:", err);
        setMessage("Failed to load available outcomes.");
        setMessageType('error');
      }
    };

    fetchOutcomes();
  }, []);

 const handleFieldChange = (field: string, value: any) => {
  if (!program) return;
  // **only update if something really changed**
  const current = (program as any)[field] || program.attributes?.[field] || "";
  if (current === value) return;
  setProgram({ ...program, [field]: value });
};

  const saveProgram = async () => {
    // your existing save logic
  };

  const availableOutcomesForStage = useMemo(() => {
    if (!program?.stage) return [];
    return outcomes.filter(
      (outcome) =>
        outcome.stage === program.stage || outcome.stage === "All Stages"
    );
  }, [outcomes, program?.stage]);

  if (loading) {
    return <p className="text-gray-400 text-center py-8">Loading program...</p>;
  }

  if (!program) {
    return <p className="text-red-500 text-center py-8">Program not found.</p>;
  }

  return (
    <div className="p-6 space-y-6 bg-gray-50 min-h-screen">
      {message && (
        <div className={`p-3 rounded text-sm ${messageType === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`}>{message}</div>
      )}

      <Tabs value={activeTab} onValueChange={setActiveTab} className="bg-white p-6 rounded-lg shadow-md">
        <TabsList className="mb-6">
          <TabsTrigger value="basic-info">Basic Info</TabsTrigger>
          <TabsTrigger value="outcomes">Outcomes</TabsTrigger>
          <TabsTrigger value="weekly-plan">Weekly Plan</TabsTrigger>
          <TabsTrigger value="assessment">Assessment</TabsTrigger>
          <TabsTrigger value="report">Report</TabsTrigger>
        </TabsList>

        <TabsContent value="basic-info">
          <BasicInfoTab program={program} handleFieldChange={handleFieldChange} />
        </TabsContent>

        <TabsContent value="outcomes">
          <OutcomesTab
            program={program}
            outcomes={availableOutcomesForStage}
            selectedOutcomes={selectedOutcomes}
            setSelectedOutcomes={setSelectedOutcomes}
            handleFieldChange={handleFieldChange}
          />
        </TabsContent>

        <TabsContent value="weekly-plan">
          <WeeklyPlanTab program={program} handleFieldChange={handleFieldChange} />
        </TabsContent>

        <TabsContent value="assessment">
          <AssessmentTab program={program} handleFieldChange={handleFieldChange} />
        </TabsContent>

        <TabsContent value="report">
          <ReportTab program={program} handleFieldChange={handleFieldChange} />
        </TabsContent>
      </Tabs>

      <div className="pt-4 text-right">
        <Button onClick={saveProgram} disabled={saving}>
          {saving ? "Saving..." : "Save Changes"}
        </Button>
      </div>
    </div>
  );
}