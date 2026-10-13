// Client du endpoint POST /api/chat/stream (Server-Sent Events).

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080";

export type Source = {
  chunkId: number;
  documentPath: string;
  documentTitle: string;
  module: string;
  heading: string;
  content: string;
  distance: number;
};

export type Citation = {
  /** Numéro de la source, à partir de 1. */
  source: number;
  chunkId: number;
  documentPath: string;
  citedText: string;
  /** Bloc de texte de la réponse auquel la citation se rattache. */
  block: number;
};

export type Done = {
  answered: boolean;
  model: string | null;
  stopReason: string | null;
  inputTokens: number;
  outputTokens: number;
};

export type ChatHandlers = {
  onSources: (sources: Source[]) => void;
  onDelta: (block: number, text: string) => void;
  onCitation: (citation: Citation) => void;
  onDone: (done: Done) => void;
};

export class ChatError extends Error {}

export async function streamChat(
  question: string,
  handlers: ChatHandlers,
  signal?: AbortSignal,
): Promise<void> {
  const response = await fetch(`${API_URL}/api/chat/stream`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json, text/event-stream" },
    body: JSON.stringify({ question }),
    signal,
  });

  if (!response.ok || !response.body) {
    throw new ChatError(await errorMessage(response));
  }

  const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
  let buffer = "";

  for (;;) {
    const { value, done } = await reader.read();
    if (done) break;

    buffer += value;
    let separator: number;
    while ((separator = buffer.indexOf("\n\n")) !== -1) {
      dispatch(buffer.slice(0, separator), handlers);
      buffer = buffer.slice(separator + 2);
    }
  }
}

function dispatch(raw: string, handlers: ChatHandlers): void {
  let event = "message";
  let data = "";

  for (const line of raw.split("\n")) {
    if (line.startsWith("event:")) event = line.slice(6).trim();
    else if (line.startsWith("data:")) data += line.slice(5).trim();
  }

  if (data === "") return;
  const payload = JSON.parse(data);

  switch (event) {
    case "sources":
      handlers.onSources(payload);
      break;
    case "delta":
      handlers.onDelta(payload.block, payload.text);
      break;
    case "citation":
      handlers.onCitation(payload);
      break;
    case "done":
      handlers.onDone(payload);
      break;
    case "error":
      throw new ChatError(payload.message);
  }
}

async function errorMessage(response: Response): Promise<string> {
  try {
    const body = await response.json();
    // Erreurs de validation Symfony (application/problem+json) ou erreur applicative.
    return body.violations?.[0]?.title ?? body.error ?? body.detail ?? `Erreur ${response.status}`;
  } catch {
    return `Erreur ${response.status}`;
  }
}
