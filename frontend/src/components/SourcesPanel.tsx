import type { Citation, Source } from "@/lib/chat";

type Props = {
  sources: Source[];
  citations: Citation[];
  activeSource: number | null;
  onSelectSource: (source: number) => void;
};

export function SourcesPanel({ sources, citations, activeSource, onSelectSource }: Props) {
  if (sources.length === 0) {
    return <p className="text-sm text-zinc-500 dark:text-zinc-400">Les extraits consultés apparaîtront ici.</p>;
  }

  return (
    <ol className="space-y-2">
      {sources.map((source, i) => {
        const number = i + 1;
        const quotes = citations.filter((c) => c.source === number).map((c) => c.citedText);
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
              } ${quotes.length === 0 && !active ? "opacity-60" : ""}`}
            >
              <div className="flex items-start gap-2">
                <span
                  className={`mt-0.5 inline-flex h-5 min-w-5 items-center justify-center rounded text-[11px] font-semibold ${
                    quotes.length > 0
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
                {quotes.length > 0 && (
                  <span className="shrink-0 text-xs font-medium text-teal-700 dark:text-teal-300">citée</span>
                )}
              </div>

              {active && (
                <div className="mt-3 space-y-2 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                  {quotes.length > 0 ? (
                    [...new Set(quotes)].map((quote) => (
                      <blockquote
                        key={quote}
                        className="border-l-2 border-teal-500 pl-3 text-zinc-700 dark:text-zinc-300"
                      >
                        {quote}
                      </blockquote>
                    ))
                  ) : (
                    <p className="whitespace-pre-wrap text-zinc-600 dark:text-zinc-400">{source.content}</p>
                  )}
                </div>
              )}
            </button>
          </li>
        );
      })}
    </ol>
  );
}
