# Pandoc Citation/CSL Current-Base Names Label Slice

Micro-slice: `pandoc-citation-csl-core-current-base-20260607T022456Z`
Base accepted HEAD: `dceb129b94af76d8e90cb1d4f15360a8db272ff2`

## Behavior

- Added bounded native support for direct `cs:label` children of `cs:names`.
- Preserves names-label metadata for before/after `cs:name` position, `long`, `short`, `verb`, `verb-short`, and `symbol` forms, contextual/always/never plural policy, affixes, text case, and period stripping.
- Renders editor and translator role labels against the creator variable that actually supplied names from a multi-variable `names` element.
- Keeps standalone `cs:label` validation unchanged: standalone label forms remain limited to `long`, `short`, and `symbol`.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1850 assertions, 0 failures`.
- Red-first: the new names-label case failed because `nameRendering.label` metadata was absent.
- Final: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 1868 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-name-label-handoff.php --self-test` passed.
- Syntax checks passed for changed PHP files:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-name-label-handoff.php`
- JSON status/manifest parse check passed.
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1435 -> 1436`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1852 -> 1853`.
- Focused test growth: `+1` PHP PASS case and `+18` focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle` XML parsing, `CitationCslProcessor` name rendering, `MarkdownReader`, `WordPressBlockWriter`, and the focused lane PHP harness. No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the recent citation/CSL institution short-parts, subsequent-author, et-al, locator/page label, number rendering, display parts, or date conditional slices. It covers the remaining names-child label role-term path only.
