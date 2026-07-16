# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T213430Z`
Base: `b321f6888e03ba16f542328dfc7cccbdbb2ef4a8`

## Behavior

- Added bounded native DOCX `w:ruby` parsing in `DocxReader`.
- Visible `w:rubyBase` content is imported through the existing inline parser, so run styling such as bold is preserved.
- `w:rt` pronunciation and `w:rubyPr` alignment/language/size fields are stored on a `.docx-ruby` span as `data-docx-ruby-*` metadata for Markdown and WordPress review output.

## Source Truth

- WordprocessingML stores ruby/phonetic annotations in `w:ruby`, with visible base text under `w:rubyBase`, annotation text under `w:rt`, and display metadata under `w:rubyPr`.
- This slice maps that OpenXML contract into the existing Pandoc-like AST without invoking Pandoc, Word, LibreOffice, zip/unzip, or external office tooling.
- The local upstream cache does not contain a hydrated Pandoc checkout for runner parity, so this remains bounded native support-library evidence.

## Verification

- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1466 assertions, 1 failures`; the new ruby case failed because `w:ruby` was ignored and the paragraph collapsed to one text node.
- Focused after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1483 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- Syntax and diff checks:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/DocxReader.php`.
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/DocxReaderTest.php`.
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-docx-body-handoff.php`.
  - `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: passed with no output.
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Adds `+1` focused DOCX/OpenXML PHP PASS case.
- Adds `+19` focused DocxReader assertions over the accepted baseline (`1464 -> 1483`).
- Updates mapped Pandoc inventory from `1533 -> 1534`.
- Updates DOCX/OpenXML mapped cases from `32 -> 33`.
- Updates lane `phpPass` from `1081 -> 1082`.

## Dependency Closure

No new support component is needed. This reuses `DocxReader`, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, `ZipPackage`, and OPC package helpers. Full Haskell runner parity still requires a hydrated Pandoc checkout and Cabal test runner dependency closure.

## Non-Overlap

This does not repeat accepted DOCX package loading, OPC relationships, styles/numbering, tables, media, VML/DrawingML images, chart/diagram placeholders, embedded objects, footnotes/endnotes/comments, note marker policies, comment ranges, bookmarks, fields, proof/permission ranges, content controls, smart tags, custom XML, tracked insert/delete/move/formatting changes, OMML math, altChunk, settings/document variables, section/header/footer metadata, symbol fonts, run language/RTL, paragraph bidi/layout, page/column/rendered page breaks, or ZIP trailing-deflate validation. It owns only bounded `w:ruby` base/pronunciation metadata handoff.

## Follow-Up

Keep glossary document parts, theme font inheritance, cross-paragraph proof/permission ranges, drawing text extraction, and fuller ruby layout fidelity as separate bounded DOCX/OpenXML slices.
