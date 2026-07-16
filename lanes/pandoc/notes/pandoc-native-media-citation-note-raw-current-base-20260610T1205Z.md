# Pandoc Native Raw Citation Current Base

Slice: `pandoc-native-media-citation-note-raw-current-base-20260610T1205Z`

## Status delta

- `NativeReader` now maps Pandoc native JSON `RawBlock` constructors into
  shared raw block AST nodes, including HTML, TeX-like, Markdown-like, and
  generic raw block formats.
- Native inline `Cite` constructors now hydrate as shared citation and
  citation-group AST nodes while preserving the original native constructor
  payload for lossless source-native round trips.
- `NativeWriter` now emits generated shared raw blocks and
  citation/citation-group nodes as Pandoc native AST constructors when no
  preserved source-native constructor is attached.
- Existing WordPress and CSL paths can now consume native JSON raw HTML blocks
  and citation clusters without invoking Pandoc, citeproc, JSON filters,
  browser renderers, TeX engines, or external validators.

## Focused evidence

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 file, 188 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 59924 assertions, 0 failures.

## Accounting

- `phpPass` moves `2959 -> 2960`; `phpFail` remains `0`.
- Adds one focused native-AST constructor handoff check for
  raw-block/citation parity.
