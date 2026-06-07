# XMP Dublin Core Date Boundary Current Base - 2026-06-07

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260607T153417Z`  
Base: `ecdae3d672a8d414071d8e7c8995009a528f904e`

## Behavior

Native PDF metadata extraction now treats document-level XMP `dc:date` list
values as the XMP document date source when `xmp:CreateDate` is absent. The
first Dublin Core date becomes `created_at`, UTC normalization is applied, and
the full date sequence is preserved under `xmp_dublin_core` review metadata.
Older trailer `/Info /CreationDate` remains visible in `info` but no longer
wins over current document XMP Dublin Core dates.

Rejected XML streams still stay review-only: they expose redacted summary
counts and normalized dates without promoting XMP text into document metadata
or visible WordPress paragraphs.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDublinCoreDateBoundaryCurrentBaseTest.php`

Result: `1 test files, 18 assertions, 2 failures`

Failures:

- accepted document XMP used stale Info `D:20240101000000Z` instead of
  `dc:date` value `2026-06-07T09:34:56-08:00`;
- rejected XML stream summary omitted `created_at` and `dublin_core` fields.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDublinCoreDateBoundaryCurrentBaseTest.php`

Result: `1 test files, 40 assertions, 0 failures`

Focused metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `53 test files, 3265 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-dublin-core-date-boundary-currentbase.php`

Result: passed. The smoke reports `xmp_dc_date_promoted=true`,
`xmp_dc_date_utc_normalized=true`,
`stale_info_creation_date_not_promoted=true`,
`dublin_core_dates_preserved=true`, `trailing_packet_excluded=true`,
`visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpDublinCoreDateBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-dublin-core-date-boundary-currentbase.php` passed.

Whitespace check:

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Adds 2 focused PASS cases.
- Adds 1 WordPress smoke scenario.

## Non-Overlap

This does not repeat accepted XMP packet begin/end, internal instruction,
complete-packet fallback, UTF-16/declared encoding, compact RDF attributes,
language alternatives, Dublin Core format/language/rights, typed-node,
resource-reference, structured-list, duplicate catalog Metadata, or XMP stream
dictionary boundary slices. The bounded behavior is only Dublin Core `dc:date`
sequence promotion when explicit XMP date fields are absent.

## Dependency Closure

No new support component is needed. This reuses native PHP PDF object
selection, stream decoding, XMP packet bounding, RDF list parsing, metadata
merge, UTC date normalization, and WordPress smoke rendering. Full upstream
GPU/model parity remains intentionally out of scope under the current no-GPU
markerPDF override.
