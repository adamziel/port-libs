# Pandoc JSON MetaMap Metadata Current Base

Slice: `pandoc-json-metamap-metadata-current-base-20260609T214500Z`

## Summary

- Current `main` already accepted document-level metadata serialized as a
  root `MetaMap` constructor; this slice keeps that behavior and adds the same
  shape inside legacy `unMeta` envelopes.
- Simplified JSON metadata records whose ordinary fields are named `t` and
  `c` are preserved as literal `MetaMap` review data unless `t` is one of the
  supported Pandoc `Meta*` constructors.
- Known `MetaString`, `MetaBool`, `MetaInlines`, `MetaBlocks`, `MetaList`,
  and `MetaMap` values remain strict constructor inputs for native writer
  exchange.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: 1 file, 263 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 42 files, 58390 assertions, 0 failures after rebasing on current
    `origin/main`.

## Accounting

- `lane-status.json` `phpPass`: `2904` to `2905`.
- `lane-status.json` `suiteProgress`: `807` to `808`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3093` to `3094`.
- Added `mappedPandocJsonMetamapMetadataCases: 1` and
  `pandocJsonMetamapMetadataAssertions: 18`.

## Scope

This is a bounded native PHP JSON metadata compatibility slice. It does not
invoke Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, office
suites, external validators, online services, live provider tests, or
live-service provider tests.
