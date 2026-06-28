# Pandoc Citation/CSL Locator Source Metadata Slice

## Scope

This slice refines native PHP citation locator diagnostics for direct AST
citations that provide `locatorValue` without an explicit `locatorLabel`.
Those citations still render as page locators and still carry the existing
`citation-locator-explicit-value-defaulted-page` diagnostic, but the
`citation-locator-source` review variable and normalized `cslLocator`
metadata now report `defaulted` instead of `explicit`.

Direct AST citations that intentionally provide both `locatorLabel="page"` and
`locatorValue` remain classified as `explicit`, so importers can distinguish a
deliberately labeled page locator from an unlabeled value that defaulted to
CSL `page`.

## Files

- `lanes/pandoc/src/CitationCslProcessor.php`
- `lanes/pandoc/tests/CitationCslProcessorTest.php`
- `lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php`
- `lanes/pandoc/lane-status.json`

## Verification

Focused checks:

```bash
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
php lanes/pandoc/examples/wordpress-citation-csl-locator-diagnostics-handoff.php --self-test
```

Result: `CitationCslProcessorTest.php` passed `1` file, `6089`
assertions, `0` failures. The locator diagnostics handoff self-test passed.

Broad lane gate:

```bash
php tools/run-tests.php lanes/pandoc/tests
```

Result: still baseline-red with `295` files, `117054` assertions, `9781`
failures. The visible failures are in `YamlMetadataReviewTest.php`, outside
this Citation/CSL locator slice.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice,
TeX/PDF engine, browser renderer, unzip/zip, external validator, online
service, live provider test, or live-service provider test is required.
