# markerpdf outline metadata stream generation boundary current-base

Slice: `markerpdf-outline-metadata-boundary-current-base-20260606T101854Z`

Accepted base: `e1661ddde6bf69323245293250d294a721f7503c`

## Behavior

Native outline item `/Metadata` stream review now records the exact indirect
object generation referenced by the outline item. A stale-generation
`/Metadata 9 0 R` reference remains unresolved when the selected object owner
is generation `1`, while `/Metadata 9 1 R` is reviewed as the current
bookmark-local metadata stream.

This maps the no-GPU markerPDF boundary for searchable PDFs where outline
bookmarks can carry local metadata streams. WordPress imports keep the stream
payload review-only, preserve object-generation provenance, and do not promote
the XMP/XML payload into document metadata, TOC/navigation rows, or visible
paragraph text.

## Evidence

Red-first before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamGenerationBoundaryCurrentBaseTest.php`

Result: `1 test files, 25 assertions, 1 failures`; failure was the missing
`object_generation` field on the reviewed exact-generation metadata stream.

After source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamGenerationBoundaryCurrentBaseTest.php`

Result: `1 test files, 47 assertions, 0 failures`.

Adjacent outline metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php`

Result: `6 test files, 357 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-generation-currentbase.php`

Result: emitted
`exact_metadata_generation=1`, `exact_metadata_status=reviewed_outline_item_metadata_stream`,
`stale_metadata_generation=0`, `stale_metadata_status=unresolved_metadata_reference`,
`current_payload_redacted=true`, `stale_payload_redacted=true`, and
`navigation_payload_redacted=true`.

Syntax:

`php -l lanes/markerpdf/src/PdfMetadataExtractor.php`

`php -l lanes/markerpdf/tests/PdfOutlineMetadataStreamGenerationBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-generation-currentbase.php`

All reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP
PDF object-owner and stream-filter paths in `PdfMetadataExtractor`; no Python,
models, GPU execution, pypdfium, PIL, external PDF tools, or live services are
required.
