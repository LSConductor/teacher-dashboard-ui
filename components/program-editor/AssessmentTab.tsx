"use client";

import React, { useState } from "react";
import CKEditorWrapper from "@/ui/editor/CKEditorWrapper";
import { Button } from "@/ui/button";
import { Input } from "@/ui/form/input";
import jsPDF from "jspdf";

interface Props {
  program: any;
  handleFieldChange: (field: string, value: any) => void;
}

export default function AssessmentTab({ program, handleFieldChange }: Props) {
  const [photo, setPhoto] = useState<File | null>(null);

  const exportToPDF = () => {
    const doc = new jsPDF();
    doc.text("Assessment Overview", 10, 10);
    doc.text("Method: " + (program.attributes?.assessment_method || ""), 10, 20);
    doc.text("Evidence: " + (program.attributes?.evidence_of_learning || ""), 10, 30);
    doc.save("assessment.pdf");
  };

  const handlePhotoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setPhoto(file);
      handleFieldChange("student_photo_evidence", file);
    }
  };

  return (
    <div className="bg-white p-6 rounded-xl shadow space-y-6">
      <h2 className="text-2xl font-bold text-gray-800">Assessment Overview</h2>

      <div>
        <h3 className="text-lg font-semibold">Assessment Method</h3>
        <CKEditorWrapper
          value={program.attributes?.assessment_method || ""}
          onChange={(data) => handleFieldChange("assessment_method", data)}
        />
      </div>

      <div>
        <h3 className="text-lg font-semibold">Evidence of Learning</h3>
        <CKEditorWrapper
          value={program.attributes?.evidence_of_learning || ""}
          onChange={(data) => handleFieldChange("evidence_of_learning", data)}
        />
      </div>

      <div>
        <h3 className="text-lg font-semibold">Linked NESA Outcomes</h3>
        <CKEditorWrapper
          value={program.attributes?.linked_outcomes || ""}
          onChange={(data) => handleFieldChange("linked_outcomes", data)}
        />
      </div>

      <div>
        <h3 className="text-lg font-semibold">Rubric</h3>
        <CKEditorWrapper
          value={program.attributes?.rubric || ""}
          onChange={(data) => handleFieldChange("rubric", data)}
        />
      </div>

      <div>
        <h3 className="text-lg font-semibold">Due Date</h3>
        <Input
          type="date"
          value={program.attributes?.due_date || ""}
          onChange={(e) => handleFieldChange("due_date", e.target.value)}
        />
      </div>

      <div>
        <h3 className="text-lg font-semibold">Adjustments for Diverse Learners</h3>
        <CKEditorWrapper
          value={program.attributes?.adjustments || ""}
          onChange={(data) => handleFieldChange("adjustments", data)}
        />
      </div>

      <div>
        <h3 className="text-lg font-semibold">Student Reflection Prompt</h3>
        <CKEditorWrapper
          value={program.attributes?.student_reflection_prompt || ""}
          onChange={(data) => handleFieldChange("student_reflection_prompt", data)}
        />
      </div>

      <div>
        <h3 className="text-lg font-semibold">Teacher Reflection</h3>
        <CKEditorWrapper
          value={program.attributes?.teacher_reflection || ""}
          onChange={(data) => handleFieldChange("teacher_reflection", data)}
        />
      </div>

      <div>
        <h3 className="text-lg font-semibold">Upload Student Evidence</h3>
        <input
          type="file"
          accept="image/*"
          className="block w-full text-sm"
          onChange={handlePhotoUpload}
        />
      </div>

      <div className="pt-4 flex justify-end space-x-4">
        <Button onClick={exportToPDF}>Export Assessment to PDF</Button>
        <Button variant="outline" onClick={() => alert("Auto-email not implemented yet.")}>Email Evidence to Home</Button>
      </div>
    </div>
  );
}