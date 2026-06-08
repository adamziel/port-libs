# DOCX/OpenXML Textbox Provenance Slice

Slice: `pandoc-docx-openxml-core-current-base-20260608T185849Z`
Base: `be1daac3955666cd7f4550d89b27b78d713e0ae0`
Date: 2026-06-08 UTC

## Source Truth

- Lane manifest source remains pinned to Pandoc upstream commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- The hydrated Pandoc upstream checkout was not present under `/home/claude/port-libs/.upstream-cache/pandoc` in this worker. No upstream Haskell runner, Cabal plan, Pandoc binary, Word, LibreOffice, zip/unzip, or external office tool was executed.
- This slice is bounded to native DOCX/OpenXML support already owned by `lanes/pandoc/src/DocxReader.php`: existing VML and DrawingML textbox extraction now carries source shape provenance into the Pandoc-like AST.

## Behavior

- VML `v:textbox` content extracted from `w:pict` is wrapped in a block `div` with `docx-textbox`, `docx-vml-textbox`, and source shape-kind classes.
- VML textbox metadata preserves bounded `data-docx-*` attributes for shape kind/id/alt/style and textbox inset/style/fitshape/insetmode when present.
- DrawingML `wps:txbx/w:txbxContent` extraction is wrapped in `docx-textbox docx-drawing-textbox` and preserves associated `wp:docPr` id/name/description/title metadata.
- Existing paragraph/table order is preserved: paragraph text before the textbox remains before the metadata wrapper, textbox paragraphs and tables remain visible inside the wrapper, and trailing paragraph text remains after it.
- WordPress output now exposes these wrappers as safe HTML blocks for reviewer provenance.

## Evidence

- `php -l lanes/pandoc/src/DocxReader.php` passed.
- `php -l lanes/pandoc/tests/DocxReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed: `1 test files, 2840 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed.

Prior DOCX note baseline was `DocxReaderTest.php` at `1 test files, 2807 assertions, 0 failures`; this patch adds 33 focused assertions inside existing DOCX PASS cases and one mapped DOCX/OpenXML manifest case.

## Non-Overlap

This does not repeat the accepted VML/DrawingML textbox extraction slices, which already made textbox content visible. The new behavior is textbox provenance metadata and downstream Markdown/WordPress wrapper preservation.

## Dependency Closure

No new support component is needed. The patch reuses existing native OPC/package loading, WordprocessingML textbox traversal, Pandoc-like `div` metadata, Markdown fenced-div rendering, and WordPress HTML block output. Full layout fidelity, Office rendering parity, external ZIP validation, Pandoc/Haskell runner parity, and external office-tool comparison remain out of scope for this micro-slice.

## Follow-Up

Choose a non-overlapping DOCX/OpenXML gap such as DrawingML shape geometry metadata, media relationship provenance beyond existing placeholders, or additional safe field-code review metadata.
