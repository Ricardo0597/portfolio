# Tailored CV Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A private admin page where Ricardo pastes a job description, reviews an AI-tailored draft of his CV (summary, highlights, skills order, bullet selection, gaps) and downloads it as a PDF, plus a logged-in `/tailored-cv` shortcut in Droopy.

**Architecture:** Reuse Droopy's facts (`AssistantContextBuilder`) and provider chain (`LlmChain`). A new prompt asks for a JSON tailoring plan that references facts by id; `TailoringPlanValidator` keeps only references that exist. A request-scoped `TailoredCvContext` lets the existing PDF preprocess swap in the plan; `CvPdfGenerator` renders the profile through Entity Print's `printHtml()` and the Dompdf engine and returns the bytes with a custom file name.

**Tech Stack:** Drupal 11.3, PHP 8.3, Entity Print (Dompdf), Form API, Twig, vanilla JS, PHPUnit (`drupal/core-dev`).

**Spec:** `docs/superpowers/specs/2026-10-22-tailored-cv-design.md`

## Global Constraints

- CLAUDE.md rules: strict types, DI in classes, PHPDoc, `t()`, BEM, tokens, no jQuery, `once()`; phpcs (Drupal, DrupalPractice) clean; `ddev drush cex -y` after config changes; explicit `git add`; no `Co-Authored-By`.
- Tests: `ddev exec ./vendor/bin/phpunit -c web/core web/modules/custom/portfolio_core/`.
- Branch `feature/tailored-cv`; **no push, no merge** until the owner approves.
- Permission machine name `use tailored cv`; route `portfolio_core.tailored_cv`, path `/admin/portfolio/tailored-cv`.
- Fact ids: jobs `j1…jN` in `facts['experience']` order; bullets `j1.b1…`; extra-knowledge lines `n1…nK`; skills by exact label.
- Plan JSON keys: `summary`, `highlights` (`[{text, source}]`), `skills` (labels), `jobs` (`[{id, bullets}]`), `gaps` (strings).
- Clamps: summary ≤ 600 chars; ≤ 3 highlights, each ≤ 160 chars; ≤ 6 gaps, each ≤ 200 chars; job description ≤ 6,000 chars; LLM `max_tokens` 1,200.
- File name: `<Site-Name-Slug>-CV-<Company-Slug>.pdf`, or `<Site-Name-Slug>-CV-Tailored.pdf` without a company (site name "Ricardo Contreras" → `Ricardo-Contreras`).
- Copy (exact): preview badge "AI-written — check before downloading"; error "Couldn't build a draft, try again."; Droopy shortcut reply "Opening your tailored-CV tool…", link text "Open tailored-CV tool".
- The public "Download CV (PDF)" must stay byte-for-byte unaffected when no plan is set.

## Review Focus

1. **Model returns prose around the JSON, or fenced ```json``` blocks** → extract the first balanced JSON object; anything else is "Couldn't build a draft". → Task 2 tests.
2. **Model invents a bullet or skill** (`j1.b99`, "Kubernetes") → dropped silently, never printed. → Task 2 tests.
3. **Anonymous or non-permitted user** hits the page or sees the shortcut → 403 / plain question; the anonymous page cache never contains the tool URL. → Task 6 + Task 7 checks.
4. **A tailored PDF leaks into the public CV** (context not request-scoped, or cached render) → public PDF unchanged after a tailored download. → Task 4 check.
5. **Unticking every bullet of a job** → the job still prints (title, dates) with no bullet list; no PHP warning. → Task 4 test.

---

### Task 1: Facts with ids + tailoring prompt

**Files:**
- Modify: `web/modules/custom/portfolio_core/src/Assistant/AssistantContextBuilder.php` (make `facts()` public)
- Create: `web/modules/custom/portfolio_core/src/Cv/CvTailoringPrompt.php`
- Modify: `portfolio_core.services.yml`
- Test: `tests/src/Unit/Cv/CvTailoringPromptTest.php`

**Interfaces:**
- Produces: `AssistantContextBuilder::facts(): array` (public, cached, unchanged shape).
- Produces: `CvTailoringPrompt::compose(array $facts): string` (pure; service `portfolio_core.cv_tailoring_prompt`). Lists every job as `[j1] Title at Company (dates)`, each bullet as `[j1.b1] text`, skills as `Skills: A, B, …`, notes as `[n1] text`, then rules and the exact JSON shape.

- [ ] **Step 1: Failing tests** — `testListsJobAndBulletIds` (`[j1]`, `[j1.b1]`, `[j2.b1]` present with their text), `testListsNoteIds` (`[n1]`), `testListsSkillsVerbatim`, `testStatesRulesAndJsonShape` (contains "only use facts listed", "reference bullets by id", "never invent", `"summary"`, `"highlights"`, `"skills"`, `"jobs"`, `"gaps"`, "first person").
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement.** **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** → `feat: add CV tailoring prompt with fact ids`.

### Task 2: TailoringPlanValidator

**Files:**
- Create: `web/modules/custom/portfolio_core/src/Cv/TailoringPlanValidator.php`
- Test: `tests/src/Unit/Cv/TailoringPlanValidatorTest.php`

**Interfaces:**
- Produces: `TailoringPlanValidator::validate(string $raw, array $facts): array` returning
  `{summary: string, highlights: list<{text, source, source_text}>, skills: array<string, list<string>> (category => labels, reordered), jobs: list<{index: int, bullets: list<{text: string, checked: bool}>}>, gaps: list<string>}`; throws `\InvalidArgumentException` when no JSON object can be parsed or `summary` is missing/empty.
- Ordering rules: categories ordered by their first chosen skill, then the rest in original order; inside a category chosen skills first (plan order), then the rest. Jobs: chosen bullets first (plan order, `checked: true`), then the others (original order, `checked: false`); a job missing from the plan keeps all bullets checked, original order.

- [ ] **Step 1: Failing tests** — `testParsesFencedJson` (```json …```), `testParsesJsonWithSurroundingProse`, `testInvalidJsonThrows`, `testMissingSummaryThrows`, `testDropsUnknownBulletAndJobIds`, `testDropsUnknownSkills`, `testDropsHighlightWithUnknownSource`, `testHighlightGetsSourceText`, `testOmittedJobKeepsAllBulletsChecked`, `testUnchosenBulletsAppendedUnchecked`, `testSkillsReorderedAcrossAndWithinCategories`, `testClampsLengthsAndCounts` (600 / 3×160 / 6×200).
- [ ] **Step 2: Run** → FAIL. **Step 3: Implement** (balanced-brace scan from the first `{`; `json_decode`; filters as above). **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** → `feat: validate AI tailoring plans against profile facts`.

### Task 3: CvTailor service

**Files:**
- Create: `web/modules/custom/portfolio_core/src/Cv/CvTailor.php`
- Modify: `portfolio_core.services.yml`
- Test: `tests/src/Unit/Cv/CvTailorTest.php`

**Interfaces:**
- Consumes: `AssistantContextBuilder::facts()`, `CvTailoringPrompt::compose()`, `LlmChain::complete()`, `TailoringPlanValidator::validate()`.
- Produces: `CvTailor::tailor(string $job_description): array` (validated plan) — throws `\RuntimeException` when no provider answers or the plan is invalid. Messages: system = prompt; one user message = the job description; `max_tokens` 1,200.

- [ ] **Step 1: Failing tests** (mock chain/builder): `testReturnsValidatedPlan`, `testNoProviderThrows`, `testInvalidPlanThrows`, `testSendsJobDescriptionAsUserMessage`.
- [ ] **Step 2–4:** RED → implement → GREEN.
- [ ] **Step 5: Commit** → `feat: add CvTailor service using the assistant provider chain`.

### Task 4: PDF with the plan (context + preprocess + generator)

**Files:**
- Create: `src/Cv/TailoredCvContext.php`, `src/Cv/CvPdfGenerator.php`
- Modify: `portfolio_core.module` (`_portfolio_core_preprocess_profile_pdf`), `web/themes/custom/portfolio_theme/templates/content/node--profile--pdf.html.twig` (Key highlights section), `scss/layout/cv-pdf.scss` (highlights style)
- Modify: `portfolio_core.services.yml`
- Test: `tests/src/Unit/Cv/CvPdfGeneratorTest.php` (file name), `tests/src/Unit/Cv/TailoredCvContextTest.php`

**Interfaces:**
- `TailoredCvContext`: `set(array $plan): void`, `get(): ?array`, `clear(): void` (service, request-scoped by nature: never persisted).
- Preprocess: when `get()` returns a plan → `profile_bio` = summary, `profile_skills` = plan skills, each job's `achievements` = its checked bullet texts, `profile_highlights` = highlight texts; else unchanged.
- `CvPdfGenerator::generate(NodeInterface $profile, array $plan): string` — sets context, `PrintBuilder::printHtml($profile)`, `EntityPrintPluginManager::createSelectedInstance('pdf')`, `addPage($html)`, `getBlob()`, then `clear()` (also on exceptions).
- `CvPdfGenerator::filename(string $site_name, string $company): string` — rules in Global Constraints (ASCII-transliterated, non-alphanumerics → `-`, trimmed, collapsed).

- [ ] **Step 1: Failing tests** — `testFilenameWithCompany` ("Ricardo Contreras", "SohoSGS" → `Ricardo-Contreras-CV-SohoSGS.pdf`), `testFilenameWithoutCompany`, `testFilenameStripsUnsafeCharacters` (`Acme / Corp, Inc.` → `Acme-Corp-Inc`), `testContextSetGetClear`.
- [ ] **Step 2–4:** RED → implement → GREEN. Template: `{% if profile_highlights %}` section "Key highlights" as a list, placed after the summary.
- [ ] **Step 5: Manual check** — `ddev drush php:eval` generating a PDF with a hand-made plan (one job's bullets all unticked) → write to scratchpad, `pdftotext` shows the highlights and the reduced bullets, 1 page; then `curl /print/pdf/node/1` → no "Key highlights", original summary.
- [ ] **Step 6: Commit** → `feat: render tailored CV PDFs from a plan using the existing template`.

### Task 5: Admin form, route, permission, menu link

**Files:**
- Create: `src/Form/TailoredCvForm.php`, `portfolio_core.permissions.yml`, `portfolio_core.links.menu.yml`
- Modify: `portfolio_core.routing.yml`

**Interfaces:**
- Route `portfolio_core.tailored_cv` → `_form: '\Drupal\portfolio_core\Form\TailoredCvForm'`, `_title: 'Tailored CV'`, requirement `_permission: 'use tailored cv'`, option `_admin_route: TRUE`.
- Permission `use tailored cv` (title "Use the tailored CV tool", `restrict access: true`).
- Menu link under `system.admin_content`, title "Tailored CV".
- Form (DI: `CvTailor`, `CvPdfGenerator`, entity type manager, config factory): step 1 fields `job_description` (textarea, required, maxlength 6000), `company` (textfield, optional, maxlength 80), submit "Generate draft" (rebuild with plan in form state; on `\RuntimeException` → error message "Couldn't build a draft, try again." and stay on step 1 with input kept). Step 2: `summary` (textarea, default plan, badge text), `highlights` (up to 3 textfields, description = "From: <source_text>"), skills shown as an item list, per job a `checkboxes` element (options = bullets in plan order, defaults = checked), gaps item list, submits "Download PDF" (builds the approved plan from the edited values, generates, `$form_state->setResponse()` with `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="…"`, `Cache-Control: no-store`) and "Regenerate" (re-runs step 1 with the same input).

- [ ] **Step 1: Implement** route, permission, menu link, form.
- [ ] **Step 2: Verify** — anonymous `curl -I /admin/portfolio/tailored-cv` → 403; admin (uli cookie) → 200; Playwright as admin: paste the SohoSGS posting (from the conversation) with company "SohoSGS" → preview shows summary + badge, highlights with sources, gaps; untick one bullet; Download → file `Ricardo-Contreras-CV-SohoSGS.pdf`, `pdftotext` shows the edited summary, highlights, without the unticked bullet, 1 page. Delete `.playwright-mcp/` afterwards.
- [ ] **Step 3: Commit** → `feat: add private tailored CV admin page`.

### Task 6: Droopy `/tailored-cv` shortcut

**Files:**
- Modify: `web/themes/custom/portfolio_theme/portfolio_theme.theme`, `components/droopy-guide/droopy-guide.component.yml` (prop `tailor_url`), `droopy-guide.twig` (`data-tailor-url` when set), `droopy-guide.js`

**Interfaces:**
- Preprocess: `tailor_url` = `Url::fromRoute('portfolio_core.tailored_cv')->toString()` only when `\Drupal::currentUser()->hasPermission('use tailored cv')`; add cache context `user.permissions`.
- JS: in `ask()`, before anything else, if `question.trim().toLowerCase() === '/tailored-cv'` and `root.dataset.tailorUrl` → append user message, bot message "Opening your tailored-CV tool…" with an `<a class="droopy__action">` "Open tailored-CV tool", clear input, no fetch, no history. Otherwise unchanged.

- [ ] **Step 1: Implement.** **Step 2: Verify** — anonymous HTML of `/` contains no `data-tailor-url` (and still served from page cache); admin: typing `/tailored-cv` shows the link and makes no `/api/portfolio/ask` request; anonymous typing `/tailored-cv` sends a normal question. **Step 3: Commit** → `feat: add logged-in /tailored-cv shortcut to Droopy`.

### Task 7: Docs and full verification

**Files:** `CLAUDE.md` (Tailored CV subsection under AI Assistant), `PROMPT.md` (Feature Inventory row 19).

- [ ] **Step 1:** Full suite + phpcs + `composer audit`; `ddev drush cex -y` shows no pending changes; public CV unchanged (`pdftotext` diff of `/print/pdf/node/1` before vs after the branch, ignoring nothing).
- [ ] **Step 2: Commit** → `docs: document the tailored CV tool`.
