# CCITT Fax Metadata Filter Boundary Current Base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260606T212309Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T212309Z`
Base accepted HEAD: `7e2d93b1714f0e3d0a46f1fe85666171af666594`

## Source Truth

Upstream markerPDF treats searchable metadata/text extraction separately from
raster image decoding. In this no-GPU PHP lane, `CCITTFaxDecode` and `CCF`
remain image-raster filters: they can be recorded as review metadata, but their
payload bytes must not be passed through as XML metadata or promoted into
WordPress document fields.

## Behavior

`PdfMetadataExtractor` now rejects root Catalog `/Metadata` XML streams whose
stream filter stack contains `CCITTFaxDecode` or `CCF`. The stream remains
review-only with `status=rejected_ccitt_fax_metadata_stream_filter`,
`filter_operand_policy=reject_ccitt_fax_metadata_stream_filter`,
`preview_only_filters`, `decoded_with_current_filters=false`, and
`native_metadata_decode=false`.

This prevents an XML-looking CCITT payload from becoming document XMP. Info
metadata still remains available as a fallback, and visible page text is
unchanged.

## Evidence

Red-first focused run after adding the regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects CCITT Fax filtered catalog Metadata streams before XMP promotion
Expected: array (
  0 => 'info',
  1 => 'catalog',
)
Actual: array (
  0 => 'xmp',
  1 => 'info',
)
1 test files, 863 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects CCITT Fax filtered catalog Metadata streams before XMP promotion
1 test files, 884 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-metadata-filter-boundary-currentbase.php
```

The smoke emits `ccitt_metadata_stream_rejected=true`,
`filter_operand_policy=reject_ccitt_fax_metadata_stream_filter`,
`filters=["CCF"]`, `preview_only_filters=["CCF"]`,
`decoded_with_current_filters=false`, `native_metadata_decode=false`,
`info_fallback_used=true`, `payload_excluded_from_metadata=true`,
`executes_python_or_models=false`, `executes_external_pdf_tools=false`, and
`executes_pypdfium_or_pil=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject CCITT metadata, inline-image CCITT
payload boundaries, renderer chained indirect filters, malformed/unresolved
image filter operands, duplicate image `/Filter` declarations, native prefix
ownership, row/EOFB/RTC stream ownership, or metadata malformed-filter operand
shape checks. It owns only the document metadata trust boundary where a validly
shaped Catalog `/Metadata` stream declares an image-only CCITT Fax filter.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
metadata stream boundary review, stream filter resolver, Info fallback metadata
path, `PdfTextExtractor`, and WordPress smoke renderer. Full CCITT raster
decoding, PDFium/PIL rendering, live OCR, Surya/Torch model execution, Texify,
Streamlit/FastAPI workers, and exact upstream model benchmark parity remain
intentionally out of scope for the no-GPU markerPDF lane.
