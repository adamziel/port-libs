# Pandoc BibTeX/CSL Current-Base Handoff - 2026-06-05T16:39:41Z

Base accepted HEAD: `5461f13312d11b720990563e5f589783adb6e304`

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260605T163941Z`

## Behavior

- Added bounded CSL locale-term handoff for BibLaTeX event fallback bibliography labels.
- `CslStyle` now carries default terms for `event`, `event-title-addon`, `event-type`, `event-organizer`, `event-place`, and `event-date` that preserve the existing English fallback output.
- `CitationCslProcessor` now renders BibLaTeX event bibliography labels through those CSL terms, so inline locale terms can relabel inherited `eventtitle`, `eventtitleaddon`, `eventtype`, `eventorganizer`, `venue`, and `eventdate` metadata without external citeproc.
- Updated the WordPress BibTeX/CSL example smoke with a localized event-label review path.

## Evidence

Baseline before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1289 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1304 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Syntax checks:

```text
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/src/CslStyle.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php
```

All reported no syntax errors.

## Status Delta

- `lane-status.json` `phpPass`: `1002 -> 1003`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1457 -> 1458`.
- `mappedBibtexCslCoreCases`: `2 -> 3`.
- `bibtexCslCoreAssertions`: `38 -> 53`.
- Focused Citation/CSL assertions: `1289 -> 1304` (`+15`).

## Dependency Closure

No new support component is needed. This slice reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and `WordPressBlockWriter` paths. No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, external bibliography manager, online service, or renderer was executed.

The upstream-runner dependency blocker remains unchanged: full upstream Pandoc runner parity still needs a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus Haskell Tasty executable builds for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This is a BibTeX/CSL event-label localization slice. It does not touch the previous ZIP comment-policy, ODF text:tab, charset, DOCX, PDF, table geometry, doctemplate, YAML, XML/HTML5 DOM, or upstream-runner dependency-audit surfaces.

Root harness: not run - isolated micro-slice.
