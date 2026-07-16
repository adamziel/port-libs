# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T220447Z`
Base: `0767b5e8420e6daf3987ebf609a811fb16a2a427`

## Behavior

- Added bounded native DOCX `glossaryDocument` relationship support in `DocxReader`.
- The reader validates the in-package glossary relationship/content type, reports glossary document-part metadata under `metadata.docxGlossary` and `importReport.glossary`, and parses `w:docPartBody` through the existing WordprocessingML body parser for reviewer text.
- `w:docPartObj` and `w:docPartList` content controls now preserve gallery, category, unique, kind, and placeholder metadata on Markdown and WordPress handoff nodes.

## Source Truth

- WordprocessingML stores reusable building blocks in a glossary document part reached from the main document relationship type `http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument`.
- `w:docPart` entries carry `w:docPartPr` metadata such as name, style, category/gallery, types, description, and guid, with visible fallback content in `w:docPartBody`.
- Content controls can reference those building blocks through `w:docPartObj` and `w:docPartList`; this slice maps that bounded OpenXML contract into the existing Pandoc-like AST without invoking Pandoc, Word, LibreOffice, zip/unzip, or external office tooling.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1483 assertions, 0 failures`.
- Focused after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 1549 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Adds `+1` focused DOCX/OpenXML PHP PASS case.
- Adds `+66` focused DocxReader assertions over the accepted baseline (`1483 -> 1549`).
- Updates mapped Pandoc inventory from `1543 -> 1544`.
- Updates DOCX/OpenXML mapped cases from `32 -> 33`.
- Updates lane `phpPass` from `1091 -> 1092`.

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage`, OPC relationship/content-type helpers, `DocxReader` WordprocessingML body parsing, `MarkdownWriter`, and `WordPressBlockWriter`. Full upstream runner parity still requires a hydrated Pandoc checkout and Cabal test runner dependency closure.

## Non-Overlap

This does not repeat accepted DOCX package loading, OPC relationships, styles/numbering, tables, media, VML/DrawingML images, chart/diagram placeholders, embedded objects, footnotes/endnotes/comments, note marker policies, comment ranges, bookmarks, fields, proof/permission ranges, generic content controls, smart tags, custom XML, tracked insert/delete/move/formatting changes, OMML math, altChunk, settings/document variables, section/header/footer metadata, symbol fonts, run language/RTL, paragraph bidi/layout, page/column/rendered page breaks, ruby annotations, or ZIP trailing-deflate validation. It owns only bounded glossary document-part metadata and docPart content-control handoff.

## Follow-Up

Keep glossary relationship error cases, glossary-local media/note relationships, theme font inheritance, drawing text extraction, and fuller Word building-block gallery semantics as separate bounded DOCX/OpenXML slices.
