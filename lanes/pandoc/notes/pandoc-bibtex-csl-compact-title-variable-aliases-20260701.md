# Pandoc BibTeX/CSL Compact Title Variable Aliases

Date: 2026-07-01
Issue: plib-65vt7

## Summary

`CitationCslProcessor` now renders and sorts already-normalized BibLaTeX title
family metadata when bounded CSL styles use compact BibLaTeX variable spellings:

- `reviewtitle` for `reviewed-title`
- `maintitleaddon` for `main-title-addon`
- `shortvolumetitle` / `short-volume-title` for `volume-title-short`
- `shortseries` / `short-series` / `shortcollection` / `short-collection` for
  `collection-title-short`

The parser and legacy BibTeX handoff already preserved these values. This slice
only closes the native CSL renderer/sort alias path so custom styles and
WordPress bibliography output do not drop the normalized metadata.

## Evidence

Red-first focused run after adding the regression:

- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1 test files, 957 assertions, 1 failures`
  - compact title variables rendered as `[Ace; Zed]` instead of the normalized
    reviewed-title, main-title-addon, volume-title-short, and short-series
    values.

Green focused verification on `integration/pandoc-semantics-csl` after the fix:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1 test files, 1267 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 6136 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
  - `1 test files, 56 assertions, 0 failures`
- PHP JSON parse for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
- `git diff --cached --check origin/integration/pandoc-semantics-csl -- lanes/pandoc`
- conflict-marker scan across the changed lane files

## Non-Overlap

This does not repeat the accepted BibTeX title-family preservation,
review-title hierarchy, short-series metadata, direct CSL title-family alias,
date, relation, source-file, identifier, package-reader, or full citeproc parity
slices. It only exposes compact BibLaTeX variable names through the existing
native PHP CSL text rendering and sorting paths. Direct-format parity remains
active for broader Pandoc support, and no Pandoc, citeproc, BibTeX, Biber,
office suite, TeX/browser engine, Typst, Node, zip/unzip, external validator,
online service, or live provider was invoked.
