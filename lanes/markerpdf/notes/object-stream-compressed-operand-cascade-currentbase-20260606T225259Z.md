# Object Stream Compressed Operand Cascade - 2026-06-06

Slice: `markerpdf-object-stream-xref-parser-current-base-20260606T225259Z`

Accepted base: `72b74d8bf978910fedcbf4b3ed6fbaee9456d76b`

## Source Truth

PDF 1.5 object streams and xref streams can select compressed objects whose
dictionary operands participate in later object-stream expansion. This slice
keeps object-stream recovery iterative for metadata and embedded-file
extraction, matching the existing text-extraction boundary where helper object
stream members must be available before a dependent carrier stream is parsed.

## Behavior Added

- `PdfMetadataExtractor` now repeats xref-selected object-stream member
  expansion in bounded passes, so a carrier `/ObjStm` can recover `/N` and
  `/First` from compressed helper members before compressed catalog and Info
  objects are selected.
- `PdfEmbeddedFileExtractor` now uses the same bounded iterative expansion and
  preserves generation bookkeeping for recovered compressed FileSpec/name-tree
  members.
- The synthetic current-base PDF fixture stores carrier `/N` and `/First`
  operands as xref-selected compressed members in a helper object stream, then
  stores catalog, Info, EmbeddedFiles name tree, and FileSpec members in the
  dependent carrier stream.

## Evidence

- Red-first temporary-revert run:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCompressedOperandCascadeCurrentBaseTest.php`
  failed with `1 test files / 3 assertions / 1 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCompressedOperandCascadeCurrentBaseTest.php`
  passed with `1 test files / 29 assertions / 0 failures`.
- Broader xref/object-stream family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedSizeDefaultRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCarrierType2MetadataAttachmentCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPlusHeaderReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCompressedOperandCascadeCurrentBaseTest.php`
  passed with `7 test files / 180 assertions / 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-compressed-operand-cascade-currentbase.php`
  emitted compressed catalog metadata and attachment review while excluding
  embedded payload bytes from visible Gutenberg paragraph text.

## Non-Overlap

This does not repeat object-stream comment headers, plus headers, skipped-zero
rows, incomplete headers, filter chains, nested xref helper objects, or the
type-2 carrier-base review slice. The new boundary is metadata and embedded-file
extractor expansion when object-stream header operands are themselves current
xref-selected compressed members.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
xref stream, object stream, and Flate filter helpers. No Python, CUDA, OCR,
models, pypdfium, or external PDF tools were launched.
