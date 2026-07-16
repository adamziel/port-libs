# Image XObject Metadata Filter Operand Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260607T034339Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260607T034339Z`
Base accepted HEAD: `b6751e8d16a369b3cb6f380d161ef10027ea4635`

## Source Truth

Upstream markerPDF keeps image XObject payloads and their auxiliary metadata in
review-only handoff space for downstream import decisions. In the native PHP
no-GPU lane, an image-local `/Metadata` stream may be summarized, but hidden
filter-helper operands such as `/FlateDecode /Crypt ...` must not be trusted as
a normal metadata stream decode boundary.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now records a
fail-closed review for image XObject `/Metadata` stream `/Filter` operands
whose indirect helper object carries a hidden trailing operand. The review
keeps the leading filter name for diagnostics, sets
`status=rejected_malformed_image_xobject_metadata_stream_filter_operand`,
`filter_operand_policy=reject_malformed_filter_operands`,
`invalid_filter_operand_count=1`, blocks decoded metadata hashes, and preserves
`payload_in_visible_text=false`.

Single-name indirect filter helpers remain accepted, so ordinary Flate-decoded
image metadata review still reports decoded length and SHA-256 without adding a
rejection status.

## Evidence

Red-first focused run after adding the regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMetadataFilterOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects image XObject metadata streams with indirect Filter helpers that carry extra operands
Expected: 'rejected_malformed_image_xobject_metadata_stream_filter_operand'
Actual: NULL
PASS accepts image XObject metadata streams with single-name indirect Filter helpers
1 test files, 16 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMetadataFilterOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS rejects image XObject metadata streams with indirect Filter helpers that carry extra operands
PASS accepts image XObject metadata streams with single-name indirect Filter helpers
2 test files, 1310 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-metadata-filter-operand-currentbase.php
```

The smoke emits `image_metadata_stream_rejected=true`,
`filter_operand_policy=reject_malformed_filter_operands`,
`filters=["FlateDecode"]`, `invalid_filter_operand_count=1`,
`malformed_filter_operand_count=1`, `extra_filter_name=Crypt`,
`decoded_with_current_filters=false`,
`payload_excluded_from_text_and_review=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted catalog XMP CCITT metadata rejection, catalog XMP
malformed `/Filter` operand rejection, duplicate image `/Filter` declarations,
direct XObject resource entry tails, indirect resource wrappers, CCITT/DCT/JPX
image codec preview-only boundaries, image `Do` text-object/compatibility
section suppression, optional content visibility, or ordinary Flate image
metadata stream review. It owns only image-XObject-local metadata stream filter
helper tails.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object
scanner, stream dictionary/payload parser, stream filter resolver, and image
XObject boundary review. Full native raster decoding, PDFium/PIL rendering,
live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI model workers,
and exact upstream model benchmark parity remain intentionally out of scope for
the current no-GPU markerPDF lane.
