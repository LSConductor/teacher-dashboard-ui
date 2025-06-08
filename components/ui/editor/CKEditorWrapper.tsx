"use client";

import dynamic from "next/dynamic";
import { useEffect, useState } from "react";

// Dynamically load the CKEditor component
const CKEditor = dynamic(
  () => import("@ckeditor/ckeditor5-react").then((mod) => mod.CKEditor),
  { ssr: false }
);

// Load the ClassicEditor build
const ClassicEditorPromise = import("@ckeditor/ckeditor5-build-classic").then(
  (mod) => mod.default
);

interface Props {
  value: string;
  onChange: (value: string) => void;
}

export default function CKEditorWrapper({ value, onChange }: Props) {
  const [ClassicEditor, setClassicEditor] = useState<any>(null);

  useEffect(() => {
    ClassicEditorPromise.then((editor) => setClassicEditor(() => editor));
  }, []);

  if (!ClassicEditor) {
    return <p className="text-sm text-gray-500">Loading editor...</p>;
  }

  return (
    <div className="border rounded bg-white shadow-sm p-2">
      <CKEditor
        editor={ClassicEditor}
        data={value}
        onChange={(_, editor) => {
          const data = editor?.getData?.();
          if (typeof data === "string") {
            onChange(data);
          }
        }}
      />
    </div>
  );
}