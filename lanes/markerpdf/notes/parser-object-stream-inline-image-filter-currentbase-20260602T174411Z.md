# Parser Object-Stream Inline Image Filter Current Base

Date: 2026-06-02 UTC

Slice: `parser-object-stream-inline-image-filter-currentbase-20260602T174411Z`

Base accepted HEAD: `252c505983bfd6b8ea68d7f5271483812ad199ee`

## Source Truth

- Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to PDFium/pdftext-style page content extraction. Native fallback text import should therefore use current page content streams and should not treat object-stream carrier payloads or inline image bytes as visible text.
- PDF object streams are compressed-object containers. Xref type-2 rows select member objects from an `/ObjStm`; the carrier stream itself is not fallback visible text.
- Inline image `BI`/`ID`/`EI` payload bytes are image data. Filtered inline image bytes can contain fake `EI`, `BT`, text, `endstream`, `endobj`, or `obj` tokens that must not enter WordPress paragraph output.

## Red-First Probe

Before the repair, the local fixture with a direct page content stream whose `/Length`, `/Filter`, and `/DecodeParms` operands were recovered only from an object stream returned no text lines. The first direct-object scan stopped at fake `endstream`/`endobj` tokens inside the compressed content payload before object-stream helper operands were available.

## Implementation

- `PdfTextExtractor` now does a post-object-stream direct stream-boundary repair pass.
- The repair only replaces a selected direct object body when object-stream-expanded helpers allow a longer valid filtered stream boundary than the initial scan found.
- The repair reuses current xref-selected direct objects, resolved indirect `/Length`, `/Filter`, and `/DecodeParms`, and the existing filtered terminator validation.
- Existing inline-image parsing continues to validate `/F` and `/DP` arrays with `null` placeholders before excluding compressed inline image payload bytes from text-token parsing.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php`
  - `1 test files, 11 assertions, 0 failures`
- Adjacent parser/text gate:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamLengthFilterRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - `10 test files, 688 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-object-stream-inline-image-filter-currentbase.php`
  - emitted `object_stream_helpers_recovered=true`, `fake_endstream_owner_excluded=true`, `fake_ei_inside_compressed_inline_image=true`, `inline_image_payload_excluded=true`, and `visible_text_imported=true`.
- Whitespace:
  - `git diff --check -- lanes/markerpdf`
  - passed with no output.

## Non-Overlap

This is not the accepted object-stream carrier exclusion, xref object-stream filter-chain operand recovery, xref-stream object-owner boundary, stream DecodeParms owner boundary, or inline image filter-array abbreviation/null-entry slice. It combines the still-open case where direct stream boundaries must be repaired after object-stream-expanded helper operands become available with filtered inline image payload exclusion.

## Dependency Closure

No new support component is required. The slice reuses native PHP xref/object-stream parsing, stream-filter decoding, DecodeParms predictor handling, and inline-image token boundaries. It does not execute Python, PDFium/pdftext, pypdfium, PIL, OCR models, JavaScript, PDF actions, or external PDF tools.
