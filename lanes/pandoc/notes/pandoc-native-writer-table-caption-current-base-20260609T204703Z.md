# Pandoc Native Writer Table Caption Current Base

Slice: `pandoc-native-writer-table-caption-current-base-20260609T204703Z`

## Status delta

- Extended bounded `NativeWriter` support for shared AST `table` nodes on the
  current accepted table writer base.
- Shared tables now serialize to Pandoc native `Table` constructors with attrs,
  long caption blocks, short caption inlines, column alignments/widths,
  table head/body/foot sections, body `rowHeadColumns`, cell alignment, and
  row/column spans.
- Text-only shared AST table cells that carry a `text` attribute now serialize
  as native `Plain` cell blocks instead of losing their visible text.
- The existing native-constructor preservation path remains unchanged: tables
  read from native JSON still round-trip through their preserved constructor.

## Focused evidence

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 file, 118 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 58719 assertions, 0 failures after rebasing on `origin/main`.

## Scope and exclusions

This is a bounded native PHP AST writer slice. It does not invoke Pandoc,
JSON filters, Cabal/Haskell runners, office suites, TeX/browser engines,
zip/unzip, Jupyter, Node tooling, external validators, or live services.
Full upstream runner parity remains blocked on the previously recorded hydrated
Pandoc checkout and Haskell/Cabal runner closure.

## WordPress handoff

Generated native `Table` constructors now read back into the shared table AST
with caption provenance intact, so existing WordPress table output receives
long captions as `figcaption` content and short captions as review metadata.
