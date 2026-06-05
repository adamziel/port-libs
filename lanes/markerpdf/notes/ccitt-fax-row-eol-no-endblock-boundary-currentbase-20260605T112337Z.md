# CCITT Fax Row-EOL No-EndBlock Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T112337Z`
Base: `188279b1bd834a52bbb4d3ff85edc9708325a53c`

## Source Truth

PDF `/CCITTFaxDecode` image streams are image-only filter payloads for markerPDF's current no-GPU native parser scope. This slice maps the PDF CCITT Group 3 boundary where `/DecodeParms` explicitly declares `/EndOfLine true` and `/EndOfBlock false`: a terminal row EOL marker is useful stream-ownership evidence when stale `/Length` stops at fake `endstream/endobj` text before the actual row terminator.

This does not add raster decoding. It only keeps CCITT payload bytes review-only and prevents fake object text inside the image stream from becoming WordPress visible text.

## Behavior Added

- `PdfTextExtractor` now treats explicit Group 3 `/EndOfLine true` plus `/EndOfBlock false` DecodeParms as a bounded direct stream-ownership case and accepts the row EOL marker `00 10 01` as the terminal evidence.
- The repair is fail-closed for missing `/EndOfLine`, non-true `/EndOfLine`, Group 4 `/K < 0`, and invalid or non-positive `/Rows`.
- The focused regression verifies that a stale `/Length` ending at fake `endstream/endobj` before the row EOL marker does not promote the fake object into `extractTextLines()`, `extractPlainText()`, or image review JSON.
- The WordPress smoke now emits `xobject_row_eol_no_endblock_*` flags for the same boundary and confirms payload exclusion from review and text output.

## Evidence

Red baseline before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 345 assertions, 1 failures
```

Focused pass after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 361 assertions, 0 failures
```

Focused assertion delta: `+16`
Focused PASS-case delta: `+1`
WordPress scenario delta: `+1`

Additional focused verification:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php

php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
emits xobject_row_eol_no_endblock_boundary_repaired=true and row-EOL payload exclusion flags true

git diff --check -- lanes/markerpdf
clean
```

## Non-Overlap

This slice does not repeat prior CCITT filter exclusion, DecodeParms fail-closed, null-filter array alignment, Flate/Crypt prefix recovery, direct EOFB/RTC marker ownership, ImageMask polarity, coding-mode metadata, escaped filter-name parsing, or malformed owner-boundary behavior. It owns only the explicit `/EndOfLine true` plus `/EndOfBlock false` row-EOL stream boundary.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PDF stream tokenizer, DecodeParms parser, and image-filter review path. GPU/model OCR, Surya/Texify/Torch, raster CCITT decoding, live services, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
