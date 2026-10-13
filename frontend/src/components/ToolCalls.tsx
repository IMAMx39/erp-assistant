import type { ToolCall } from "@/lib/chat";

const LABELS: Record<string, string> = {
  get_article: "Fiche article",
  search_articles: "Recherche d’articles",
  get_stock: "Stock par emplacement",
  get_customer: "Compte client",
  get_customer_order: "Commande client",
  get_purchase_order: "Commande fournisseur",
  restock_suggestions: "Suggestions de réapprovisionnement",
};

/** Consultations de l'ERP faites par l'agent pour construire la réponse. */
export function ToolCalls({ tools }: { tools: ToolCall[] }) {
  return (
    <ul className="flex flex-wrap gap-1.5" aria-label="Données consultées dans l’ERP">
      {tools.map((tool, i) => {
        const detail = Object.values(tool.arguments).filter((v) => typeof v === "string" && v !== "").join(" · ");
        return (
          <li
            key={i}
            title={tool.error ?? undefined}
            className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs ${
              tool.error
                ? "border-amber-300 text-amber-800 dark:border-amber-800 dark:text-amber-300"
                : "border-zinc-200 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300"
            }`}
          >
            <span aria-hidden className={`h-1.5 w-1.5 rounded-full ${tool.error ? "bg-amber-500" : "bg-teal-500"}`} />
            <span className="font-medium">{LABELS[tool.name] ?? tool.name}</span>
            {detail && <span className="font-mono text-zinc-500 dark:text-zinc-400">{detail}</span>}
          </li>
        );
      })}
    </ul>
  );
}
