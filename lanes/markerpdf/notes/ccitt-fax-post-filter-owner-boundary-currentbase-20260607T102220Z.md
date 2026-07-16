# CCITT Fax Post-Filter Owner Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260607T102220Z`
Base: `6fa074599e5fd619ef5a15e90dfc55fbe48f9304`

## Behavior

PDF image streams may declare `CCITTFaxDecode`/`CCF` as a preview-only first filter while also declaring later native filters. In that shape, the CCITT RTC/EOFB marker is not a safe stream-owner terminator: later filters mean the stream payload is not natively complete at the CCITT marker.

This patch keeps first-position nonterminal CCITT filters from using the preview-only marker scanner as an owner boundary. If a stale `/Length` lands at that marker and a later real `endstream` closes the current object, markerPDF now repairs the owner span to the current object boundary and keeps fake nested stream objects out of text extraction and image review.

## Red-First Evidence

A focused probe with `/Filter [/CCF /ASCIIHexDecode]`, stale `/Length` at a CCITT RTC marker, and a page `/Contents [4 0 R 9 0 R 6 0 R]` leaked the fake embedded `9 0 obj` stream:

- extracted lines included `Post-native CCITT leak`;
- image review reported the short declared `raw_length` instead of the full owner payload;
- `ccitt_fax_filter_boundary.post_ccitt_filters_block_native_decode` was already true, so the missing behavior was stream ownership rather than metadata classification.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors
- `php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php` => no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => 1 test file / 1040 assertions / 0 failures
- `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php` => exits 0 and emits `post_ccitt_owner_filters_block_native_decode=true`, `post_ccitt_owner_payload_excluded_from_text=true`, and `post_ccitt_owner_payload_excluded_from_review=true`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF stream parser, filter-stack metadata, CCITT DecodeParms boundary helpers, and WordPress smoke path. No OCR, Surya/Texify/Torch, GPU/model worker, external PDF tool, or live-service dependency was introduced.

## Follow-Up

Keep future CCITT work scoped to native parser/review behavior: row/EOL ownership, filter-stack handoff metadata, image/filter metadata, and fail-closed stream boundary repair. Raster CCITT decoding remains out of scope under the current no-GPU/model markerPDF directive.
