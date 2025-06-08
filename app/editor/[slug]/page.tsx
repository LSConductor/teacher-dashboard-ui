// app/editor/[slug]/page.tsx
// ✅ Server Component — do NOT add 'use client'

import ProgramEditClientWrapper from "@/components/program-editor/ProgramEditClientWrapper";

interface PageProps {
  params: { slug: string };
}

export default function Page({ params }: PageProps) {
  return <ProgramEditClientWrapper programSlug={params.slug} />;
}