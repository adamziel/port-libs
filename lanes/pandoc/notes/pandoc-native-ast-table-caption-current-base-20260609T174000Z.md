# Pandoc Native AST Table Caption Current Base

## Status delta

- Added bounded `NativeReader` support for Pandoc native JSON `Table` nodes.
- Native table imports now expose long caption text, `captionBlocks`, single
  inline caption metadata, short caption text, and `shortCaptionInlines` on the
  shared `table` AST node while retaining the original native constructor for
  lossless `NativeWriter` round trips.
- The slice also maps table attrs, column alignments/widths, table sections,
  body `rowHeadColumns`, row/cell attrs, basic cell alignment, row/column spans,
  and simple cell block content so Markdown and WordPress table writers can
  consume the captioned native table shape.

## Focused evidence

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 file, 35 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 39 files, 56530 assertions, 0 failures.

## Scope and exclusions

This is a bounded native PHP AST handoff slice. It does not invoke Pandoc,
Cabal/Haskell runners, office suites, TeX/browser engines, zip/unzip, Jupyter,
Node tooling, or external validators. Full upstream runner parity remains
blocked on the previously recorded hydrated Pandoc checkout and Haskell/Cabal
runner closure.

## WordPress handoff

Native table captions now flow through existing WordPress table output as
`figcaption` content, preserving block-level caption provenance where present
and exposing short-caption metadata for review packets.
