# Pandoc Citation Locator Raw-Label Mismatch Diagnostics

Implemented one bounded native PHP Citation/CSL diagnostics slice for direct
AST citation nodes that carry `locatorLabel` metadata alongside raw unlabeled
`locator` text, but do not carry a matching `locatorValue`.

## Behavior

- `CitationCslProcessor::citationLocatorDiagnostics()` preserves
  `rawLocatorLabel` consistently in locator diagnostic packets.
- Direct AST citations such as `locatorLabel=chapter` with raw `locator=4`
  now emit `citation-locator-label-without-explicit-value` before the existing
  unlabeled page fallback diagnostic.
- Rendered citation output is unchanged; the slice only improves review
  diagnostics for lossy locator handoff.
- Inferred raw locators such as `chap. 4` do not receive the warning because
  the raw locator text already carries the effective locator label.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: 1 test file, 4170 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 42 test files, 58081 assertions, 0 failures.

Status delta after rebase: `phpPass` moves from `2885` to `2886`; mapped
focused checks move from `788` to `789`. `UPSTREAM_TEST_MANIFEST.json` mapped
denominator moves from `3083` to `3084`.
