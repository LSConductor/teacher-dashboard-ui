'use client'; // This directive is crucial for client-side components

import { useEffect, useState } from "react";

interface WeekData {
  week: string;
  focus: string;
  activities: string;
}

interface Props {
  program: any; // Consider a more specific ProgramData type if possible
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
   * Effect hook to initialize the `weeks` state when the `program` prop becomes available.
   * It populates `weeks` with existing data from `program.attributes.weekly_plans`
   * or defaults to an array of 6 empty week objects if no existing data is found.
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
  }, [program]); // Dependency on `program` ensures this runs when program data loads

  /**
   * Effect hook to automatically save changes to `weekly_plans` whenever the `weeks` state updates.
   * This propagates the changes up to the parent component (`ProgramEditClientWrapper`)
   * which then handles the actual API save to Strapi.
   */
  useEffect(() => {
    if (weeks.length > 0) { // Only attempt to save if weeks array has been initialized
      handleFieldChange("weekly_plans", weeks);
    }
  }, [weeks, handleFieldChange]); // Dependencies ensure this runs when `weeks` or `handleFieldChange` changes

  /**
   * Updates a specific field within a week's data.
   * @param index The index of the week to update.
   * @param field The field name (e.g., "week", "focus", "activities").
   * @param value The new value for the field.
   */
  const updateWeek = (index: number, field: keyof WeekData, value: string) => {
    const updated = weeks.map((week, i) =>
      i === index ? { ...week, [field]: value } : week
    );
    setWeeks(updated);
  };

  // Display a loading message if program attributes are not yet available
  if (!program?.attributes) {
    return <p className="text-gray-500 text-sm">Loading weekly plan...</p>;
  }

  // Render the weekly plan editor
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

            {/* Render input fields only when the week is open */}
            {openWeek === i && (
              <div className="space-y-3">
                <input
                  className="w-full border rounded px-3 py-2"
                  placeholder="Week name"
                  value={week.week}
                  onChange={(e) => updateWeek(i, "week", e.target.value)}
                />
                <input
                  className="w-full border rounded px-3 py-2"
                  placeholder="Focus"
                  value={week.focus}
                  onChange={(e) => updateWeek(i, "focus", e.target.value)}
                />
                <textarea
                  className="w-full border rounded px-3 py-2"
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
