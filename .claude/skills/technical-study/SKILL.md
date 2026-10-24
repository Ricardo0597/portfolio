---
name: technical-study
description: Write an outward-facing technical study of a feature we already built, so another team can understand how it works, why it was built that way, and decide whether their use case matches before replicating it. First reconciles existing project docs against the actual code (and fixes drift), then writes the study. Use this whenever the user asks for a "technical study", "technical write-up", "architecture overview for another team", "how we built X", documentation for someone who "wants to replicate / reuse / copy" a feature, or a handoff doc for an outside developer, peer institution, advisory board, or vendor, even if they don't use the word "study".
---

# Technical Study

A technical study explains a feature we already shipped to people **outside the team**: another
developer, a peer department, a vendor. Its job is to let that reader answer two questions fast:

1. *Does my situation match theirs?*
2. *If so, what would it take to do the same?*

It is not an editor guide, not a changelog and not a sales pitch. Every claim in it should be true
of the code as it exists today, because the reader may build on it.

## Workflow

### 1. Gather what's already written

- Find every doc that mentions the feature: `grep -ril "<feature name variants>" docs/ README* *.md`.
  Try several spellings (`program finder`, `program_finder`, `program-finder`, `ProgramFinder`).
- Sort the hits: **primary** (dedicated guides, plans, specs, ADRs) vs. **passing mentions**
  (test plans, release notes). Read the primary ones in full. Skim the passing ones for facts
  that aren't recorded anywhere else.
- Check project instructions (CLAUDE.md, AGENTS.md, CONTRIBUTING) for doc conventions: where docs
  live, whether `docs/` is gitignored, and whether some files must never be committed.

### 2. Verify against the code

The docs are claims; the code is the truth. Walk the feature end to end and record what you find:

- **Entry points and data flow.** Where data comes from, how it's transformed, where it's stored,
  how it's rendered, what triggers each step (cron, request, webhook, user action).
- **Config as deployed.** Read the config actually used (exported config, settings overrides per
  environment, feature flags), not only the defaults shipped in the module. Look for config that
  is ignored or overridden. A stale exported copy is a classic source of drift.
- **Numbers and limits.** Page sizes, schedules, caps, timeouts, retries, cache lifetimes and tags.
  These are what docs most often get wrong.
- **Non-obvious mechanisms.** Access rules, cache invalidation, failure handling, environment
  switches, anything a re-implementer would trip on.
- **Leftovers.** Unused fields, dead routes, legacy services, roles nobody needs. Outsiders will
  see them in the code and wonder.
- **Undocumented pieces.** Hooks in other modules, theme code, templates, JS, and placement in the
  page builder.

Keep a running **drift list**: each item is a doc claim, what the code actually does, and the
`file:line`.

### 3. Fix the source docs

Correct each existing doc in place. Change only what's wrong or missing and keep the author's voice.
Then append a dated "Code/doc drift found YYYY-MM-DD" section to the most technical doc. It should
list:
- what was corrected in the docs
- code-level issues found but **not** fixed (stale comments, missing tests, duplicated constants,
  fragile matching). Don't fix code as part of this task unless asked; report it instead.
- anything you couldn't verify (environment secrets, production state, external systems)

Don't commit unless asked, and follow the project's rules on what may be committed.

### 4. Write the study

Where to write it: if a first-party document connector is available (e.g. Claude Docs), create the
study there, so the user can share, comment on and export it. Otherwise write a Markdown file where
the project keeps docs, or wherever the user says. Build the outline first, then fill it one section
at a time.

Use this structure. Rename sections to fit, but keep the order: the reader decides whether to keep
reading after the first two sections.

1. **Summary.** One lead sentence saying what it is, where it lives and the core mechanism. Then
   an aspect/what-we-built table: platform, sources, sync or trigger, storage, front end, extras,
   rough size of the custom code (count it, e.g. `wc -l`).
2. **Does your use case match ours?** A fit-check table with columns *Question · Our answer ·
   If yours differs*. Cover where the data lives, prerequisites on the source side, content
   ownership, freshness needs, scale, interaction model, and hosting/auth. End with a one-line
   rule of thumb naming the rows that decide the match. This is the most valuable section, so make
   the "if yours differs" column practical.
3. **Architecture.** A diagram of components and data flow (direction, trigger, boundaries between
   systems) with one sentence of reading underneath. Draw it rather than describing it in prose
   whenever the tool allows.
4. **Implementation details.** Numbered subsections that follow the data flow (source → sync →
   storage → query/render → client → extras). Name real files, classes, hooks, services, config
   keys, cache tags and URLs. Readers will grep for them. Use tables for field and component
   inventories.
5. **Design decisions and why.** A table with columns *Decision · Alternative considered or tried ·
   Why*. Include approaches that were built and then removed. They are often the most useful
   lesson for outsiders.
6. **Accessibility, performance, and platform/multisite considerations.** What happens without
   JS, ARIA and keyboard behaviour, caching and request costs, per-environment differences,
   secrets handling.
7. **Editorial / operational workflow.** A table with columns *Task · Who · Where · When it takes
   effect*, plus the few things operators must know. Link the editor guide rather than repeating it.
8. **Replication guide.** Dependencies, steps for each side of the system, what to rename or
   parameterize, and "gotchas we hit" (each one a real problem found in the code or history).
9. **Known limitations and future work.** A table with columns *Area · Limitation · Possible next
   step*. Include the leftovers and fragile spots from the drift list.

## Writing rules

- **Facts only.** Every number, path and behaviour comes from the code, config or docs you read. If
  you didn't measure it (effort in weeks, scale thresholds, test coverage, AT testing), either
  leave it out or label it clearly as general guidance and point it out to the user in your reply.
  Don't invent percentages or estimates to make a section feel complete.
- **Don't commit the user to anything.** Don't write "happy to share our code", "we'll walk you
  through it" or offer timelines. Sharing code and time are the user's decisions; suggest such
  lines in chat instead.
- **Write for an outsider.** Spell out project jargon and internal acronyms the first time. Assume a
  competent developer on the same general platform, but not on this codebase.
- **Open each section with its point** ("We copy data into the aggregator rather than query it
  live"), not "This section describes…".
- **Pick the format that fits the content.** Use tables for inventories and comparisons, numbered
  lists for sequences, a diagram for flows, and prose only for reasoning. Keep sentences under
  about 25 words and avoid filler.
- **Voice.** Follow the project's authorship conventions (first person plural, the author's name,
  language). Keep all technical content in English unless told otherwise.
- **Sensitive details.** Never include credentials, secret paths with values, internal hostnames
  that aren't needed, or production data. Mention *that* secrets come from a secrets file, not
  what they are.

## Before handing off

Re-read the study as a sceptical outside developer would and check:
- Could they decide "match / no match" from the first two sections alone?
- Is every number traceable to a file you read?
- Is any sentence a promise, an estimate or a claim you didn't verify?
- Do the diagram's arrows run the right way, and do labels clear the lines? Look at the render
  if you can.

Then reply briefly with:
1. the study link or path
2. the doc corrections made, grouped by file
3. code issues found but left unfixed
4. anything the user should confirm or soften before sending, such as unverified claims,
   general-guidance lines and sharing decisions
