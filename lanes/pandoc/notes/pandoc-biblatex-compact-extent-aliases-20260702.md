# Pandoc BibLaTeX Compact Extent Aliases - 2026-07-02

Issue: `plib-tp70h`
Branch: `polecat/1762/plib-tp70h-csl-core`
Target: `integration/pandoc-semantics-csl`

## Summary

- Direct BibLaTeX parsing now treats `numberofvolumes`, `number-of-volumes`, `volume-count`, `volumecount`, `numvolumes`, and `num-volumes` as `number-of-volumes` aliases alongside `volumes`.
- Direct BibLaTeX parsing now treats `chapternumber` and `chapter-number` as `chapter-number` aliases alongside `chapter`.
- The focused regression keeps compact and hyphenated extent aliases visible through `CitationCslProcessor::bibtexItems()`, `CitationCslProcessor::fromBibtex()`, CSL variable rendering, and appended WordPress bibliography output.

## Validation

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` - 1 file, 6,164 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php` - 2 files, 7,414 assertions, 0 failures
- `git diff --check origin/integration/pandoc-semantics-csl...HEAD -- lanes/pandoc`
- Conflict-marker scan of changed lane files

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Typst, Node, zip/unzip, validators, or live services were invoked.
