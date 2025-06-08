// components/program-editor/EditorPageWrapper.tsx

'use client';

import { useEffect, useState } from "react";
import EditorClientPage from "./EditorClientPage";

export default function EditorPageWrapper({ slug }: { slug: string }) {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    setReady(true);
  }, []);

  if (!ready) return <div className="p-4">🔄 Loading Editor...</div>;

  return <EditorClientPage slug={slug} />;
}