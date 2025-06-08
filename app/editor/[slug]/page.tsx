// app/editor/[slug]/page.tsx
// ✅ Server Component — do NOT add "use client"

import ProgramEditClientWrapper from "@/components/program-editor/ProgramEditClientWrapper";

interface PageProps {
  // Next.js now types `params` as a Promise
  params: Promise<{
    slug: string;
  }>;
}

export default async function Page({ params }: PageProps) {
  // Await the params promise
  const { slug } = await params;

  return <ProgramEditClientWrapper programSlug={slug} />;
}