# Pandoc Citation/CSL Participant Name Variables Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260607T142135Z`
Base accepted HEAD: `a41b2fbbd4cb3f4632865fc8802044782b4e58ba`
Date: 2026-06-07 UTC

## Behavior

- Added bounded native CSL item normalization and rendering for standard CSL
  name variables `chair`, `collection-editor`, `composer`, `contributor`,
  `editor-translator`, and `recipient`.
- Preserved these role names through `cs:names` citation and bibliography
  layouts, default bibliography role summaries, and name annotation summaries.
- Allowed name-only CSL layouts to trigger custom rendering when they use
  non-default creator variables, while preserving the existing author/editor
  fallback path for default author-date citation output.
- Added a WordPress handoff example that keeps collection participant and
  recipient names visible in rendered citation clusters and bibliography
  entries.

## Source Truth

- CSL 1.0.2 lists these as standard name variables in Appendix IV: `chair`,
  `collection-editor`, `composer`, `contributor`, `editor-translator`, and
  `recipient`.
- Source: https://docs.citationstyles.org/en/v1.0.2/specification.html#appendix-iv-variables

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this
  lane before editing.
- Baseline focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2019 assertions, 0 failures`.
- Initial focused run after implementation and test addition:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 2026 assertions, 1 failures` because name-only
  non-author CSL layouts were still treated as default author-date fallback.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2032 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-participant-names-handoff.php --self-test`
  passed with `wordpress-citation-csl-participant-names-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1512 -> 1513`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1932 -> 1933`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `11 -> 12`.
- Focused citation coverage: one new PASS case and `+13` focused assertions in
  `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP `CslStyle` XML
layout parsing, `CitationCslProcessor` CSL item normalization and name
rendering, `MarkdownReader`, `WordPressBlockWriter`, and the focused lane PHP
harness.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, external
bibliography manager, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This does not repeat accepted CSL date-parts precision, extended locator
labels, locator range punctuation, page-range formatting, institution
short-parts, name child labels, et-al behavior, subsequent-author substitution,
choose match semantics, or BibTeX/BibLaTeX metadata handoffs. This patch owns
only direct CSL participant name variables and the bounded non-author
name-only layout activation needed to render them.
