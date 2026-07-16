# Malformed CMap Dangling Filter Name Boundary Current Base

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T010736Z`

Accepted base: `5ede50989da7cee0d4a9a04198c44e074eacbb0b`

## Source Truth

- MarkerPDF no-GPU scope: native searchable-PDF text extraction and CMap/filter parsing only.
- PDF dictionaries require key/value pairs; a resolved CMap `/Filter` followed by a dangling slash-name at dictionary close is a malformed filter boundary, not a safe ToUnicode stream.
- Upstream parity target is fail-closed CMap handling: preserve searchable page text through Identity-H/simple-font fallback and exclude corrupt CMap program payloads from WordPress paragraphs.

## Behavior Added

- Direct CMap stream filter: `/Length N /Filter /FlateDecode /DanglingFilterName >>` now rejects the dangling slash-name as a malformed extra filter operand before CMap decoding.
- Xref-selected indirect CMap filter: `/Length N /Filter 7 0 R /DanglingReferenceFilterName >>` with object `7 0 obj /FlateDecode endobj` now rejects the dangling slash-name before following the ToUnicode remap.
- Review metadata reports `reject_malformed_filter_operands`, `filter_resolution_failed`, zero decoded CMaps, the dangling name as `extra_filter_name`, and no Python/model or external PDF tool execution.

## Evidence

Red-first before source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDanglingFilterNameBoundaryCurrentBaseTest.php`

Result: `1 test files, 2 assertions, 2 failures`; both malformed fixtures decoded the CMap and leaked `Dangling ... CMap Leak` into extracted text.

After source/test/example edits:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapDanglingFilterNameBoundaryCurrentBaseTest.php`

Result: `1 test files, 126 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP stream-filter operand review, xref-selected object resolution, CMap decoding, and simple-font fallback paths. GPU/OCR/model execution remains intentionally out of scope.

## Non-Overlap

This does not repeat accepted duplicate `/Filter` declarations, indirect scalar extra decoder tokens, post-`DecodeParms` extra decoder tokens, post-`Length` decoder tokens, null-filter stale-length recovery, unsupported filters, Crypt identity/private filter handling, explicit EOD terminators, JSON/Pandoc/SQLite surfaces, or pdftext dictionary layout/order boundaries.

## Next Task

Continue native no-GPU markerPDF work around CMap stream filter/DecodeParms malformed operand boundaries, font encoding widths, xref repair, stream filters, metadata, annotations, forms, image/filter review, and supplied-boundary conversion handoffs.
