# markerpdf stream-filter indirect Length boundary current-base slice

Session: `port-dev-markerpdf-stream-filter-stack-20260606T035616Z`
Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T035616Z`
Accepted base: `467a51a62878d82460d36533ed61e04c504f4164`

## Source truth

- Upstream markerPDF searchable-PDF import routes page content through PDFium/pdftext-like stream decoding before text-token parsing.
- Native no-GPU boundary for this lane: a stream `/Length` indirect helper may authorize filtered page bytes only when the selected helper object is a standalone non-negative integer. Helper objects with extra top-level operands are malformed and are rejected before WordPress text import.
- This avoids treating a plausible leading length token followed by decoy operands as safe stream ownership.

## Patch

- `PdfTextExtractor` now validates indirect `/Length` helpers before reading stream payloads.
- Direct length operands and valid indirect integer helpers continue to work, including comment-split indirect references already covered by the stream-filter stack suite.
- Malformed indirect length helpers fail closed for that stream, so later valid content streams remain importable and decoy bytes do not enter visible paragraphs.

## Evidence

- Red-first focused run before source edit:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`
  failed `rejects malformed indirect Length helper objects before filtered page text import`; actual lines included `Malformed Indirect Length Leak`.
- After source edit:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`
  passed: `1 test files, 324 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-stream-filter-indirect-length-boundary-currentbase.php`
  emits `malformed_indirect_length_rejected=true`, `visible_fallback_preserved=true`, `length_decoy_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF parser, indirect-object resolver, stream-filter decoder stack, and WordPress smoke harness. GPU/model execution, OCR, Surya/Texify/Torch, PDFium, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue non-overlapping native searchable-PDF work around stream filters, xref repair, fonts/CMaps, annotations/forms, image/filter metadata, and supplied-boundary table/equation handoffs.
