# markerpdf-stream-filter-stack-boundary-current-base-20260608T144951Z

Accepted base: `e204a40179162b2df94e6db36bf203fd0df70d1a`

Scope: native markerPDF metadata stream-filter boundary behavior. No OCR,
Surya, Texify, Torch, GPU/model workers, raster rendering, external PDF tools,
or live services were run.

## Behavior

Catalog XMP metadata streams using `/Filter /FlateDecode` now verify the byte
offset consumed by the complete compressed member before XMP promotion. A
second concatenated compressed member after the first member is a
non-whitespace tail and the stream fails closed as unreadable metadata. Exact
Flate metadata with trailing PDF whitespace remains accepted and promoted.

This is intentionally scoped to metadata extraction. It avoids overlapping the
accepted page-content stream-filter stack boundary work, trailing ASCII85/
ASCIIHex EOD payload checks, and supplied table/equation boundary slices.

## Evidence

Red-first focused run before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataFlateConcatenatedMemberBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects catalog XMP metadata when Flate stream has a concatenated member tail
PASS promotes catalog XMP metadata when Flate stream ends before PDF whitespace only
1 test files, 13 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataFlateConcatenatedMemberBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects catalog XMP metadata when Flate stream has a concatenated member tail
PASS promotes catalog XMP metadata when Flate stream ends before PDF whitespace only
1 test files, 32 assertions, 0 failures
```

Focused metadata stream-filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataFlateConcatenatedMemberBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS rejects catalog XMP metadata when Flate stream has a concatenated member tail
PASS promotes catalog XMP metadata when Flate stream ends before PDF whitespace only
PASS promotes catalog XMP metadata through ASCII85 and RunLength filter stacks
PASS rejects catalog XMP metadata filter-stack payload after explicit EOD marker
2 test files, 74 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-metadata-flate-concat-boundary-currentbase.php
{
    "scenario": "wordpress_pdf_metadata_flate_concatenated_member_boundary_currentbase",
    "concat_metadata_promoted": false,
    "concat_uses_info_fallback": true,
    "concat_review_status": "unreadable_metadata_stream",
    "concat_payload_included": false,
    "clean_metadata_promoted": true,
    "clean_text_visible": true,
    "concat_text_visible": true,
    "executes_python_or_models": false,
    "executes_external_pdf_tools": false,
    "self_test_passed": true
}
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataFlateConcatenatedMemberBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-metadata-flate-concat-boundary-currentbase.php
```

All reported no syntax errors.

## Dependency Closure

No new support component is needed. The implementation reuses PHP zlib inflate
state APIs already used elsewhere in markerPDF for bounded Flate stream end
detection. If those APIs are unavailable, Flate metadata boundary validation
fails closed for this document-XMP path instead of promoting partially decoded
metadata.

## Next Task

Continue with non-overlapping native PDF parser/converter behavior: searchable
PDF fonts/CMaps/text operators, xref repair, annotations/forms/security
preflight, page geometry, image/filter metadata, or supplied-boundary table and
equation handoffs.
