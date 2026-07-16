# Pandoc Citation/CSL Current-Base Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T043140Z`
Base: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`

## Scope

- Implemented bounded native CSL bibliography sorting for note styles whose bibliography `<sort>` key uses `first-reference-note-number`.
- Reused existing note-position annotation and first-reference note-number collection; no external citeproc, Pandoc runner, BibTeX/Biber, Haskell binary, office tool, archive tool, renderer, validator, or online service is required.
- `first-reference-note-number` now participates in numeric bibliography sort evaluation and feeds citation-number assignment before citations are rendered.
- Added a WordPress handoff example showing first-note sorted bibliography entries and matching citation numbers in footnotes.

## Red-First Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 3655 assertions, 0 failures`.
- After adding only the new focused case, the same command failed as expected: bibliography entries stayed in fallback `Alpha/Middle/Zeta` order while the first-note order was `Zeta/Middle/Alpha`, and rendered citation numbers were `#3/#2/#1`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 3663 assertions, 0 failures`.
- `php -l lanes/pandoc/src/CitationCslProcessor.php` -> no syntax errors.
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` -> no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-citation-csl-note-sort-handoff.php` -> no syntax errors.
- `php lanes/pandoc/examples/wordpress-citation-csl-note-sort-handoff.php --self-test` -> `wordpress-citation-csl-note-sort-handoff self-test passed`.
- `git diff --check -- lanes/pandoc` -> passed.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `MarkdownReader`, `CitationCslProcessor`, `CslStyle`, and `WordPressBlockWriter` behavior plus focused `CitationCslProcessorTest.php` coverage. Full upstream Pandoc/citeproc parity remains a separate upstream-runner dependency task.

## Non-Overlap

This does not repeat the prior note-style handoff that rendered `first-reference-note-number` in citations and bibliographies. That accepted behavior made the variable visible; this slice makes it sort-affecting and citation-number-affecting for bibliography order.
