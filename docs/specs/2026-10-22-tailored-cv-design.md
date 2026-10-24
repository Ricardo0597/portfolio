# Tailored CV — Design

**Date:** 2026-10-22
**Branch:** `feature/tailored-cv`
**Status:** Implemented (merged to `main` 2026-10-23); see the drift notes at the end

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
   - a professional summary rewritten to lead with what the role asks for,
     in CV style (no personal pronouns) and stating the total years of
     experience;
   - two or three "Key highlights" taken from his facts;
   - the role's skills ticked and moved first, unrelated skills unticked;
   - for each job, his existing achievement bullets chosen and reordered.
3. A preview where he can edit the summary and highlights, tick or untick
   skills and bullets and see the role's gaps (shown in the preview only,
   never on the CV).
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
     lines, each showing the fact it came from), skills per category as
     checkboxes (the role's skills ticked and first), each job's bullets as
     checkboxes (chosen ones ticked and first, the rest unticked below), a
     "Gaps for this role" box (read-only, not printed), a live word count
     under the summary, buttons "Download PDF" and "Regenerate". The company
     field stays editable and is read again at download time.

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
knowledge line `nK`, each certification `cK`.

### Tailoring plan (AI output)

A new mode on the existing chain. The model must return one JSON object:

```json
{
  "summary": "string, ≤ 80 words, CV style without personal pronouns, states the total years, facts only",
  "highlights": [{"text": "string ≤ 20 words", "source": "nK | cK | jN.bM"}],
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
  exist. Chosen skills are ticked and lead their category (categories are
  ordered by their best-ranked skill); skills not mentioned follow unticked.
  If the model picks no known skill, every skill stays ticked.
- Jobs the model omitted keep all their bullets, ticked, in the original
  order. In a chosen job, picked bullets come first (ticked), the rest follow
  unticked so the owner can re-tick them.
- Highlights whose source is a bullet that will print are dropped (they would
  repeat it). A highlight that names a skill its cited fact doesn't mention is
  kept but flagged in the preview.
- The summary is flagged when it uses personal pronouns or omits the years
  figure.
- A highlight with a number its cited fact doesn't contain is flagged too.
- Clamp lengths (summary ≤ 600 characters, ≤ 3 highlights, each ≤ 160,
  ≤ 6 gaps, each ≤ 200) and replace glyphs the PDF fonts lack (non-breaking
  hyphens, minus sign, arrows, non-breaking spaces). The owner's edits get
  the same normalisation.
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
  `deliverPrintable()`, which picks the file name itself.) It switches to the
  default front-end theme for the render (the CV template lives there; the
  page runs in the admin theme) and back afterwards.
- `portfolio_core_node_build_defaults_alter()` removes the render-cache keys
  of the profile build while a plan is set, so a tailored render is never
  cached or served as the public CV (and vice versa).
- The public "Download CV (PDF)" is unchanged (the context is empty there).
- Must still fit one A4 page; the preview shows a live word count, and if the
  rendered PDF runs past one page the download is held back once with a
  warning and a "Download anyway" button, rather than shrinking type further.

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

## Code/doc drift found 2026-10-23

Reconciled against the code while writing `docs/studies/tailored-cv.md`.

**Corrected in this spec:** status; summary voice (pronoun-free CV style with
the years figure, not first person); skills as checkboxes instead of a
read-only order; unticked rather than dropped bullets in chosen jobs;
certifications as highlight sources (`cK`); highlights that repeat a printed
bullet are dropped; preview warnings (pronouns, missing years, unsupported
skills); gaps limits; glyph normalisation; live word count; editable company
field; theme switch and render-cache bypass during the PDF render.

**Code issues found, not fixed:**
- The 6,000-character job description limit is the form's `#maxlength`
  (enforced server-side by Form API); `CvTailor` has no limit of its own, so a
  future public caller (e.g. through Droopy) must add one, plus flood control.
- The one-page fit is not checked: the preview shows a word count, but nothing
  measures the rendered PDF.
- No functional or end-to-end test covers the form or the downloaded PDF; the
  pieces are unit-tested (`tests/src/Unit/Cv/`, 42 tests).
- Fact ids are positional (`j1` is the first job in field order), so reordering
  experience on the profile between "Generate" and "Download" would point the
  plan at different jobs. The 6-hour form cache makes this unlikely but possible.

**Could not verify:** how often providers return unusable JSON in practice
(no logging of answers, by design).

**Fixed the same day:**
- Plan jobs store the work experience paragraph id, and
  `TailoringPlanValidator::locateJob()` matches by it, so reordering
  experience during a draft can no longer move bullets.
- `CvPdfGenerator` returns a `CvPdf` with the page count; a draft longer than
  one page triggers a warning and a "Download anyway" button.
- `CvTailor` enforces the 6,000-character limit and 20 drafts per hour per
  client (flood event `portfolio_core.cv_tailor`), throwing
  `TailoringLimitException` (`TOO_LONG`, `TOO_MANY`).
- Highlights are also flagged for numbers that are not in the cited fact.
- Playwright covers anonymous 403, the owner's page and the `/tailored-cv`
  shortcut (`tests/e2e/tailored-cv.spec.js`). The full generate-to-download
  path was checked by hand with a live provider (draft, two-page warning,
  "Download anyway", `Ricardo-Contreras-CV-Acme-Corp.pdf`).
