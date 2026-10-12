import type { Citation, Done } from "@/lib/chat";

type Props = {
  blocks: Record<number, string>;
  citations: Citation[];
  done?: Done;
  writing: boolean;
  activeSource: number | null;
  onSelectSource: (source: number) => void;
};

export function AnswerView({ blocks, citations, done, writing, activeSource, onSelectSource }: Props) {
  const indexes = Object.keys(blocks).map(Number).sort((a, b) => a - b);
  const notFound = done !== undefined && !done.answered && done.stopReason !== "refusal";

  if (done?.stopReason === "refusal") {
    return (
      <p className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
        Le modèle a refusé de répondre à cette demande.
      </p>
    );
  }

  return (
    <div
      className={
        notFound
          ? "rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400"
          : "leading-7 text-zinc-800 dark:text-zinc-100"
      }
    >
      <p className="whitespace-pre-wrap">
        {indexes.map((index) => (
          <span key={index}>
            <InlineMarkdown text={blocks[index]} />
            {sourcesOf(citations, index).map((source) => (
              <button
                key={source}
                type="button"
                onClick={() => onSelectSource(source)}
                aria-label={`Voir la source ${source}`}
                className={`mx-0.5 inline-flex h-5 min-w-5 -translate-y-0.5 items-center justify-center rounded px-1 align-middle text-[11px] font-semibold transition-colors ${
                  activeSource === source
                    ? "bg-teal-600 text-white"
                    : "bg-teal-100 text-teal-800 hover:bg-teal-200 dark:bg-teal-900/60 dark:text-teal-200 dark:hover:bg-teal-800"
                }`}
              >
                {source}
              </button>
            ))}
          </span>
        ))}
        {writing && <span className="ml-0.5 inline-block h-4 w-1.5 animate-pulse bg-teal-600 align-middle" />}
      </p>
    </div>
  );
}

function sourcesOf(citations: Citation[], block: number): number[] {
  const sources = citations.filter((c) => c.block === block).map((c) => c.source);
  return [...new Set(sources)].sort((a, b) => a - b);
}

/** Rendu minimal du gras Markdown (**texte**), le reste est affiché tel quel. */
function InlineMarkdown({ text }: { text: string }) {
  return text.split(/(\*\*[^*]+\*\*)/g).map((part, i) =>
    part.startsWith("**") && part.endsWith("**") && part.length > 4 ? (
      <strong key={i} className="font-semibold">
        {part.slice(2, -2)}
      </strong>
    ) : (
      part
    ),
  );
}
