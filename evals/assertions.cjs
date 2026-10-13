// Assertions déterministes (sans LLM) pour promptfoo.
// La sortie du provider est le JSON de POST /api/chat : { answer, answered, citations, sources, ... }.
// Chaque fonction renvoie un GradingResult ; « non applicable » compte comme réussi pour promptfoo,
// les taux par catégorie sont recalculés proprement par summarize.mjs.

const notApplicable = { pass: true, score: 1, reason: "Non applicable à cette catégorie" };

function response(output) {
  return typeof output === "string" ? JSON.parse(output) : output;
}

/** Minuscules, sans accents, espaces normalisées : « 5 000 » = « 5000 », « 10 % » = « 10% ». */
function normalize(text) {
  return String(text)
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[   ]/g, " ")
    .replace(/[’‘]/g, "'")
    .replace(/\s+/g, " ")
    .replace(/(\d) (?=\d)/g, "$1")
    .replace(/ %/g, "%");
}

/** Recherche : au moins une source acceptée figure parmi les extraits retrouvés. */
function retrieval(output, { vars }) {
  if (vars.category === "refusal") return notApplicable;
  const found = response(output).sources.map((s) => s.documentPath);
  const hit = vars.sources.some((path) => found.includes(path));
  return {
    pass: hit,
    score: hit ? 1 : 0,
    reason: hit ? "Source attendue retrouvée" : `Sources retrouvées : ${[...new Set(found)].join(", ")}`,
  };
}

/** Refus : refus attendu pour les questions hors corpus, réponse attendue pour les autres. */
function refusal(output, { vars }) {
  const { answered } = response(output);
  const expected = vars.category !== "refusal";
  const pass = answered === expected;
  return {
    pass,
    score: pass ? 1 : 0,
    reason: pass ? "Comportement attendu" : expected ? "Refus à tort" : "A répondu au lieu de refuser",
  };
}

/** Exactitude : tous les faits attendus sont présents (une des variantes de chacun). */
function facts(output, { vars }) {
  if (vars.category === "refusal") return notApplicable;
  const answer = normalize(response(output).answer);
  const missing = vars.facts.filter((variants) => !variants.some((v) => answer.includes(normalize(v))));
  return {
    pass: missing.length === 0,
    score: (vars.facts.length - missing.length) / vars.facts.length,
    reason: missing.length === 0 ? "Tous les faits sont présents" : `Faits manquants : ${missing.map((v) => v[0]).join(", ")}`,
  };
}

/** Citations : au moins une citation, et toutes pointent vers une source acceptée. */
function citations(output, { vars }) {
  if (vars.category === "refusal") return notApplicable;
  const cited = [...new Set(response(output).citations.map((c) => c.documentPath))];
  const wrong = cited.filter((path) => !vars.sources.includes(path));
  const pass = cited.length > 0 && wrong.length === 0;
  return {
    pass,
    score: pass ? 1 : 0,
    reason: cited.length === 0 ? "Aucune citation" : wrong.length > 0 ? `Citations hors sources acceptées : ${wrong.join(", ")}` : "Citations correctes",
  };
}

/**
 * Recherche par section (sortie de GET /api/search) : rang de la première section attendue parmi
 * les extraits. Score = 1 / rang (réciproque du rang, moyenné en MRR), 0 si absente.
 */
function sectionRank(output, { vars }) {
  if (vars.category === "refusal") return notApplicable;
  const headings = response(output).results.map((r) => r.heading);
  const index = headings.findIndex((heading) => vars.sections.includes(heading));
  return {
    pass: index !== -1,
    score: index === -1 ? 0 : 1 / (index + 1),
    reason: index === -1 ? `Sections retrouvées : ${headings.join(" | ")}` : `Section attendue au rang ${index + 1}`,
  };
}

module.exports = { retrieval, refusal, facts, citations, sectionRank, normalize };
