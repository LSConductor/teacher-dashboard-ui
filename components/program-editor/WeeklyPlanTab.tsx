"use client";

import React, { useEffect, useState } from "react";
import { Input } from "@ui/form/input";
import { Textarea } from "@ui/form/textarea";
import { Label } from "@ui/form/label";

interface WeekData {
  week: string;
  focus: string;
  activities: string;
}

interface Props {
  program: any; // Consider a more specific type if possible
  handleFieldChange: (field: string, value: any) => void;
}

const initialWeekData: WeekData = {
  week: "",
  focus: "",
  activities: "",
};

export default function WeeklyPlanTab({ program, handleFieldChange }: Props) {
  const [weeks, setWeeks] = useState<WeekData[]>([]);
  const [openWeek, setOpenWeek] = useState<number | null>(null);

  /**
   * Initialize weeks from program or default to 6 weeks
   */
  useEffect(() => {
    if (program?.attributes) {
      const existing = program.attributes.weekly_plans;
      setWeeks(
        Array.isArray(existing) && existing.length > 0
          ? existing
          : Array(6).fill({ ...initialWeekData })
      );
    }
  }, [program]);

  /**
   * Persist changes when weeks state updates
   */
  useEffect(() => {
    if (weeks.length > 0) {
      handleFieldChange("weekly_plans", weeks);
    }
  }, [weeks, handleFieldChange]);

  const updateWeek = (
    index: number,
    field: keyof WeekData,
    value: string
  ) => {
    setWeeks((prev) =>
      prev.map((w, i) => (i === index ? { ...w, [field]: value } : w))
    );
  };

  if (!program?.attributes) {
    return <p className="text-gray-500 text-sm">Loading weekly plan...</p>;
  }

  return (
    <div className="space-y-6">
      <p className="text-gray-700 text-sm">Click a week to expand and edit.</p>
      <div className="grid gap-4">
        {weeks.map((week, i) => (
          <div key={i} className="border rounded-lg shadow p-4 bg-white">
            <button
              className="w-full text-left font-semibold text-lg mb-2"
              onClick={() => setOpenWeek(openWeek === i ? null : i)}
            >
              Week {i + 1}
            </button>

            {openWeek === i && (
              <div className="space-y-3">
                <Label htmlFor={`week-name-${i}`}>Week Name</Label>
                <Input
                  id={`week-name-${i}`}
                  placeholder="Week name"
                  value={week.week}
                  onChange={(e) => updateWeek(i, "week", e.target.value)}
                />
                <Label htmlFor={`week-focus-${i}`}>Focus</Label>
                <Input
                  id={`week-focus-${i}`}
                  placeholder="Focus"
                  value={week.focus}
                  onChange={(e) => updateWeek(i, "focus", e.target.value)}
                />
                <Label htmlFor={`week-activities-${i}`}>Activities</Label>
                <Textarea
                  id={`week-activities-${i}`}
                  placeholder="Activities"
                  value={week.activities}
                  onChange={(e) => updateWeek(i, "activities", e.target.value)}
                />
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}