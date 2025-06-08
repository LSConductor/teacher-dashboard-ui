import { GoogleGenerativeAI } from "@google/generative-ai";
import { NextRequest, NextResponse } from "next/server";

const GEMINI_API_KEY = process.env.GEMINI_API_KEY;

if (!GEMINI_API_KEY) {
  throw new Error("Missing GEMINI_API_KEY in environment variables");
}

const genAI = new GoogleGenerativeAI(GEMINI_API_KEY);

export async function POST(req: NextRequest) {
  const { subject, stage, outcomes } = await req.json();

  const filteredOutcomes = outcomes.filter((o: string) => o.trim() !== "");

  const prompt = `
Act as a highly experienced NSW Stage ${stage} ${subject} teacher in a progressive school.
The following outcomes are selected: ${filteredOutcomes.join(", ")}.
Suggest 3 engaging, creative teaching ideas that help students deeply explore and demonstrate these outcomes.
`;

  try {
    const model = genAI.getGenerativeModel({ model: "gemini-1.5-pro" });

    const result = await model.generateContent({
      contents: [
        {
          role: "user",
          parts: [{ text: prompt }],
        },
      ],
    });

    const text = await result.response.text();

    const ideas = text
      .split(/\n(?:-|\d+\.)\s+/)
      .map((s) => s.trim())
      .filter((s) => s.length > 0)
      .slice(0, 3);

    return NextResponse.json({ ideas });
  } catch (error) {
    console.error("Gemini API error:", error);
    return NextResponse.json({ error: "Gemini API failed" }, { status: 500 });
  }
}