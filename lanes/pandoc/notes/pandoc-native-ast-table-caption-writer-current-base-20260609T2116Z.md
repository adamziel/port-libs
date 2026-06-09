# Pandoc Native AST Table Caption Writer Current Base

Slice: `pandoc-native-ast-table-caption-writer-current-base-20260609T2116Z`

## Status delta

- Added bounded `NativeWriter` support for generated shared `table` AST nodes.
- Shared tables now emit Pandoc native JSON `Table` constructors with attrs,
  long caption inlines or caption blocks, short caption inlines, column
  alignments/widths, table head/body/foot sections, body `rowHeadColumns`,
  row/cell attrs, basic cell alignment, row/column spans, and simple cell
  content.
- Generated native AST packets now read back through `NativeReader` with long
  and short caption metadata intact instead of requiring a preserved source
  `native` constructor.

## Focused evidence

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 file, 49 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57353 assertions, 0 failures.

## Scope and exclusions

This is a bounded native PHP AST writer slice. It does not invoke Pandoc,
Cabal/Haskell runners, office suites, TeX/browser engines, zip/unzip, Jupyter,
Node tooling, JSON filters, external validators, or live services. Full
upstream runner parity remains blocked on the previously recorded hydrated
Pandoc checkout and Haskell/Cabal runner closure.
