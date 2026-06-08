# pandoc-docx-openxml-core-current-base-20260608T200513Z

## Scope

- Lane: `pandoc`
- Base accepted HEAD: `e8d89b1bca7d948de85077feddadfe6b141d5ed7`
- Upstream behavior cluster: bounded DOCX/OpenXML DrawingML geometry metadata handoff for `wp:inline` and `wp:anchor` containers.

## Source Truth

Pandoc DOCX readers preserve source document structure through the Pandoc AST rather than invoking Word/LibreOffice. This slice ports the bounded contract needed by the PHP reader: inline and anchored DrawingML containers expose reviewer metadata for extent, effect extent, distances, wrap mode, anchor flags, and horizontal/vertical positioning while existing image/chart/text handoff nodes remain native PHP AST nodes.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was executed.

## Implementation

- `DocxReader` now locates the nearest `wp:inline`/`wp:anchor` container for DrawingML blips, chart references, diagram relationships, and DrawingML text bodies.
- Geometry metadata is emitted only when the container has real geometry data, so existing `docPr`-only drawings keep their current AST shape.
- Geometry classes and `data-docx-*` attributes flow through Markdown attributes and WordPress image/span attributes.
- The DOCX body handoff example now includes a geometry-bearing inline hero image in its self-test path.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 2952 assertions, 0 failures`
- Red-first with only the new geometry test:
  - `1 test files, 2961 assertions, 1 failures`
  - Failure: image AST nodes lacked expected DrawingML geometry classes/attributes.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 3016 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - `docx body handoff self-test ok`

## Non-Overlap

This does not repeat accepted DOCX work for media relationship resolution, VML images/textboxes, chart/diagram placeholders, DrawingML text extraction, caption grouping, tracked changes, fields, content controls, settings, OLE/package placeholders, table geometry, section geometry, or altChunk import. It only adds the missing DrawingML container geometry metadata layer to existing DOCX body handoff nodes.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP `ZipPackage`, OPC relationship resolution, DOCX reader traversal, Markdown writer attributes, and WordPress block writer attributes. Full upstream runner parity remains out of scope for this isolated micro-slice.

## Next Task

For DOCX/OpenXML follow-up, choose a non-overlapping native package/body mapping gap such as DrawingML transform/crop metadata, style-driven drawing defaults, comments in drawing text, or remaining WordprocessingML review metadata.
