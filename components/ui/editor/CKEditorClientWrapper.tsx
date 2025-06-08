"use client";

import React, { useEffect, useState } from "react";

interface Props {
  value: string;
  onChange: (value: string) => void;
}

export default function CKEditorClient({ value, onChange }: Props) {
  const [editorLoaded, setEditorLoaded] = useState(false);
  const [CKEditor, setCKEditor] = useState<any>(null);
  const [ClassicEditor, setClassicEditor] = useState<any>(null);

  useEffect(() => {
    Promise.all([
      import("@ckeditor/ckeditor5-react").then((mod) => mod.CKEditor),
      import("@ckeditor/ckeditor5-build-classic").then((mod) => mod.default),
    ]).then(([CKEditorImport, ClassicEditorImport]) => {
      setCKEditor(() => CKEditorImport);
      setClassicEditor(() => ClassicEditorImport);
      setEditorLoaded(true);
    });
  }, []);

  if (!editorLoaded || !CKEditor || !ClassicEditor) {
    return <p>Loading editor...</p>;
  }

  return (
    <div className="border rounded shadow-sm bg-white">
      <CKEditor
        editor={ClassicEditor}
        data={value}
        onChange={(_, editor) => onChange(editor.getData())}
      />
    </div>
  );
}