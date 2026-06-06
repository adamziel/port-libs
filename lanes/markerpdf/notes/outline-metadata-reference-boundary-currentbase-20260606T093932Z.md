# markerpdf-outline-metadata-reference-boundary-currentbase-20260606T093932Z

Slice: `markerpdf-outline-metadata-boundary-current-base-20260606T093932Z`
Base accepted HEAD: `333ee46512d5ab2039cf170209aca42d287f1569`

## Behavior

Added a native no-GPU PDF outline metadata boundary for item-local `/Metadata`
operands. Direct dictionary operands, unresolved indirect references, and
resolved non-stream metadata dictionaries are now represented as fail-closed
review metadata on `document_outline.items[*].metadata_stream_review` without
promoting payload bytes to document XMP, navigation review payloads, or visible
WordPress paragraph text.

This maps the in-scope searchable-PDF/catalog metadata behavior only. It does
not run OCR, Surya, Texify, Torch, Python marker workers, or external PDF tools.

## Red/Green Evidence

Red-first focused run after adding the test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceBoundaryCurrentBaseTest.php`

Result: `1 test files, 28 assertions, 1 failures`

Failure: resolved non-stream outline `/Metadata 12 0 R` was reported as
`unreadable_metadata_stream` instead of a distinct non-stream fail-closed
metadata boundary.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceBoundaryCurrentBaseTest.php`

Result: `1 test files, 38 assertions, 0 failures`

Adjacent outline/metadata family:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfOutlineMetadata*CurrentBaseTest.php' | sort) lanes/markerpdf/tests/PdfOutlineMetadataReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: `44 test files, 2596 assertions, 0 failures`

## Dependency Closure

No new support component is required. The slice reuses the existing native PDF
dictionary parser, xref-selected object resolution, stream-boundary checks, and
outline metadata review path in `PdfMetadataExtractor`.

## Next

Continue non-overlapping markerPDF native parser work around searchable-PDF
metadata, outlines, annotations, forms, stream filters, image/filter metadata,
xref repair, and supplied-boundary table/equation handoffs.
