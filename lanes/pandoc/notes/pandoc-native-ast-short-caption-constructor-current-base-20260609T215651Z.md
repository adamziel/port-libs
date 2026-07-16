# Pandoc Native AST Short Caption Constructor Current Base

Slice: `pandoc-native-ast-short-caption-constructor-current-base-20260609T215651Z`

## Status delta

- Current main already contains the `NativeReader` constructor unwrapping path
  for Pandoc native JSON table short captions. This slice keeps distinct
  tuple-form coverage for explicit `ShortCaption` constructors whose content is
  wrapped as a single inline-list payload.
- Tuple-form constructor-wrapped short captions populate shared table
  `shortCaptionInlines` and scalar `shortCaption` metadata, flow into
  `TableGeometry` caption review packets, and remain visible to WordPress table
  review output through `data-pandoc-short-caption`.
- Lossless source-native round trips remain intact because preserved native
  constructors still take precedence in `NativeWriter`.

## Focused evidence

- Red-first focused run:
  `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - Failed the new constructor-wrapped short-caption case with
    `Pandoc native JSON inlines must be tagged constructors`.
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 file, 91 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 58291 assertions, 0 failures.
- Lane accounting moved from `phpPass` 2901 / `suiteProgress` 804 to
  `phpPass` 2902 / `suiteProgress` 805 on the current base.

## Scope and exclusions

This is a bounded native PHP AST handoff slice. It does not invoke Pandoc,
Cabal/Haskell runners, office suites, TeX/browser engines, zip/unzip, Jupyter,
Node tooling, JSON filters, external validators, online services, live provider
tests, or live-service provider tests. Full upstream runner parity remains
outside this slice.
