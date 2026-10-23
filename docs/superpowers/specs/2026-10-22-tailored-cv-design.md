# Tailored CV — Design

**Date:** 2026-10-22
**Branch:** `feature/tailored-cv`
**Status:** Approved 2026-10-22 (admin page + logged-in Droopy shortcut)

## Intent

Ricardo applies to roles that each want a CV matching their job description.
Today the site offers one fixed CV PDF. This feature lets him paste a job
description in a private admin page, review an AI-proposed tailoring of his
CV, adjust it, and download a PDF built from the same template as the public
CV.

It must never put a claim on the CV that isn't in his own profile data. The AI
selects, orders and phrases; it does not invent.

## Goals

1. A private page where Ricardo pastes a job description (and an optional
   company name) and gets a tailored draft in about the time Droopy answers.
2. The draft reorders and selects existing content for the role:
   - a professional summary rewritten to lead with what the role asks for;
   - two or three "Key highlights" taken from his facts;
   - skills reordered so the role's skills come first;
   - for each job, his existing achievement bullets chosen and reordered.
3. A preview where he can edit the summary and highlights, untick bullets and
   see the role's gaps (shown in the preview only, never on the CV).
4. One-click download of a PDF using the current CV template, named
   `Ricardo-Contreras-CV-<Company>.pdf` (or `-Tailored.pdf` without a company).
5. Free to run: the same Groq → Gemini provider chain as Droopy.
6. A hidden Droopy shortcut: when Ricardo is logged in with the permission,
   typing `/tailored-cv` in Droopy replies "Opening your tailored-CV tool…"
   with a button to the page. For everyone else the text is an ordinary
   question.

## Non-goals

- Public access (recruiters generating CVs through Droopy). Possible later;
  this design keeps the service reusable for it.
- Cover letters, DOCX output, languages other than English.
- Saving job descriptions or generated CVs (beyond Drupal's temporary form
  cache, which expires after 6 hours).
- Editing bullet wording in the preview (select and reorder only).

## Anti-goals

- Any skill, employer, date, number or achievement on the PDF that is not in
  the profile data or "Extra knowledge for Droopy".
- A CV that silently differs from what the preview showed.
- Job descriptions or drafts leaking to anonymous users or into logs.

## How it works

### Page and access

- Route `/admin/portfolio/tailored-cv` (admin theme), new permission
  `use tailored cv` (granted to administrators), menu link under
  Administration › Content.
- A Drupal form with two steps in one form (form state, no storage):
  1. **Input:** job description (textarea, up to 6,000 characters, same limit
     as Droopy's fit mode), optional company name, "Generate draft".
  2. **Preview:** summary (editable textarea), highlights (up to 3 editable
     lines, each showing the fact it came from), skills in the proposed order
     (read-only), each job's bullets as checkboxes, pre-ticked and in the
     proposed order, a "Gaps for this role" box (read-only, not printed),
     buttons "Download PDF" and "Regenerate".

### Droopy shortcut

- `portfolio_theme_preprocess_page()` adds a `tailor_url` prop to the
  `droopy-guide` component only when the current user has `use tailored cv`,
  and adds the `user.permissions` cache context. Anonymous pages never contain
  the URL, so the internal page cache stays safe.
- `droopy-guide.js`: when the input is exactly `/tailored-cv` (trimmed,
  case-insensitive) **and** `data-tailor-url` is present, it doesn't call the
  API or store history; it shows a local bot message with an
  "Open tailored-CV tool" link. Without the attribute the text is sent as a
  normal question.
- No new endpoint and no secret: Drupal's login is the lock.

### Facts

Reuse `AssistantContextBuilder`'s facts (profile, jobs, skills, availability,
extra knowledge). The tailoring prompt receives them **with stable ids**:
each job `jN`, each bullet `jN.bM`, each skill by its exact label, each extra
knowledge line `nK`.

### Tailoring plan (AI output)

A new mode on the existing chain. The model must return one JSON object:

```json
{
  "summary": "string, ≤ 80 words, same first-person voice as the profile summary, facts only",
  "highlights": [{"text": "string ≤ 20 words", "source": "nK | jN.bM"}],
  "skills": ["exact skill labels in priority order"],
  "jobs": [{"id": "jN", "bullets": ["jN.bM", "..."]}],
  "gaps": ["requirement not covered by the facts"]
}
```

Rules in the prompt mirror Droopy's: only facts, no unlisted tools or skills,
no salary, plain text, English.

### Honesty check (server side, before the preview)

`TailoringPlanValidator` turns the raw answer into a safe plan:

- Parse the first JSON object in the answer; invalid → error message
  "Couldn't build a draft, try again" (no partial output).
- Drop bullet ids, job ids, skill labels and highlight sources that don't
  exist. Skills not mentioned keep their original order after the chosen ones.
- Jobs the model omitted keep all their bullets in the original order.
- Clamp lengths (summary ≤ 600 characters, ≤ 3 highlights, each ≤ 160).
- The summary and highlight text are the only AI-written words; the preview
  labels them "AI-written — check before downloading".

### PDF

Reuse Entity Print and `node--profile--pdf.html.twig`:

- A request-scoped `TailoredCvContext` service holds the approved plan.
- `_portfolio_core_preprocess_profile_pdf()` checks it: when set, it replaces
  the summary, the skills order and each job's bullets, and adds a
  `profile_highlights` variable (a new "Key highlights" section in the
  template, rendered only when present).
- The download handler sets the context, then renders the profile node
  through Entity Print's `PrintBuilder::printHtml()` and the Dompdf engine,
  and returns the PDF with its own file name. (Built this way instead of
  `deliverPrintable()`, which picks the file name itself.)
- The public "Download CV (PDF)" is unchanged (the context is empty there).
- Must still fit one A4 page; if a long summary pushes it to two, the preview
  warns (word count) rather than shrinking type further.

## Constraints

- CLAUDE.md rules: DI in classes, strict types, `t()`, typed config,
  coding standards (`phpcs`), unit tests for the validator and the prompt.
- Admin-only; no flood control needed. Job descriptions are sent to the
  provider (free tier) — acceptable because they're public postings.
- No new contrib modules.

## Decisions made by Claude (owner to check)

1. Admin page with a two-step form, not a Droopy chat flow (private first);
   Droopy only links to it, for logged-in users with the permission. An access
   key (unlock without login) was considered and rejected: a second secret to
   manage for little gain.
2. Bullets can be unticked and reordered by the AI, not reworded.
3. A "Key highlights" section is added to the tailored PDF only.
4. Gaps appear in the preview only.
5. Nothing is saved to content or logs; the job description and draft live
   only in Drupal's temporary form cache (expires after 6 hours). Regenerate if
   you leave the page.
6. File name from the optional company field.

## Left to the builder

- Exact prompt wording, JSON extraction strategy, token budget (~1,200).
- Preview layout within Gin's admin styles.

## Verification

- Unit tests: validator (unknown ids dropped, omitted jobs kept, clamping,
  invalid JSON), prompt composition (ids present, rules present).
- Manual: the SohoSGS Full Stack Engineer posting → summary leads with Drupal
  11, the 10→11/PHP migration and Acquia Cloud; highlights cite real facts;
  PDF downloads with the company name, one page, public CV unchanged;
  anonymous user gets 403 on the page.
