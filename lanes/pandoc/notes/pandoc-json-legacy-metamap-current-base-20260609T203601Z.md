# Pandoc JSON Legacy MetaMap Current Base

Slice: `pandoc-json-legacy-metamap-current-base-20260609T203601Z`

## Status delta

- `PandocJsonReader` now accepts legacy nested `MetaMap` payloads whose content
  is wrapped as `{"unMeta": {...}}`.
- The wrapper is normalized only for `MetaMap` constructor content, so ordinary
  simplified metadata maps keep their existing literal object behavior.
- `PandocJsonWriter` also canonicalizes pre-tagged legacy `MetaMap` metadata
  because tagged metadata is validated through the reader before emission.
- Re-emitted packets use canonical `MetaMap` content without the legacy
  `unMeta` wrapper.

## Focused evidence

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 305 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 58774 assertions, 0 failures.

## Accounting

- `lanes/pandoc/lane-status.json` `phpPass`: `2930 -> 2931`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator:
  `3109 -> 3110`.
- Focused JSON metadata coverage: +1 PASS case, +17 assertions.

## Scope and exclusions

This is a bounded native PHP JSON metadata compatibility slice. It does not
invoke Pandoc, JSON filters, Cabal/Haskell runners, TeX/browser engines,
office suites, zip/unzip, Jupyter, Node tooling, external validators, online
services, live provider tests, or live-service provider tests.
