# Pandoc JSON Metadata T/C Map Compatibility Current Base

Slice: `pandoc-json-metadata-tc-map-compat-current-base-20260609T223316Z`

## Status delta

- Tightened `PandocJsonReader` metadata-constructor detection so only `Meta*`
  tags are treated as Pandoc metadata constructors.
- Simplified JSON metadata maps that happen to contain constructor-like `t` and
  `c` keys now remain literal `MetaMap` content instead of failing as an
  unsupported Pandoc constructor.
- Added native reader/writer round-trip coverage for nested simplified sidecar
  metadata with `t`/`c` keys.
- Moved lane accounting by one mapped check after rebasing over current main:
  `phpPass` `2889 -> 2890` and `suiteProgress` `792 -> 793`;
  `UPSTREAM_TEST_MANIFEST.json` mapped support moved `3086 -> 3087`.

## Focused evidence

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 245 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 58141 assertions, 0 failures.

## Scope and exclusions

This is a bounded native PHP JSON metadata compatibility slice. It does not
invoke Pandoc, JSON filters, Cabal/Haskell runners, office suites,
TeX/browser engines, zip/unzip, Jupyter, Node tooling, external validators, or
live services. Full upstream runner parity remains blocked on the previously
recorded hydrated Pandoc checkout and Haskell/Cabal runner closure.
