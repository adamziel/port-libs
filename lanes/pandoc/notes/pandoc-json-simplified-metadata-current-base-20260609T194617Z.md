# Pandoc JSON Simplified Metadata Current Base

Slice: `pandoc-json-simplified-metadata-current-base-20260609T194617Z`

## Status delta

- `PandocJsonReader` now accepts simplified JSON metadata values alongside
  canonical Pandoc `Meta*` constructors.
- Plain strings, numbers, and null values normalize into MetaString-compatible
  review values; booleans remain MetaBool-compatible.
- Nested plain JSON lists and objects normalize into the existing typed
  `list`/`map` metadata representation so `PandocJsonWriter` re-emits them as
  canonical `MetaList` and `MetaMap` constructors.
- Mixed metadata still preserves tagged `MetaInlines`, so canonical Pandoc JSON
  handoff remains unchanged.

## Focused evidence

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 189 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57102 assertions, 0 failures.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

## Scope and exclusions

This is a bounded native PHP JSON metadata compatibility slice. It does not
invoke Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/browser
engines, zip/unzip, Jupyter, Node tooling, external validators, online
services, live provider tests, or live-service provider tests.
