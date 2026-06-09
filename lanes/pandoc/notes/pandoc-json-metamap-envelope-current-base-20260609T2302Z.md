# Pandoc JSON MetaMap Metadata Envelope Current Base

Slice: `pandoc-json-metamap-envelope-current-base-20260609T2302Z`

## Status delta

- `PandocJsonReader` now accepts document-level metadata encoded as a
  `MetaMap` constructor envelope and normalizes it into the same root metadata
  map used by direct Pandoc JSON filter packets.
- `PandocJsonWriter` now unwraps document `meta` attrs supplied as either a
  typed `map` value or a tagged `MetaMap` value, so it emits canonical root
  metadata entries instead of synthetic `type`/`items` or `t`/`c` metadata
  fields.
- Nested metadata values still flow through the existing tagged/simplified
  metadata normalization paths.

## Focused evidence

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 222 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57927 assertions, 0 failures.

## Accounting

- `lanes/pandoc/lane-status.json` `phpPass`: 2876 -> 2877.
- `lanes/pandoc/lane-status.json` `suiteProgress`: 779 -> 780.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: 3080 -> 3081.
- Added `mappedPandocJsonMetaMapEnvelopeCases: 1`.
- Added `pandocJsonMetaMapEnvelopeAssertions: 14`.

No Pandoc executable, JSON filter, Cabal solver/build/test command, Haskell
runner, browser renderer, external validator, online service, live provider
test, or live-service provider test was executed.
