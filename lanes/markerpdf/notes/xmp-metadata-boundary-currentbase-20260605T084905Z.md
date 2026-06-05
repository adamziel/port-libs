# XMP Metadata Boundary Current Base - 2026-06-05

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T084905Z`  
Base: `980ef492bfe4c1ebea9d77eeee80c623451a7e76`

## Behavior

Catalog `/Metadata` is now covered at the reference boundary before document
XMP promotion:

- direct catalog metadata dictionaries are rejected as
  `rejected_non_indirect_metadata_reference`;
- missing indirect objects are rejected as `unresolved_metadata_reference`;
- unreadable `/Type /Metadata /Subtype /XML` streams are rejected as
  `unreadable_metadata_stream`;
- unreadable stream review rows preserve safe diagnostics for `filters` and
  `declared_length` while keeping payload bytes and XMP text out of document
  metadata and visible WordPress paragraphs.

This maps the native PDF parser boundary used before markerPDF/pdftext-style
metadata import. It does not run Python, PDFium, OCR, Surya, Texify, Torch,
external PDF tools, or model workers.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`

Result: `1 test files, 30 assertions, 1 failures`

Failure: unreadable catalog Metadata stream review rejected the stream but did
not preserve `filters`.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`

Result: `1 test files, 35 assertions, 0 failures`

Focused metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `19 test files, 1645 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-reference-boundary-currentbase.php`

Result: passed. The smoke reports `direct_metadata_dictionary_rejected=true`,
`unresolved_reference_rejected=true`, `unreadable_stream_rejected=true`,
`unreadable_filters_preserved=true`, `unreadable_declared_length_preserved=true`,
`payload_values_excluded_from_metadata=true`, and
`payload_values_excluded_from_visible_text=true`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-reference-boundary-currentbase.php` passed.

Required whitespace check:

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1638 -> 1641` from 3 new focused TestRunner cases.
- `wordpressScenarios`: `1511 -> 1512` from the new WordPress smoke.
- `lane-status.json` updated with this focused evidence.

## Non-Overlap

This slice avoids accepted XMP packet, CDATA, comment, entity, namespace,
typed-node, qualified-value, UTF-16, generation, associated-file, OutputIntent,
name-tree, xref, page-resource, and image/filter metadata clusters. It owns
only the catalog `/Metadata` reference fail-closed boundary.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP PDF
dictionary parsing, object reference resolution, stream dictionary label
inspection, filter parsing, and stream-length resolution. GPU/model/OCR parity
remains intentionally out of scope under the current no-GPU markerPDF override.
