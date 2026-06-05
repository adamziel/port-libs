# DOCX/OpenXML DrawingML Text Boxes

Slice: `pandoc-docx-openxml-core-current-base-20260605T223304Z`
Base accepted HEAD: `6ef3a89a7bcb2f075adfe9a86e43278f02288697`

## Behavior

This slice adds bounded native PHP DOCX/OpenXML support for WordprocessingShape
text boxes embedded as DrawingML:

- `w:drawing` containing `wps:txbx/w:txbxContent` is now unwrapped into the
  document body in run order.
- The existing body parser handles the unwrapped `w:p` and `w:tbl` children, so
  paragraph, table geometry, Markdown, and WordPress block handoff behavior
  stays shared with the accepted VML text-box path.
- The WordprocessingShape namespace is treated as a supported markup
  compatibility namespace for bounded DOCX text-box extraction.

## Red-First Evidence

Before the reader change, the new focused case failed because DrawingML text-box
content was ignored and only the host paragraph was emitted:

`php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`

Result before implementation: `1 test files, 1550 assertions, 1 failures`.

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`

Result: `1 test files, 1563 assertions, 0 failures`.

Focused delta: `+1` PASS case and `+14` assertions.

## Non-Overlap

This does not repeat accepted VML text-box handling, alternate-content fallback
selection, drawing image metadata, chart/SmartArt placeholders, tracked
formatting changes, glossary parsing, or archive/OPC package primitives. It is
limited to direct DrawingML WordprocessingShape text-box body extraction.

## Dependency Closure

No new support component is needed. The slice reuses existing native
`ZipPackage`, OPC XML loading, and `DocxReader` body parsing. No Pandoc, Cabal
build, Haskell runner, Word, LibreOffice, zip/unzip, external office tool,
browser renderer, online sanitizer, or online service was executed.

## Follow-Up

Keep generic DrawingML `a:txBody` extraction outside WordprocessingShape text
boxes, glossary-local relationship handling, theme font inheritance, chart and
SmartArt semantic extraction, and full upstream-runner dependency planning as
separate bounded slices.
