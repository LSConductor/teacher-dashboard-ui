// ✅ Server Component — do NOT add 'use client'

import EditorClientPage from "@/components/program-editor/EditorClientPage";

interface Props {
  params: { slug: string };
}

export default async function EditorPage({ params }: Props) {
  // ✅ Await required to safely access `params.slug` in App Router
  await Promise.resolve(); // can replace with real fetch later if needed

  const slug = params.slug;
  return <EditorClientPage slug={slug} />;
}