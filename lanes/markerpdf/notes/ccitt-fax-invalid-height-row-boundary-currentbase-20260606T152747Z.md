# markerPDF CCITT Fax Invalid Height Row Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260606T152747Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T152747Z`
Accepted base: `3d6e6a3622decb12b82b423840061172715fe0f2`

## Source Truth

Upstream markerPDF keeps searchable-PDF text extraction on parser/dependency paths before any OCR or model stage. In this native no-GPU PHP lane, CCITT Fax raster bytes are still review-only, but stream ownership must be conservative so stale `endstream` tokens and fake object headers inside fax bytes cannot reopen page text parsing.

PDF CCITT `/EndOfBlock false` may use row EOL markers only when the parser knows a positive row count from `/DecodeParms /Rows` or the Image XObject height. If `/Rows` is omitted and image `/Height` is malformed, the effective CCITT metadata remains unbounded rows. That state is not safe enough to accept the first row EOL before a stale stream owner; it must fall back to the terminal CCITT marker when present.

## Red-First Evidence

After adding the focused invalid-height case on the accepted base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
FAIL requires valid image Height before omitted Rows can drive CCITT row EOL stream ownership
Expected: ["Before invalid-height CCITT rows", "After invalid-height CCITT rows"]
Actual: ["Before invalid-height CCITT rows", "Fake invalid-height CCITT owner leak", "After invalid-height CCITT rows"]
1 test files, 923 assertions, 1 failures
```

## Implementation

`PdfTextExtractor` now treats row-EOL ownership with omitted `/Rows` and missing or non-positive image height as a malformed row-owner boundary for Group 3 CCITT streams. The existing terminal-marker fallback is then used instead of accepting the stale stream boundary after the first row EOL.

The focused fixture uses `/EndOfBlock false`, `/EndOfLine true`, `/K 0`, valid `/Columns 16`, omitted `/Rows`, and malformed `/Height -1`. The payload contains a first-row EOL, a stale `endstream` plus fake object with text, then the real RTC marker. After the fix, XObject extraction repairs through the terminal marker, excludes the fake object from visible text and review JSON, and preserves unbounded-row review metadata. The paired inline-image fixture remains closed as well.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-invalid-height-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-invalid-height-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 950 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
4 test files, 3053 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-invalid-height-currentbase.php
Emits xobject_height_source=unbounded_rows, xobject_effective_height=null, xobject_boundary_repaired_to_terminal_marker=true, xobject_payload_excluded_from_text=true, xobject_payload_excluded_from_review=true, inline_payload_excluded_from_text=true, decoded_with_current_filters=false, native_raster_decode=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CCITT image-only exclusion, malformed `/Columns` row-owner fallback, valid row-EOL ownership, declared `/Rows` ownership, valid image-height-derived ownership, indirect height or indirect `/Rows` ownership, RTC/EOFB terminal ownership, malformed `K`/`EndOfLine`/`Rows` handling, unresolved DecodeParms operands, duplicate DecodeParms, filter-array DecodeParms alignment, native-prefix surplus checks, RunLength/LZW/ASCII85/Flate prefix boundaries, DCT/JPX/JBIG2 image boundaries, Image XObject stream-filter stack checks, or live OCR/model work. The bounded behavior is invalid or missing image height blocking height-derived row-EOL ownership when `/Rows` is omitted.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, stream dictionary reader, DecodeParms int/boolean parsing, CCITT owner-boundary detector, Image XObject review path, inline image tokenizer, and WordPress smoke renderer. Full CCITT raster decoding remains intentionally out of scope without a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, live-service provider, or GPU execution was run.
