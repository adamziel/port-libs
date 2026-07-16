# Pandoc Native AST Table Caption Constructors Current Base

Slice: `pandoc-native-ast-table-caption-constructors-current-base-20260609T2232Z`

## Status delta

- Added bounded `NativeReader` compatibility for constructor-wrapped Pandoc
  native JSON table captions.
- Native table imports now accept tagged `Caption` values with `Just`-wrapped
  `ShortCaption` payloads, including the single-list short-caption wrapper shape,
  while preserving multi-block long captions as `captionBlocks`.
- Review handoff keeps long-caption block metadata, formatted short-caption
  inline metadata, WordPress figcaption rendering, and original source-native
  constructor round trips without invoking Pandoc or JSON filters.

## Focused evidence

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 file, 64 assertions, 0 failures.
- `git diff --check -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57658 assertions, 0 failures.

## Scope and exclusions

This is a bounded native PHP AST reader compatibility slice. It does not invoke
Pandoc, Cabal/Haskell runners, office suites, TeX/browser engines, zip/unzip,
Jupyter, Node tooling, JSON filters, external validators, or live services.
Full upstream runner parity remains blocked on the previously recorded
Haskell/Cabal hydration constraints.
