# Pandoc JSON Table Caption Current Base

Slice: `pandoc-json-table-caption-current-base-20260609T183059Z`

## Status delta

- Added bounded `PandocJsonReader` support for Pandoc JSON `Table` block
  constructors.
- `Table` nodes now map attrs, long caption blocks/inlines, short caption
  inlines, column alignments/widths, table head/body/foot sections,
  `rowHeadColumns`, row/cell attrs, cell alignment, row spans, column spans,
  and simple cell content into the shared table AST.
- Added matching `PandocJsonWriter` output for the shared table AST so JSON
  filter packets can round-trip captioned table structures without falling
  back to external Pandoc, JSON filters, browser engines, or validators.
- WordPress table output now receives Pandoc JSON long captions as figcaption
  content and short captions as review metadata through the existing table
  writer path.

## Focused evidence

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file, 164 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 56785 assertions, 0 failures.

## Scope and exclusions

This is a bounded native PHP JSON handoff slice. It does not invoke Pandoc,
Cabal/Haskell runners, office suites, TeX/browser engines, zip/unzip, Jupyter,
Node tooling, external validators, or live services. Full upstream runner
parity remains blocked on the previously recorded hydrated Pandoc checkout and
Haskell/Cabal runner closure.
