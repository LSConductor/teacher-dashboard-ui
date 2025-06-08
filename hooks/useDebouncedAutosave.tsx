import { useCallback } from "react";

export default function useDebouncedAutosave(id: number | null) {
  const debounceMap = new Map<string, NodeJS.Timeout>();

  return useCallback(
    (field: string, value: any) => {
      if (!id || typeof id !== "number" || isNaN(id)) {
        console.warn(`⚠️ Autosave skipped. Invalid or missing ID:`, id);
        return;
      }

      if (debounceMap.has(field)) {
        clearTimeout(debounceMap.get(field)!);
      }

      const timeout = setTimeout(async () => {
        try {
          const res = await fetch(
            `${process.env.NEXT_PUBLIC_STRAPI_URL}/api/programs/${id}`,
            {
              method: "PUT",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify({
                data: {
                  [field]: value,
                },
              }),
            }
          );

          let responseJson: any;
          try {
            responseJson = await res.json();
          } catch (parseError) {
            console.error("❌ Autosave failed: Response not JSON", parseError);
            return;
          }

          if (!res.ok) {
            const errorMessage =
              responseJson?.error?.message ||
              responseJson?.message ||
              "Unknown error";
            console.error("❌ Autosave failed:", errorMessage);
          } else {
            console.log(`✅ Autosaved "${field}" successfully.`);
          }
        } catch (err) {
          console.error("❌ Autosave error (network/server):", err);
        }
      }, 1000);

      debounceMap.set(field, timeout);
    },
    [id]
  );
}