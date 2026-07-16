# Pandoc Citation/CSL Current-Base Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T045415Z`
Base: `e3e201377d66d62da0039dedbb153200e0a6e366`

## Scope

- Implemented bounded native CSL institution abbreviation lookup for literal organization names.
- When a CSL style renders `<institution institution-parts="short">` and the item has no explicit `short` value, `CitationCslProcessor` now consults the existing supplied CSL `institution` abbreviation map.
- Explicit item `short` values still take precedence, and missing abbreviations still fall back to the long literal organization name.
- Added a WordPress handoff example showing abbreviation-backed organization authors in citations and bibliography entries.

## Red-First Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3681 assertions, 0 failures`.
- After adding only the focused case, the same command failed as expected: `World Health Organization` rendered as `org WORLD HEALTH ORGANIZATION` instead of the supplied `org WHO` institution abbreviation.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3692 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-citation-csl-institution-abbreviation-handoff.php --self-test` -> `wordpress-citation-csl-institution-abbreviation-handoff self-test passed`.
- Final lint and whitespace checks are recorded in the worker final response.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CitationCslProcessor`, `CslStyle`, the existing bounded CSL abbreviation table, `MarkdownReader`, `WordPressBlockWriter`, and the focused PHP test runner. Full upstream Pandoc/citeproc parity remains a separate upstream-runner dependency task.

## Non-Overlap

This does not repeat the prior text-variable abbreviation handoff or the literal institution-part formatting handoff. Those accepted behaviors made short forms available for text variables and explicit literal `short` organization names; this slice bridges supplied CSL `institution` abbreviation lists into institution short-part rendering.
