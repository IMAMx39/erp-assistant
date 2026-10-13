// Synthèse de la mesure de recherche (evals/retrieval.yaml) : node summarize-retrieval.mjs results/retrieval-hybrid.json
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";

const { sectionRank } = createRequire(import.meta.url)("./assertions.cjs");

const CATEGORIES = { fact: "Fait précis (une page)", multi: "Plusieurs pages", paraphrase: "Reformulation" };

const file = process.argv[2];
const data = JSON.parse(readFileSync(new URL(file, `file://${process.cwd()}/`), "utf8"));
const rows = data.results.results.filter((row) => row.vars.category !== "refusal");

const cases = rows.map((row) => {
  const output = row.response?.output;
  const id = row.testCase?.description ?? row.vars.question;
  if (!output || typeof output !== "object" || !Array.isArray(output.results)) {
    return { id, vars: row.vars, error: row.response?.error ?? row.error ?? "Réponse invalide" };
  }
  const check = sectionRank(output, { vars: row.vars });
  const pages = output.results.map((r) => r.documentPath);
  return {
    id,
    vars: row.vars,
    output,
    latency: row.latencyMs,
    found: check.pass,
    reciprocalRank: check.score,
    pageFound: row.vars.sources.some((path) => pages.includes(path)),
  };
});

const ok = cases.filter((c) => !c.error);
const pct = (part, total) => (total === 0 ? "—" : `${Math.round((100 * part) / total)} %`);
const ratio = (list, key) => `${pct(list.filter((c) => c[key]).length, list.length)} (${list.filter((c) => c[key]).length}/${list.length})`;
const mrr = (list) => (list.length === 0 ? "—" : (list.reduce((sum, c) => sum + c.reciprocalRank, 0) / list.length).toFixed(2));
const median = (values) => {
  const sorted = [...values].sort((a, b) => a - b);
  return sorted.length === 0 ? 0 : sorted[Math.floor(sorted.length / 2)];
};

const mode = ok[0]?.output.mode ?? rows[0]?.vars.mode ?? "inconnu";
const lines = [];
lines.push(`# Recherche par section — mode \`${mode}\``);
lines.push("");
lines.push(`- Date : ${data.results.timestamp ?? new Date().toISOString()}`);
lines.push(`- Questions : ${ok.length} couvertes par le corpus, ${Math.max(0, ...ok.map((c) => c.output.results.length))} extraits par question`);
if (cases.length !== ok.length) lines.push(`- Erreurs d'appel : ${cases.length - ok.length}`);
lines.push(`- Latence médiane de la recherche : ${median(ok.map((c) => c.latency).filter(Number.isFinite))} ms`);
lines.push("");
lines.push("| Mesure | Résultat |");
lines.push("|---|---|");
lines.push(`| Section attendue parmi les extraits | ${ratio(ok, "found")} |`);
lines.push(`| MRR (1 = toujours en tête) | ${mrr(ok)} |`);
lines.push(`| Page attendue parmi les extraits (mesure du J6) | ${ratio(ok, "pageFound")} |`);
lines.push("");
lines.push("| Catégorie | Questions | Section trouvée | MRR |");
lines.push("|---|---|---|---|");
for (const [key, label] of Object.entries(CATEGORIES)) {
  const list = ok.filter((c) => c.vars.category === key);
  if (list.length > 0) lines.push(`| ${label} | ${list.length} | ${ratio(list, "found")} | ${mrr(list)} |`);
}
lines.push("");
lines.push("## Sections manquées");
lines.push("");
const misses = cases.filter((c) => c.error || !c.found);
if (misses.length === 0) lines.push("Aucune.");
for (const c of misses) {
  if (c.error) {
    lines.push(`- **${c.id}** : erreur d'appel (${c.error})`);
    continue;
  }
  lines.push(`- **${c.id}** (${c.vars.category}) — attendu : ${c.vars.sections.join(" ou ")}`);
  lines.push(`  > retrouvé : ${c.output.results.map((r) => r.heading).join(" · ")}`);
}

console.log(lines.join("\n"));
