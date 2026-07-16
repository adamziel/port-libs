# CCITT Fax ImageMask Polarity Boundary - Current Base

Date: 2026-06-05 UTC

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T063443Z`

Accepted base: `9516f07b7dbc0e31f892b7f1f85e7e8fc034d61d`

## Source Truth

PDF CCITT Fax images are one-bit image streams. `/DecodeParms /BlackIs1` tells the importer which encoded sample represents black, while an ImageMask `/Decode` array controls the stencil opacity for sample 0 and sample 1. Upstream markerPDF's no-GPU text/import boundary excludes CCITT raster bytes from visible text and leaves image-stream interpretation as review metadata unless a native raster backend is available.

This slice keeps that no-raster boundary and records the composite ImageMask polarity decision as review-only parser metadata:

- `black_sample_value` and `white_sample_value` come from effective CCITT `/BlackIs1` defaults or explicit DecodeParms.
- `black_sample_opacity` and `white_sample_opacity` come from the ImageMask `/Decode` array, including default `[0 1]`.
- inline image review adds `ccitt_fax_imagemask_polarity_review_before_rgb_conversion` and `inline_ccitt_fax_imagemask_polarity_review_before_rgb_conversion` notes without exposing payload bytes.

## Red-First Evidence

Before the source change, the focused current-base test failed because both XObject and inline review plans lacked `ccitt_fax_imagemask_polarity_boundary`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 219 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
1 test files, 241 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
emits inline_ccitt_imagemask_polarity and xobject_compact_imagemask_polarity with executes_python_or_models=false and executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat the accepted CCITT DecodeParms fail-closed, null-filter DecodeParms alignment, escaped DecodeParms key, stale endstream, identity `/Crypt`, direct EOFB/RTC, geometry/defaults, nested mask/alternate, or coding-mode boundary slices. It adds only the ImageMask polarity handoff that combines existing CCITT `/BlackIs1` metadata with existing ImageMask `/Decode` opacity metadata.

## Dependency Closure

No new support component is needed. The patch reuses native PDF dictionary, DecodeParms, inline-image, and ImageMask decode helpers. Full CCITT raster decoding remains intentionally out of scope for this no-GPU markerPDF lane and would require a future native raster backend or an explicitly authorized PDFium/PIL-style dependency.
