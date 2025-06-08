'use client';

import ProgramEditClientWrapper from "./ProgramEditClientWrapper";

export default function EditorClientPage({ slug }: { slug: string }) {
  return <ProgramEditClientWrapper programSlug={slug} />;
}