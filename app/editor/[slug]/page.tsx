// app/editor/[slug]/page.tsx
// ✅ Server Component — DO NOT add "use client"

import dynamic from "next/dynamic";

const ProgramEditClientWrapper = dynamic(
  () => import("@/components/program-editor/ProgramEditClientWrapper"),
  { ssr: false }
);

interface PageProps {
  params: { slug: string };
}

export default function Page({ params }: PageProps) {
  // Server‐safe; delegates everything to the client wrapper
  return <ProgramEditClientWrapper programSlug={params.slug} />;
}