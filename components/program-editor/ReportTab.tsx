"use client";

import React, { useState } from "react";
import dynamic from "next/dynamic";

const CKEditorWrapper = dynamic(() => import("@/ui/editor/CKEditorClientWrapper"), {
  ssr: false,
});
import { Input } from "@/ui/form/input";
import { Button } from "@/ui/button";
import {
  Accordion,
  AccordionItem,
  AccordionTrigger,
  AccordionContent,
} from "@/ui/layout/accordion";
import jsPDF from "jspdf";

interface Props {
  program: any;
  handleFieldChange: (field: string, value: any) => void;
}

export default function ReportTab({ program, handleFieldChange }: Props) {
  const [studentVoice, setStudentVoice] = useState(program.attributes?.student_voice || "");
  const [teacherComment, setTeacherComment] = useState(program.attributes?.teacher_comment || "");
  const [parentSummary, setParentSummary] = useState(program.attributes?.parent_summary || "");
  const [engagementRating, setEngagementRating] = useState(program.attributes?.engagement_rating || 3);

  const exportToPDF = () => {
    const doc = new jsPDF();
    doc.setFontSize(14);
    doc.text("Student Report Summary", 20, 20);
    doc.setFontSize(11);
    doc.text("Teacher Comment:", 20, 30);
    doc.text(teacherComment || "[Not provided]", 20, 38);
    doc.text("Student Voice:", 20, 50);
    doc.text(studentVoice || "[Not provided]", 20, 58);
    doc.text("Parent Summary:", 20, 70);
    doc.text(parentSummary || "[Not provided]", 20, 78);
    doc.text(`Engagement Rating: ${engagementRating}`, 20, 90);
    doc.save("student-report.pdf");
  };

  return (
    <div className="bg-white p-6 rounded-xl shadow space-y-6">
      <h2 className="text-2xl font-semibold text-center">Student Report Tab</h2>
      <Accordion type="multiple" className="w-full space-y-4">
        <AccordionItem value="teacher-comment">
          <AccordionTrigger>Teacher Comment</AccordionTrigger>
          <AccordionContent>
            <CKEditorWrapper
              value={teacherComment}
              onChange={(data) => {
                setTeacherComment(data);
                handleFieldChange("teacher_comment", data);
              }}
            />
          </AccordionContent>
        </AccordionItem>

        <AccordionItem value="student-voice">
          <AccordionTrigger>Student Voice</AccordionTrigger>
          <AccordionContent>
            <CKEditorWrapper
              value={studentVoice}
              onChange={(data) => {
                setStudentVoice(data);
                handleFieldChange("student_voice", data);
              }}
            />
          </AccordionContent>
        </AccordionItem>

        <AccordionItem value="parent-summary">
          <AccordionTrigger>Parent Summary</AccordionTrigger>
          <AccordionContent>
            <CKEditorWrapper
              value={parentSummary}
              onChange={(data) => {
                setParentSummary(data);
                handleFieldChange("parent_summary", data);
              }}
            />
          </AccordionContent>
        </AccordionItem>

        <AccordionItem value="engagement">
          <AccordionTrigger>Engagement Rating</AccordionTrigger>
          <AccordionContent>
            <input
              type="range"
              min="1"
              max="5"
              value={engagementRating}
              className="w-full"
              onChange={(e) => {
                const val = parseInt(e.target.value);
                setEngagementRating(val);
                handleFieldChange("engagement_rating", val);
              }}
            />
            <div className="text-sm mt-2">{engagementRating} / 5</div>
          </AccordionContent>
        </AccordionItem>

        <AccordionItem value="upload">
          <AccordionTrigger>Upload Learning Evidence</AccordionTrigger>
          <AccordionContent>
            <Input
              type="file"
              accept="image/*,video/*,application/pdf"
              onChange={(e) => {
                const file = e.target.files?.[0];
                if (file) handleFieldChange("uploaded_evidence", file);
              }}
            />
          </AccordionContent>
        </AccordionItem>

        <AccordionItem value="skills">
          <AccordionTrigger>Core Skills and Values</AccordionTrigger>
          <AccordionContent>
            <p className="text-sm text-gray-600 mb-2">Coming soon: Radar chart and rubric selectors</p>
            {/* Reserved for future integration */}
          </AccordionContent>
        </AccordionItem>
      </Accordion>

      <div className="flex justify-end pt-4">
        <Button className="bg-green-600 hover:bg-green-700" onClick={exportToPDF}>
          Export Report to PDF
        </Button>
      </div>
    </div>
  );
}