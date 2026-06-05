# Stream Filter Stack Duplicate Dictionary Key Boundary - 2026-06-05

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T193824Z`
Session: `port-dev-markerpdf-stream-filter-stack-20260605T193824Z`
Base accepted HEAD: `1023bcbd8ca3bdde63ea90d99e9760afec5b3560`

## Source Truth

Pinned upstream markerPDF source is `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream searchable-PDF extraction delegates native page text to `marker/pdf/extract_text.py::get_text_blocks()` / `naive_get_text()` through pdftext/PDFium, so the no-GPU PHP port has to preserve conservative PDF parser behavior before any OCR/model fallback.

For this slice, duplicate top-level stream dictionary `/Filter` or `/DecodeParms` keys are treated as malformed and ambiguous for filter-stack decoding. The native parser now fails closed for that individual stream instead of importing bytes decoded through whichever key the scanner encountered first. Sibling page content streams continue to import normally.

## Implementation

- `PdfTextExtractor::streamFilters()` now rejects streams with more than one top-level `/Filter` key before resolving a direct, array, null, or indirect filter operand.
- `PdfTextExtractor::streamDecodeParms()` and `streamDecodeParmsForFilters()` now reject streams with more than one top-level `/DecodeParms` key before predictor alignment.
- The existing top-level dictionary scanner is reused, so nested dictionaries, arrays, comments, literal strings, and private object bodies are not mistaken for duplicate stream-owned keys.

## Evidence

Red probe after adding the regression and before the source guard:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files, 244 assertions, 1 failures`; duplicate-key payloads leaked as `Duplicate Filter Key Leak` and `Duplicate DecodeParms Key Leak`.

Focused fix verification:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files, 255 assertions, 0 failures`.

Adjacent stream-filter family verification:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `7 test files, 393 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php`

Result: metadata emitted `duplicate_top_level_filter_key_rejected=true`, `duplicate_top_level_decodeparms_key_rejected=true`, `duplicate_filter_key_payload_excluded=true`, `duplicate_decodeparms_key_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`; visible paragraphs include `Visible After Duplicate Stream Keys` and exclude both duplicate-key leak strings.

## Non-Overlap

This does not repeat the accepted stream-filter stack work for ASCII85/Flate missing length boundaries, null filters, compact DecodeParms alignment, all-null filters, indirect null filters, LZW short lengths, Crypt Identity/default behavior, parser-comment split indirect references, malformed indirect multi-name filter objects, attachment LZW/Crypt/dictionary filters, overdeclared lengths, trailing payload EOD rejection, CMap/filter ownership, object/xref streams, image filter metadata, or tiling-pattern image traversal. This slice owns only duplicate top-level stream dictionary keys for `/Filter` and `/DecodeParms`.

## Dependency Closure

No new support component is required. The patch reuses the existing native PHP PDF object parser, top-level dictionary value scanner, stream filter resolver, DecodeParms resolver, stream decoder, text-content parser, and WordPress smoke path. No Python, pdftext, PDFium, OCR, Surya, Texify, Torch, GPU/model execution, or external PDF tooling was used.

Root harness: not run - isolated micro-slice.
