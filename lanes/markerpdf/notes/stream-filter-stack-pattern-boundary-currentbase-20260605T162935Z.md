# Stream Filter Stack Pattern Boundary Current Base

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T162935Z`

Source truth:
- Upstream markerPDF native parser/converter behavior expects filtered PDF stream payloads to be decoded before visible-content or review traversal.
- PDF stream filters with explicit end markers must not accept raw non-whitespace bytes after the filter EOD as part of a valid bounded stream payload.

Implemented behavior:
- `decodedTilingPatternBody()` now requires bounded explicit filter end markers when decoding PatternType 1 tiling pattern streams.
- A malformed ASCIIHex tiling pattern stream with raw bytes after `>` now fails closed for image-review traversal.
- A well-bounded ASCIIHex tiling pattern stream with only trailing whitespace still decodes and preserves the invoked image XObject as review-only metadata.
- Visible WordPress text remains limited to the page text; image payload text and malformed trailing pattern resources stay out of Gutenberg paragraphs.

Focused evidence:
- Red-first before source edit:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`
  failed the new pattern-boundary case with `Expected: 1`, `Actual: 3`.
- After source edit:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`
  passed with `1 test files, 243 assertions, 0 failures`.
- Related image-review traversal check:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`
  passed with `2 test files, 1146 assertions, 0 failures`.

WordPress smoke:
- Added `lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-pattern-boundary-currentbase.php`.
- The smoke emits `malformed_pattern_filter_stack_rejected=true`, `raw_trailing_pattern_payload_excluded=true`, `safe_pattern_filter_stack_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:
- No new support component is needed.
- The patch reuses native PHP stream filter decoding, PatternType 1 resource traversal, and image XObject review metadata paths.
- OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, live external PDF tools, and upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.

Non-overlap:
- Avoided AcroForm duplicate `/Fields`, named destinations, xref repair, annotation/action, CMap/font, attachment, image color-space, inline-image, and OCR/model surfaces.
- This slice is limited to filtered tiling-pattern stream boundaries in native searchable-PDF/image-review traversal.
