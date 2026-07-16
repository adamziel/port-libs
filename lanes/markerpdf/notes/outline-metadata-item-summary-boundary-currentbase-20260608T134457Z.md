# Outline Metadata Item Summary Boundary Current Base

## Scope

Isolated markerPDF lane slice `markerpdf-outline-metadata-boundary-current-base-20260608T133617Z` on accepted base `ab39c48b2a82ff9622403db018d37fcff9180477`.

This patch maps a bounded native PDF parser boundary for outline item-local `/Metadata` streams:

- bookmark-local `/Metadata` streams remain review-only item metadata;
- malformed `/Metadata` operands with extra top-level references are rejected without decoding hidden streams;
- the document-level `document_outline` summary now records item metadata stream count, statuses, object numbers, trailing reference objects, types, subtypes, and filters;
- Catalog `/Metadata` XMP and trailer `/Info` remain the only document metadata roots;
- outline titles and hidden bookmark XMP payloads remain out of visible WordPress paragraph text.

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` into `pdftext.extraction.dictionary_output(...)` and simple text extraction through `naive_get_text()` into pypdfium page text. In this native no-GPU lane, `PdfMetadataExtractor` owns the PHP parser boundary that keeps PDF catalog/document metadata, PDF outline review rows, and visible text extraction separated before WordPress import.

Existing current-base coverage already handled item-local `/Metadata` stream review rows. This slice adds the missing outline-level aggregation needed by WordPress review flows without changing the payload boundary.

## Red-First Evidence

Before the source change, the new focused test failed because `document_outline.item_metadata_stream_count` and related summary fields were absent:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataItemSummaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL summarizes outline item Metadata review boundaries without promoting bookmark XMP
Values are not identical
Expected: 2
Actual: NULL
PASS carries outline item Metadata review into navigation while keeping bookmark payloads hidden

1 test files, 32 assertions, 1 failures
```

## Implementation

`PdfMetadataExtractor` now derives `document_outline` item metadata stream summary fields from existing per-item `metadata_stream_review` rows:

- `item_metadata_stream_count`
- `item_metadata_stream_review_only`
- `item_metadata_stream_payload_included`
- `item_metadata_stream_accepted_as_document_xmp`
- `item_metadata_stream_statuses`
- `item_metadata_stream_objects`
- `item_metadata_stream_trailing_reference_objects`
- `item_metadata_stream_types`
- `item_metadata_stream_subtypes`
- `item_metadata_stream_filters`

No payload bytes are added to the summary; stream content remains represented only by existing per-item hashes and redacted XMP summaries.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataItemSummaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes outline item Metadata review boundaries without promoting bookmark XMP
PASS carries outline item Metadata review into navigation while keeping bookmark payloads hidden

1 test files, 56 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutline.*Metadata.*CurrentBaseTest\.php$' | sort)
Focused test run: 74 selected test files (root lock skipped)
74 test files, 3202 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataItemSummaryBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-item-summary-currentbase.php
No syntax errors detected
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-item-summary-currentbase.php
exit 0
```

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary/value parser, stream decoder, XMP summary redaction, outline metadata review rows, navigation action review carry-through, and visible-text extraction boundaries. No Python, OCR, Surya, Texify, Torch, pypdfium/PDFium, Poppler, Ghostscript, raster rendering, PDF action execution, model workers, or external PDF tools were run.

## Next Task

Continue non-overlapping no-GPU markerPDF work in native searchable-PDF behavior: xref repair, object-stream filter metadata, font/CMap width edges, annotations/forms/security preflight, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
