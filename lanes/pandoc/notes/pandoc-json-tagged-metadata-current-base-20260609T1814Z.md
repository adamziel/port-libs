# Pandoc JSON Tagged Metadata Compatibility Slice

## Scope

This slice maps one bounded Pandoc JSON metadata compatibility case in native
PHP. `PandocJsonWriter` now accepts already-tagged Pandoc JSON metadata values
for `MetaString`, `MetaBool`, `MetaInlines`, `MetaBlocks`, `MetaList`, and
`MetaMap`, validates them through `PandocJsonReader`, and emits canonical
metadata constructors instead of treating the tagged object as an ordinary
metadata map.

## Evidence

- Focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: 1 file / 129 assertions / 0 failures
- Syntax checks:
  - `php -l lanes/pandoc/src/PandocJsonWriter.php`
  - `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Full Pandoc PHP gate:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 42 files / 56688 assertions / 0 failures

## Accounting

- `lanes/pandoc/lane-status.json` `phpPass`: 2808 -> 2809.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: 3035 -> 3036.
- Added `mappedPandocJsonTaggedMetadataCases: 1`.
- Added `pandocJsonTaggedMetadataAssertions: 11`.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, EPUBCheck, browser renderer, online service, live provider test, or
live-service provider test was executed.
