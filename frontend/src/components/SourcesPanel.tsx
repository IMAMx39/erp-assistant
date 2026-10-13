import type { Citation, Source } from "@/lib/chat";

type Props = {
  sources: Source[];
  citations: Citation[];
  activeSource: number | null;
  onSelectSource: (source: number) => void;
  /** Réponse terminée sans s'appuyer sur la documentation (refus). */
  unanswered?: boolean;
};

export function SourcesPanel({ sources, citations, activeSource, onSelectSource, unanswered = false }: Props) {
  if (sources.length === 0) {
    return <p className="text-sm text-zinc-500 dark:text-zinc-400">Les extraits consultés apparaîtront ici.</p>;
  }

  return (
    <>
      {unanswered && (
        <p className="mb-3 text-sm text-zinc-500 dark:text-zinc-400">
          Aucun de ces extraits ne répond à la question : ce sont les plus proches trouvés dans la documentation.
        </p>
      )}
      <ol className="space-y-2">
      {sources.map((source, i) => {
        const number = i + 1;
        const cited = citations.some((c) => c.source === number);
        // Les fournisseurs sans citations natives ne renvoient pas l'extrait exact (citedText vide).
        const quotes = [
          ...new Set(citations.filter((c) => c.source === number && c.citedText !== "").map((c) => c.citedText)),
        ];
        const active = activeSource === number;
        const section = source.heading.split(" > ").slice(1).join(" › ");

        return (
          <li key={source.chunkId}>
            <button
              type="button"
              onClick={() => onSelectSource(number)}
              className={`w-full rounded-lg border p-3 text-left transition-colors ${
                active
                  ? "border-teal-500 bg-teal-50/60 dark:border-teal-600 dark:bg-teal-950/30"
                  : "border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700"
              } ${!cited && !active ? "opacity-60" : ""}`}
            >
              <div className="flex items-start gap-2">
                <span
                  className={`mt-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded text-[11px] font-semibold ${
                    cited
                      ? "bg-teal-600 text-white"
                      : "bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                  }`}
                >
                  {number}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{source.documentTitle}</p>
                  {section && <p className="text-sm text-zinc-600 dark:text-zinc-400">{section}</p>}
                  <p className="mt-1 truncate font-mono text-xs text-zinc-500">{source.documentPath}</p>
                </div>
                {cited && (
                  <span className="shrink-0 text-xs font-medium text-teal-700 dark:text-teal-300">citée</span>
                )}
              </div>

              {active && (
                <div className="mt-3 space-y-2 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                  {quotes.length > 0 ? (
                    quotes.map((quote) => (
                      <blockquote
                        key={quote}
                        className="border-l-2 border-teal-500 pl-3 text-zinc-700 dark:text-zinc-300"
                      >
                        {quote}
                      </blockquote>
                    ))
                  ) : (
                    <p className="whitespace-pre-wrap text-zinc-600 dark:text-zinc-400">{plainText(source.content)}</p>
                  )}
                </div>
              )}
            </button>
          </li>
        );
      })}
      </ol>
    </>
  );
}

/** Retire la syntaxe Markdown des extraits : gras, code, liens internes au corpus. */
function plainText(markdown: string): string {
  return markdown
    .replace(/\[([^\]]+)\]\([^)]*\)/g, "$1")
    .replace(/\*\*([^*]+)\*\*/g, "$1")
    .replace(/`([^`]+)`/g, "$1");
}
