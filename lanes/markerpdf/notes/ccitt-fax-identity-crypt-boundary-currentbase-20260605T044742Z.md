# CCITT Fax Identity Crypt Boundary Current Base

## Source Truth

- Upstream markerPDF keeps image payload bytes out of searchable page text and hands image/filter metadata to downstream review or raster paths.
- PDF stream filter semantics allow explicit `/Crypt` identity filters to behave as pass-through stages before `/CCITTFaxDecode`.
- CCITT Fax streams with `/EndOfBlock true` use an EOFB or RTC marker as the reliable image-data boundary when a non-delimiting prefix filter leaves embedded `endstream` bytes in the payload.
- Current no-GPU markerPDF scope applies: no OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, or external PDF tools were run.

## Behavior

- `PdfTextExtractor` now requires decoded CCITT Fax end-of-block markers before accepting repaired `endstream` candidates after identity `/Crypt` prefix filters.
- Fake `endstream` / `endobj` bytes embedded before the CCITT boundary remain part of the image payload instead of becoming a stream owner boundary.
- `/EndOfBlock false` and filter stacks without a CCITT marker requirement keep the existing fail-closed/legacy boundary behavior.
- Searchable text extraction still imports text before and after the image while excluding CCITT payload bytes.

## Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` passed with `1 test files, 197 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php` passed with `1 test files, 136 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php` exits `0` and emits `identity_crypt_wrapped_ccitt_eofb_boundary_repaired=true`.
- `git diff --check -- lanes/markerpdf`

## Status Delta

- Focused CCITT Fax test assertions: `175 -> 197`.
- Focused markerPDF PHP pass count: `1426 -> 1427`.
- WordPress scenario count: `1354 -> 1355`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This patch does not touch OCR/model execution, raster decoding, generic image previews, Flate/ASCIIHex/RunLength stream-stack repair, CMaps, fonts, xref repair, outlines, annotations, forms, metadata, page geometry, or security preflight clusters.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP tokenizer, stream filter stack, identity `/Crypt` pass-through handling, CCITT DecodeParms extraction, and review-only image metadata path.
