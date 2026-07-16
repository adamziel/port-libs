# Pandoc ODF OpenDocument Core Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260604T231523Z`
- Accepted base: `fd0f5327abfd3b58715219a1c13c4c8295941253`
- Rework notes: no `port-pandoc-*.needs-lane-rework.md` file was present before editing.

## Implementation

Added a bounded native `OdfReader` for OpenDocument Text packages. The reader validates the root ODT mimetype entry, reads `META-INF/manifest.xml`, `content.xml`, `styles.xml`, and `meta.xml`, and maps the supported package state into the existing Pandoc-like AST plus an import report.

Covered behavior:

- Manifest `file-entry` inventory with package part existence, media types, byte lengths, CRCs, root mimetype/version, and missing-media diagnostics.
- `meta.xml` Dublin Core and ODF metadata including title, creator, language, dates, keywords, user-defined metadata, and document statistics.
- `styles.xml` plus `content.xml` automatic style parsing with parent text-style inheritance, list styles, table column widths, and style metadata.
- `content.xml` headings, paragraphs, links, spaces, line breaks, ordered/bullet lists, sections, inline annotations as notes, text boxes, frame images, and table header/body rows with colspan/rowspan attributes.
- Markdown and WordPress block rendering through existing `MarkdownWriter` and `WordPressBlockWriter` without invoking Pandoc, LibreOffice, Word, zip/unzip, or online services.

Added `examples/wordpress-odf-open-document-handoff.php --self-test` to exercise the WordPress review path from generated ODT package bytes to rendered WordPress blocks.

## Evidence

- `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: 1 test file, 105 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 14 test files, 3,803 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`: `odf open document handoff self-test ok`.
- `git diff --check -- lanes/pandoc`: no whitespace errors.

Root harness was not run; this is an isolated Pandoc micro-slice.

## Status Delta

- `phpPass`: 389 -> 394.
- `benchmarkDenominator.mapped`: 846 -> 851.
- `odfOpenDocumentCoreCases`: 10 -> 15.
- `odfOpenDocumentCoreAssertions`: 217 -> 322.

## Dependency Closure

No new support component is needed. This slice reuses the existing native `ZipPackage`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` components. Full upstream Pandoc runner parity remains blocked on hydrating/building the Haskell Pandoc checkout at the manifest commit, but ODT-local native parsing is not blocked by that runner.

## Non-Overlap / Exclusions

This slice is limited to ODT package/core OpenDocument XML mapping. It does not attempt DOCX, EPUB3, BibTeX/CSL, legacy DOC/CFB, PDF engine, archive compression, or table-geometry helper changes outside the ODF reader tests.

ODT follow-up remains separate for tracked changes, footnotes/endnotes, bookmarks, formulas, charts, linked sections, encrypted package preflight, forms, richer style cascades, and full Pandoc ODT reader parity.
