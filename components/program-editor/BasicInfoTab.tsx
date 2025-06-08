"use client";

import React from "react";
import { Input } from "@ui/form/input";
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from "@ui/form/select";
import { Label } from "@ui/form/label";
import dynamic from "next/dynamic";

const CKEditor = dynamic(
  () => import("@ui/editor/CKEditorClientWrapper"),
  { ssr: false }
);

interface Props {
  program: any;
  handleFieldChange: (field: string, value: any) => void;
}

export default function BasicInfoTab({ program, handleFieldChange }: Props) {
  if (!program) {
    return (
      <p className="text-gray-400 text-center py-8 font-[Montserrat] font-light">
        🔄 Loading program details...
      </p>
    );
  }

  return (
    <div className="space-y-8 font-[Montserrat] max-w-4xl mx-auto p-6 bg-white shadow-sm border border-gray-200 rounded-xl">
      <h2 className="text-3xl font-semibold text-gray-800 border-b border-gray-200 pb-4">
        Program Overview
      </h2>

      {/* Row 1: Title + Teacher */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <Label htmlFor="title" className="text-sm font-normal text-gray-600">
            Program Title
          </Label>
          <Input
            id="title"
            className="mt-1 font-thin border border-gray-300 focus:ring-1 focus:ring-blue-400"
            value={program.title || ""}
            onChange={(e) => handleFieldChange("title", e.target.value)}
            placeholder="Enter program title"
          />
        </div>
        <div>
          <Label htmlFor="teacher_name" className="text-sm font-normal text-gray-600">
            Teacher Name
          </Label>
          <Input
            id="teacher_name"
            className="mt-1 font-thin border border-gray-300 focus:ring-1 focus:ring-blue-400"
            value={program.teacher_name || ""}
            onChange={(e) => handleFieldChange("teacher_name", e.target.value)}
            placeholder="Enter teacher name"
          />
        </div>
      </div>

      {/* Keystone Question */}
      <div>
        <Label htmlFor="keystone_question" className="text-sm font-normal text-gray-600">
          Keystone Question
        </Label>
        <Input
          id="keystone_question"
          className="mt-1 font-thin border border-gray-300 focus:ring-1 focus:ring-blue-400"
          value={program.keystone_question || ""}
          onChange={(e) => handleFieldChange("keystone_question", e.target.value)}
          placeholder="What drives this program?"
        />
      </div>

      {/* Big Idea */}
      <div>
        <Label className="text-sm font-normal text-gray-600">Big Idea</Label>
        <div className="mt-2 border border-gray-300 rounded shadow-sm">
          <CKEditor
            value={typeof program.big_idea === "string" ? program.big_idea : ""}
            onChange={(val: string) => handleFieldChange("big_idea", val)}
          />
        </div>
      </div>

      {/* Questions to Explore */}
      <div>
        <Label className="text-sm font-normal text-gray-600">Questions to Explore</Label>
        <div className="mt-2 border border-gray-300 rounded shadow-sm">
          <CKEditor
            value={
              typeof program.questions_to_explore === "string"
                ? program.questions_to_explore
                : ""
            }
            onChange={(val: string) => handleFieldChange("questions_to_explore", val)}
          />
        </div>
      </div>

      {/* Row 2: Term Focus + Stage */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <Label className="text-sm font-normal text-gray-600">Term Focus</Label>
          <Select onValueChange={(val) => handleFieldChange("term_focus", val)}>
            <SelectTrigger className="mt-1 font-thin border border-gray-300 focus:ring-1 focus:ring-blue-400">
              <SelectValue placeholder="Select term focus" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="Term 1 – Identity">Term 1 – Identity</SelectItem>
              <SelectItem value="Term 2 – Nature">Term 2 – Nature</SelectItem>
              <SelectItem value="Term 3 – Life and Living">Term 3 – Life and Living</SelectItem>
              <SelectItem value="Term 4 – Civilisation">Term 4 – Civilisation</SelectItem>
              <SelectItem value="Term 5 – Phenomena">Term 5 – Phenomena</SelectItem>
              <SelectItem value="Term 6 – Service">Term 6 – Service</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-sm font-normal text-gray-600">Stage</Label>
          <Select
            value={program.stage || ""}
            onValueChange={(val) => handleFieldChange("stage", val)}
          >
            <SelectTrigger className="mt-1 font-thin border border-gray-300 focus:ring-1 focus:ring-blue-400">
              <SelectValue placeholder="Select stage" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="ES1">ES1</SelectItem>
              <SelectItem value="S1">S1</SelectItem>
              <SelectItem value="S2">S2</SelectItem>
              <SelectItem value="S3">S3</SelectItem>
              <SelectItem value="S4">S4</SelectItem>
              <SelectItem value="S5">S5</SelectItem>
              <SelectItem value="S6">S6</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>
  );
}