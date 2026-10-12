"use client";

import { useState, type FormEvent, type KeyboardEvent } from "react";

type Props = {
  busy: boolean;
  onSubmit: (question: string) => void;
  onStop: () => void;
};

export function Composer({ busy, onSubmit, onStop }: Props) {
  const [value, setValue] = useState("");

  function submit(event?: FormEvent) {
    event?.preventDefault();
    const question = value.trim();
    if (question === "" || busy) return;
    onSubmit(question);
    setValue("");
  }

  function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    // Entrée envoie, Maj+Entrée passe à la ligne.
    if (event.key === "Enter" && !event.shiftKey && !event.nativeEvent.isComposing) {
      submit(event);
    }
  }

  return (
    <form
      onSubmit={submit}
      className="flex items-end gap-2 rounded-xl border border-zinc-300 bg-white p-2 shadow-sm focus-within:border-teal-500 dark:border-zinc-700 dark:bg-zinc-900"
    >
      <label htmlFor="question" className="sr-only">
        Votre question
      </label>
      <textarea
        id="question"
        rows={1}
        value={value}
        maxLength={1000}
        onChange={(e) => setValue(e.target.value)}
        onKeyDown={onKeyDown}
        placeholder="Posez une question sur Gestix…"
        className="field-sizing-content max-h-40 min-h-10 w-full min-w-0 flex-1 resize-none bg-transparent px-2 py-2 text-base outline-none placeholder:text-zinc-400"
      />
      {busy ? (
        <button
          type="button"
          onClick={onStop}
          className="h-10 rounded-lg border border-zinc-300 px-4 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"
        >
          Arrêter
        </button>
      ) : (
        <button
          type="submit"
          disabled={value.trim() === ""}
          className="h-10 rounded-lg bg-teal-600 px-4 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-40"
        >
          Envoyer
        </button>
      )}
    </form>
  );
}
