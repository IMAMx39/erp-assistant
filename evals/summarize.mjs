// Synthèse d'une exécution promptfoo en Markdown : node summarize.mjs results/latest.json
// Les taux sont calculés sur les seuls cas concernés (les questions hors corpus n'ont ni source
// ni fait attendu), avec les mêmes fonctions que les assertions.
import { existsSync, readFileSync } from "node:fs";
import { createRequire } from "node:module";

const { retrieval, refusal, facts, citations, toolUsed } = createRequire(import.meta.url)("./assertions.cjs");

const CATEGORIES = {
  fact: "Fait précis (une page)",
  multi: "Plusieurs pages",
  paraphrase: "Reformulation",
  refusal: "Hors corpus (refus attendu)",
  agent: "Agent : données de l'ERP",
};

const file = process.argv[2] ?? "results/latest.json";
if (!existsSync(new URL(file, `file://${process.cwd()}/`))) {
  console.error(`Aucun résultat dans ${file} : l'évaluation n'a pas abouti.`);
  process.exit(1);
}
const data = JSON.parse(readFileSync(new URL(file, `file://${process.cwd()}/`), "utf8"));
const rows = data.results.results;

const cases = rows.map((row) => {
  const vars = row.vars;
  const output = row.response?.output;
  const id = row.testCase?.description ?? vars.question;
  if (!output || typeof output !== "object" || row.response?.error) {
    return { id, vars, error: row.response?.error ?? row.error ?? "Réponse invalide", latency: row.latencyMs };
  }
  const context = { vars };
  return {
    id,
    vars,
    output,
    latency: row.latencyMs,
    checks: {
      recherche: retrieval(output, context),
      refus: refusal(output, context),
      exactitude: facts(output, context),
      citations: citations(output, context),
      outils: toolUsed(output, context),
    },
  };
});

const ok = cases.filter((c) => !c.error);
const answerable = ok.filter((c) => c.vars.category !== "refusal" && c.vars.category !== "agent");
const agentCases = ok.filter((c) => c.vars.category === "agent");
const outOfScope = ok.filter((c) => c.vars.category === "refusal");

const pct = (part, total) => (total === 0 ? "—" : `${Math.round((100 * part) / total)} %`);
const rate = (list, check) => `${pct(list.filter((c) => c.checks[check].pass).length, list.length)} (${list.filter((c) => c.checks[check].pass).length}/${list.length})`;
const percentile = (values, p) => {
  const sorted = [...values].sort((a, b) => a - b);
  return sorted.length === 0 ? 0 : sorted[Math.min(sorted.length - 1, Math.ceil((p / 100) * sorted.length) - 1)];
};
const average = (values) => (values.length === 0 ? 0 : Math.round(values.reduce((a, b) => a + b, 0) / values.length));

const models = [...new Set(ok.map((c) => c.output.model).filter(Boolean))];
const fullPass = ok.filter((c) => Object.values(c.checks).every((check) => check.pass));
const falseRefusals = answerable.filter((c) => c.output.answered === false);
const latencies = ok.map((c) => c.latency).filter((v) => typeof v === "number");

const lines = [];
lines.push("# Résultats d'évaluation");
lines.push("");
lines.push(`- Date : ${data.results.timestamp ?? new Date().toISOString()}`);
lines.push(`- Modèle : ${models.join(", ") || "inconnu"}`);
lines.push(`- Recherche : mode \`${rows[0]?.vars.mode ?? "inconnu"}\``);
lines.push(`- Questions : ${cases.length} (${answerable.length} couvertes par le corpus, ${outOfScope.length} hors corpus, ${agentCases.length} sur les données de l'ERP)`);
if (cases.length !== ok.length) lines.push(`- Erreurs d'appel : ${cases.length - ok.length}`);
lines.push("");
lines.push("## Vue d'ensemble");
lines.push("");
lines.push("| Mesure | Résultat | Sur |");
lines.push("|---|---|---|");
lines.push(`| Recherche : une source attendue parmi les 5 extraits | ${rate(answerable, "recherche")} | questions couvertes |`);
lines.push(`| Exactitude : tous les faits attendus dans la réponse | ${rate(answerable, "exactitude")} | questions couvertes |`);
lines.push(`| Citations : au moins une, toutes vers une source acceptée | ${rate(answerable, "citations")} | questions couvertes |`);
lines.push(`| Refus justes | ${rate(outOfScope, "refus")} | questions hors corpus |`);
lines.push(`| Agent : bon outil appelé | ${rate(agentCases, "outils")} | questions sur les données |`);
lines.push(`| Agent : valeurs attendues dans la réponse | ${rate(agentCases, "exactitude")} | questions sur les données |`);
lines.push(`| Refus à tort | ${pct(falseRefusals.length, answerable.length)} (${falseRefusals.length}/${answerable.length}) | questions couvertes |`);
lines.push(`| Réussite complète (tous les contrôles) | ${pct(fullPass.length, ok.length)} (${fullPass.length}/${ok.length}) | toutes |`);
lines.push("");
lines.push(`Latence : médiane ${percentile(latencies, 50)} ms, 95e centile ${percentile(latencies, 95)} ms. `
  + `Tokens moyens par question : ${average(ok.map((c) => c.output.inputTokens))} en entrée, ${average(ok.map((c) => c.output.outputTokens))} en sortie.`);
lines.push("");
lines.push("## Par catégorie");
lines.push("");
lines.push("| Catégorie | Questions | Recherche | Exactitude | Citations | Outils | Refus correct |");
lines.push("|---|---|---|---|---|---|---|");
for (const [key, label] of Object.entries(CATEGORIES)) {
  const list = ok.filter((c) => c.vars.category === key);
  if (list.length === 0) continue;
  const na = () => "—";
  const show = (check, applies) => (applies ? rate(list, check) : na());
  const docs = key !== "refusal" && key !== "agent";
  lines.push(`| ${label} | ${list.length} | ${show("recherche", docs)} | ${show("exactitude", key !== "refusal")} | ${show("citations", docs)} | ${show("outils", key === "agent")} | ${rate(list, "refus")} |`);
}
lines.push("");
lines.push("## Échecs");
lines.push("");
const failures = cases.filter((c) => c.error || !Object.values(c.checks).every((check) => check.pass));
if (failures.length === 0) lines.push("Aucun.");
for (const c of failures) {
  if (c.error) {
    lines.push(`- **${c.id}** : erreur d'appel (${c.error})`);
    continue;
  }
  const reasons = Object.entries(c.checks).filter(([, check]) => !check.pass).map(([name, check]) => `${name} : ${check.reason}`);
  lines.push(`- **${c.id}** (${c.vars.category}) — ${reasons.join(" ; ")}`);
  lines.push(`  > ${c.output.answer.replace(/\s+/g, " ").slice(0, 220)}`);
}

console.log(lines.join("\n"));
