- // ✅ Server Component — do NOT add 'use client'
-
- import dynamic from "next/dynamic";
-
- const ProgramEditClientWrapper = dynamic(
-   () => import("@/components/program-editor/ProgramEditClientWrapper"),
-   { ssr: false }
- );
+ // ✅ Server Component — do NOT add 'use client'
+ 
+ import ProgramEditClientWrapper from "@/components/program-editor/ProgramEditClientWrapper";

  interface PageProps {
    params: { slug: string };
  }

  export default function Page({ params }: PageProps) {
    return <ProgramEditClientWrapper programSlug={params.slug} />;
  }