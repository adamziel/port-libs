# Citation/CSL Camel Alias Direct-Item Handoff

Slice: `pandoc-citation-csl-core-current-base-duplicate-20260609T061650Z`
Base accepted HEAD: `54e4f08a09f2e83c9a94575366cb4582953b41b9`

## Scope

Implemented a bounded Citation/CSL direct-item normalization fix for PHP callers
that supply common camelCase metadata keys. `CitationCslProcessor` now maps:

- `titleAddon` / `titleaddon` to CSL `title-addon`
- `containerTitle` / `containertitle` to CSL `container-title`
- `containerTitleAddon` / `containertitleaddon` to CSL `container-title-addon`
- `publisherPlace` to CSL `publisher-place`

The focused test renders those fields through canonical hyphenated CSL variables
in a citation, bibliography entry, and WordPress block handoff.

## Evidence

Baseline before editing:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3832 assertions, 0 failures
```

Red-first after adding the focused test and example, before the normalizer fix:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL renders bounded csl camelcase title and publication aliases from direct items
Expected: 'review copy'
Actual: ''
1 test files, 3835 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3848 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/pandoc/examples/wordpress-citation-csl-camel-alias-handoff.php --self-test
wordpress-citation-csl-camel-alias-handoff self-test passed
```

Additional verification run before handoff:

```text
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-citation-csl-camel-alias-handoff.php
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/pandoc
```

## Status Delta

- `phpPass`: `2435` to `2436`
- `benchmarkDenominator.mapped`: `2824` to `2825`
- Focused assertion count: `3832` baseline to `3848` final, a net `+16`

## Dependency Closure

No new support component is needed. This reuses the native CSL item normalizer,
style parser, Markdown reader, and WordPress block writer. Upstream Pandoc or
citeproc runner parity remains out of scope for this isolated support-library
slice.

## Non-overlap

This slice does not repeat the latest accepted page-range locator formatting,
creator-label, static-authority, section/supplement number, source-variable, or
BibTeX entry-type mapping work. It targets direct PHP item alias normalization
for canonical CSL rendering only.

## Exclusions

Did not run Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip,
external template engines, external converters, TeX/PDF engines, browser
renderers, online services, live provider tests, or the no-argument root PHP
harness.
