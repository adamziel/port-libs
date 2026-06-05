# markerpdf named-destination byte-string Limits boundary current-base

Session: `port-dev-markerpdf-named-destinations-20260605T104213Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T104213Z`
Base accepted HEAD: `c6b8bdd91e9129ca076584776bb76e4fcded4d0c`

## Source truth

Upstream markerPDF delegates searchable-PDF navigation and destination extraction to pdftext/PDFium at the parser boundary. In the no-GPU PHP lane, the equivalent native boundary is catalog `/Names /Dests` name-tree parsing without executing PDF actions or external render/model tools.

PDF name-tree `/Limits` delimit destination-name keys by their PDF string bytes, while the user-visible destination labels are decoded text strings. This matters for PDFDocEncoding bytes such as `<18>`, which decodes to U+02D8 and sorts differently as UTF-8 text than it does as a one-byte PDF string.

## Red-first evidence

Before the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationByteStringLimitsCurrentBaseTest.php`

Result:

- `FAIL compares destination name-tree Limits by source bytes before decoded labels`
- Actual destination names included the out-of-range `<80>` bullet destination before `LegacyOk`.
- `FAIL keeps decoded out-of-byte-range destination keys out of WordPress text and metadata`
- Summary: `1 test files, 5 assertions, 2 failures`

## Implementation

- `PdfNamedDestinationExtractor` now preserves raw PDF string bytes in parsed string values as `__pdf_bytes` while still exposing decoded `__pdf_string` labels to normalization.
- Name-tree `/Limits` extraction, inherited/local limit intersection, leaf pair matching, and final key filtering now compare raw source bytes.
- Destination output still uses decoded labels, so WordPress review metadata preserves readable destination names while stale out-of-byte-range rows stay hidden.

## Verification

`php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php`

`No syntax errors detected in lanes/markerpdf/src/PdfNamedDestinationExtractor.php`

`php -l lanes/markerpdf/tests/PdfNamedDestinationByteStringLimitsCurrentBaseTest.php`

`No syntax errors detected in lanes/markerpdf/tests/PdfNamedDestinationByteStringLimitsCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-byte-limits-currentbase.php`

`No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-named-destination-byte-limits-currentbase.php`

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationByteStringLimitsCurrentBaseTest.php`

`1 test files, 15 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*CurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php`

`19 test files, 451 assertions, 0 failures`

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-byte-limits-currentbase.php`

Smoke emitted `byte_string_limits_applied=true`, `out_of_byte_range_decoded_key_rejected=true`, `destination_names=["\u02d8","A","LegacyOk"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

`git diff --check -- lanes/markerpdf`

No whitespace errors.

## Non-overlap

This does not repeat accepted named-destination generation, name-key, view-mode, page-only, direct/indirect page-operand, xref/object-stream, trailer-root, action-dictionary, intermediate-limit, internal-node, limits-fallback, link annotation name-tree `/Limits`, outline destination action-context, or inline image tokenizer slices. The bounded behavior is specifically source-byte comparison for PDF string keys and `/Limits` inside the standalone catalog named-destination extractor.

## Dependency closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, tokenizer, PDFDocEncoding decoder, generation-aware resolver, page-tree indexer, named-destination normalizer, text extractor, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
