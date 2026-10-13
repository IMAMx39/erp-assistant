"use client";

import { useEffect, useRef, useState } from "react";
import { ChatError, streamChat, type Citation, type Done, type Source, type ToolCall } from "@/lib/chat";
import { AnswerView } from "./AnswerView";
import { Composer } from "./Composer";
import { SourcesPanel } from "./SourcesPanel";
import { ToolCalls } from "./ToolCalls";

type Turn = {
  id: number;
  question: string;
  status: "searching" | "writing" | "done" | "error";
  sources: Source[];
  blocks: Record<number, string>;
  citations: Citation[];
  tools: ToolCall[];
  done?: Done;
  error?: string;
};

const EXAMPLES = [
  "Qui peut clôturer un inventaire ?",
  "Peut-on supprimer un emplacement qui contient du stock ?",
  "Comment corriger une facture déjà validée ?",
  "Quel écart déclenche un recomptage d'inventaire ?",
];

export function Chat() {
  const [turns, setTurns] = useState<Turn[]>([]);
  const [selectedTurn, setSelectedTurn] = useState<number | null>(null);
  const [activeSource, setActiveSource] = useState<number | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const endRef = useRef<HTMLDivElement>(null);

  const busy = turns.some((t) => t.status === "searching" || t.status === "writing");
  const current = turns.find((t) => t.id === selectedTurn) ?? turns.at(-1);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
  }, [turns]);

  function update(id: number, change: (turn: Turn) => Partial<Turn>) {
    setTurns((all) => all.map((t) => (t.id === id ? { ...t, ...change(t) } : t)));
  }

  async function ask(question: string) {
    const id = Date.now();
    const controller = new AbortController();
    abortRef.current = controller;

    setTurns((all) => [...all, { id, question, status: "searching", sources: [], blocks: {}, citations: [], tools: [] }]);
    setSelectedTurn(id);
    setActiveSource(null);

    try {
      await streamChat(
        question,
        {
          onSources: (sources) => update(id, () => ({ sources })),
          onDelta: (block, text) =>
            update(id, (t) => ({
              status: "writing",
              blocks: { ...t.blocks, [block]: (t.blocks[block] ?? "") + text },
            })),
          onCitation: (citation) => update(id, (t) => ({ citations: [...t.citations, citation] })),
          onTool: (tool) => update(id, (t) => ({ tools: [...t.tools, tool] })),
          onDone: (done) => update(id, () => ({ status: "done", done })),
        },
        controller.signal,
      );
      // Flux interrompu sans événement « done » : on fige ce qui a été reçu.
      update(id, (t) => (t.status === "done" ? {} : { status: "done" }));
    } catch (error) {
      if (controller.signal.aborted) {
        update(id, () => ({ status: "done" }));
        return;
      }
      const message =
        error instanceof ChatError ? error.message : "Impossible de joindre l'assistant. Vérifiez que l'API tourne.";
      update(id, () => ({ status: "error", error: message }));
    }
  }

  function selectSource(turnId: number, source: number) {
    setSelectedTurn(turnId);
    setActiveSource((active) => (turnId === current?.id && active === source ? null : source));
  }

  return (
    <div className="flex h-dvh flex-col">
      <header className="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
        <div className="mx-auto flex max-w-6xl items-baseline gap-3">
          <h1 className="text-base font-semibold text-zinc-900 dark:text-zinc-50">Gestix · Assistant documentaire</h1>
          <p className="hidden text-sm text-zinc-500 sm:block">Réponses tirées de la documentation, avec leurs sources</p>
        </div>
      </header>

      <div className="mx-auto grid min-h-0 w-full max-w-6xl flex-1 grid-cols-[minmax(0,1fr)] lg:grid-cols-[minmax(0,1fr)_380px]">
        <main className="flex min-h-0 flex-col">
          <div className="flex-1 overflow-y-auto px-4 py-6">
            {turns.length === 0 ? (
              <EmptyState onPick={ask} />
            ) : (
              <div className="mx-auto max-w-3xl space-y-8">
                {turns.map((turn) => (
                  <article key={turn.id} className="space-y-3">
                    <p className="ml-auto w-fit max-w-[85%] rounded-2xl rounded-br-sm bg-zinc-100 px-4 py-2 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">
                      {turn.question}
                    </p>

                    {turn.tools.length > 0 && <ToolCalls tools={turn.tools} />}
                    {turn.status === "searching" && (
                      <p className="text-sm text-zinc-500">
                        {turn.sources.length === 0 ? "Recherche dans la documentation…" : "Rédaction de la réponse…"}
                      </p>
                    )}
                    {turn.error && (
                      <p className="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                        {turn.error}
                      </p>
                    )}
                    {Object.keys(turn.blocks).length > 0 && (
                      <AnswerView
                        blocks={turn.blocks}
                        citations={turn.citations}
                        done={turn.done}
                        writing={turn.status === "writing"}
                        activeSource={turn.id === current?.id ? activeSource : null}
                        onSelectSource={(source) => selectSource(turn.id, source)}
                      />
                    )}
                    {turn.done && (turn.done.inputTokens > 0 || turn.done.outputTokens > 0) && (
                      <p className="text-xs text-zinc-400">
                        {turn.done.model && `${turn.done.model} · `}
                        {turn.done.inputTokens} tokens en entrée · {turn.done.outputTokens} en sortie
                      </p>
                    )}

                    {/* Sur mobile, les sources s'affichent sous la réponse. */}
                    {turn.sources.length > 0 && (
                      <details className="lg:hidden">
                        <summary className="cursor-pointer text-sm font-medium text-zinc-600 dark:text-zinc-300">
                          Sources ({turn.sources.length})
                        </summary>
                        <div className="mt-2">
                          <SourcesPanel
                            sources={turn.sources}
                            citations={turn.citations}
                            activeSource={turn.id === current?.id ? activeSource : null}
                            onSelectSource={(source) => selectSource(turn.id, source)}
                            unanswered={turn.done?.answered === false}
                          />
                        </div>
                      </details>
                    )}
                  </article>
                ))}
                <div ref={endRef} />
              </div>
            )}
          </div>

          <div className="px-4 pb-4">
            <div className="mx-auto max-w-3xl">
              <Composer busy={busy} onSubmit={ask} onStop={() => abortRef.current?.abort()} />
              <p className="mt-2 text-center text-xs text-zinc-400">
                Gestix est un ERP fictif. Les réponses peuvent contenir des erreurs : vérifiez les sources.
              </p>
            </div>
          </div>
        </main>

        <aside className="hidden min-h-0 overflow-y-auto border-l border-zinc-200 px-4 py-6 lg:block dark:border-zinc-800">
          <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Sources</h2>
          {current && current.question && (
            <p className="mb-3 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-400">« {current.question} »</p>
          )}
          <SourcesPanel
            sources={current?.sources ?? []}
            citations={current?.citations ?? []}
            activeSource={activeSource}
            onSelectSource={(source) => current && selectSource(current.id, source)}
            unanswered={current?.done?.answered === false}
          />
        </aside>
      </div>
    </div>
  );
}

function EmptyState({ onPick }: { onPick: (question: string) => void }) {
  return (
    <div className="mx-auto flex h-full max-w-xl flex-col justify-center gap-6 py-12">
      <div>
        <h2 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Une question sur Gestix ?</h2>
        <p className="mt-2 text-zinc-600 dark:text-zinc-400">
          L’assistant cherche la réponse dans la documentation et cite les passages utilisés. S’il ne trouve pas,
          il le dit.
        </p>
      </div>
      <ul className="grid gap-2 sm:grid-cols-2">
        {EXAMPLES.map((example) => (
          <li key={example}>
            <button
              type="button"
              onClick={() => onPick(example)}
              className="h-full w-full rounded-lg border border-zinc-200 px-3 py-2.5 text-left text-sm text-zinc-700 hover:border-teal-500 hover:bg-teal-50/50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:border-teal-700 dark:hover:bg-teal-950/20"
            >
              {example}
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}
