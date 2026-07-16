# XMP Direct Array Metadata Boundary Current Base

- Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T172205Z`
- Base accepted HEAD: `19e469ac5fba851474b6c82ad19f3b8c0f411282`
- Scope: native no-GPU PDF catalog metadata boundary; no OCR, model, raster, action execution, or external PDF tools.

## Behavior

PDF Catalog `/Metadata` is promoted only when it is a single indirect reference to a document XMP metadata stream. This slice keeps direct array values such as `/Metadata [5 0 R 7 0 R]` review-only, while surfacing the direct value type, sanitized preview, array entry count, and referenced object numbers for WordPress/import review. Referenced XMP and action payload text remain excluded from document metadata and visible text.

The production change reuses the existing native PDF tokenizer, array parser, indirect-reference parser, and metadata stream boundary review helpers in `PdfMetadataExtractor`; it does not add a support component.

## Evidence

- Red-first focused check after fixture/harness normalization: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDirectArrayBoundaryCurrentBaseTest.php` failed before the extractor change because `metadata_value_type` was not present in `catalog.metadata_stream_review`.
- Focused test after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDirectArrayBoundaryCurrentBaseTest.php` => `1 test files, 18 assertions, 0 failures`.
- Adjacent boundary family: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpIndirectObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpDirectArrayBoundaryCurrentBaseTest.php` => `3 test files, 220 assertions, 0 failures`.
- Broad XMP current-base family: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php` => `71 test files, 3319 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-xmp-direct-array-boundary-currentbase.php` exits 0 and reports `status=rejected_non_indirect_metadata_reference`, `metadata_value_type=array`, `referenced_object_numbers=[5,7]`, `xmp_payload_redacted=true`, and `action_payload_redacted=true`.

## Dependency Closure

No new dependency or support-library row is needed. The slice is fully native PHP parser/review behavior and reuses existing bounded PDF value parsing helpers.

## Next

A non-overlapping follow-up would cover another catalog metadata trust boundary, such as malformed direct arrays with non-reference members, without promoting payload bytes or duplicating direct-array reference reporting.
