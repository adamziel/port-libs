# Pandoc Citation/CSL Current-Base Duplicate Handoff

Slice: `pandoc-citation-csl-core-current-base-duplicate-20260609T051012Z`
Base: `516b4c2368ab923eeb7c71f762618468a7a4d437`

## Scope

- Added bounded English long-label plural terms for secondary CSL creator name variables: `redactor`, `founder`, `continuator`, `reviser`, `collaborator`, `commentator`, `annotator`, `introduction`, `foreword`, and `afterword`.
- This keeps `cs:label` output pluralized for contextual and forced-plural secondary creator labels instead of falling back to the singular variable name.
- Added a WordPress handoff example that renders plural secondary creator labels in both citation and bibliography review packets.

## Red-First Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3719 assertions, 0 failures`.
- After adding only the focused secondary-creator label test, the same command failed as expected: plural commentator/annotator/redactor/collaborator labels rendered as singular fallback terms.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3731 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-citation-csl-secondary-creator-label-handoff.php --self-test` -> `wordpress-citation-csl-secondary-creator-label-handoff self-test passed`.
- Final lint and whitespace checks are recorded in the worker final response.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle` term resolution, `CitationCslProcessor` name rendering, `MarkdownReader`, `WordPressBlockWriter`, and the focused PHP test runner. Full upstream Pandoc/citeproc runner parity remains a separate upstream-runner dependency task.

## Non-Overlap

This does not repeat the recent institution-abbreviation, text-variable abbreviation, static authority, audiovisual creator label, or editorial creator label handoffs. Those covered abbreviation lookup, authority names, media/editorial role terms, and explicit institution short parts; this slice only fills missing long plural defaults for already-normalized secondary creator name variables.
