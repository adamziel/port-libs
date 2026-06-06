# markerPDF CCITT Fax Invalid Columns Row Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260606T144926Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T144926Z`
Accepted base: `329b34568a5e9ea6b4a71ed3f0baabdca2830c90`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser/text dependencies before any OCR/model path. In this no-GPU native PHP lane, CCITT Fax raster bytes stay review-only, but stream ownership still has to be conservative so embedded fake `endstream`/object headers inside image bytes cannot reopen page text parsing.

`/EndOfBlock false` lets CCITT streams be bounded by row EOL markers only when the row parameters are trustworthy. An explicit malformed `/Columns` value is not equivalent to an omitted `/Columns` default, because row decoding cannot safely use the declared line width. The parser now treats that state like other malformed row-boundary DecodeParms and falls back to terminal CCITT markers instead of accepting the first row EOL before a stale object owner.

## Red-First Evidence

After adding the focused case on the accepted base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
FAIL requires valid CCITT Columns before row EOL stream ownership
Expected: ["Before invalid-column CCITT", "After invalid-column CCITT"]
Actual: ["Before invalid-column CCITT", "Fake invalid-column CCITT owner leak", "After invalid-column CCITT"]
1 test files, 895 assertions, 1 failures
```

## Implementation

`PdfTextExtractor` now checks explicit `/Columns` before CCITT row-EOL ownership. If `/Columns` is present and cannot resolve to a positive integer, row-EOL ownership is disabled and the existing terminal-marker fallback is used. This affects both Image XObject streams and inline CCITT image tokenization through the shared boundary helper.

The focused fixture uses `/EndOfBlock false`, `/EndOfLine true`, `/Rows 1`, and malformed `/Columns 0` with a first-row EOL followed by a fake object. The real RTC marker appears later in the same image payload. After the fix, both XObject and inline text extraction skip the fake object and preserve review-only CCITT metadata.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-invalid-columns-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-invalid-columns-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 922 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-invalid-columns-currentbase.php
Emits xobject_invalid_decode_parms_valid=false, xobject_invalid_decode_parms_fields=["columns"], xobject_invalid_columns_boundary_repaired=true, xobject_payload_excluded_from_text=true, inline_payload_excluded_from_text=true, executes_python_or_models=false, and executes_external_pdf_tools=false.

git diff --check -- lanes/markerpdf
No whitespace errors.
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CCITT image-only exclusion, valid row-EOL ownership, row-count/height-derived ownership, RTC/EOFB terminal ownership, malformed `K`/`EndOfLine`/`Rows` owner fallback, unresolved DecodeParms operand handling, duplicate DecodeParms, null-filter DecodeParms alignment, native-prefix surplus checks, RunLength/LZW/ASCII85/Flate prefix boundaries, DCT/JPX/JBIG2 image boundaries, or live OCR/model work. The bounded behavior is specifically malformed explicit `/Columns` blocking row-EOL stream ownership for CCITT Fax XObject and inline image parsing.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, stream dictionary reader, DecodeParms int/boolean parsing, CCITT owner-boundary detector, Image XObject review path, inline image tokenizer, and WordPress smoke renderer. Full CCITT raster decoding remains intentionally out of scope without a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, live-service provider, or GPU execution was run.
