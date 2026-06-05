# Associated FileSpec XMP Generation Boundary

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T000856Z`

Accepted base: `f5adeb178f5cfe9e1a6ae8ca9f6d42cce63d7b49`

## Behavior

Attachment-local FileSpec `/Metadata` XMP streams are review metadata only. This patch makes that provenance generation-exact: `/Metadata 6 0 R` no longer summarizes the currently selected `6 1 obj` stream when the current xref/object owner has generation 1. Exact references such as `/Metadata 6 1 R` still produce redacted `xmp_summary` review rows.

The document-root Catalog `/Metadata` path already used generation-exact reference resolution; this patch applies the same boundary to associated-file XMP provenance without changing root XMP promotion, Info fallback, OutputIntent review, or encrypted metadata policy.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps FileSpec XMP metadata provenance generation exact before WordPress import review
Values are not identical
Expected: array (
  0 => 'filespec_afrelationship',
  1 => 'embedded_file_payload_hash',
)
Actual: array (
  0 => 'filespec_afrelationship',
  1 => 'embedded_file_payload_hash',
  2 => 'filespec_metadata_stream',
)

1 test files, 7 assertions, 1 failures
```

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps FileSpec XMP metadata provenance generation exact before WordPress import review

1 test files, 26 assertions, 0 failures
```

Adjacent XMP metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadata*Xmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpGenerationBoundaryCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
...
8 test files, 1124 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-associated-xmp-generation-boundary-currentbase.php
```

The smoke emits `associated_file_count=2`, `mismatched_generation_excluded=true`, `exact_generation_summarized=true`, `stale_attachment_xmp_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the previous outline `/Prev` sibling traversal boundary, root XMP packet-boundary trimming, encrypted root XMP policy, xref `/Prev` current trailer selection, or catalog non-Metadata XML rejection. It only changes attachment-local FileSpec XMP provenance resolution.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF object reference owner map and stream decoder. It runs entirely in PHP and does not invoke Python, Surya/Texify/Torch, OCR/model workers, pypdfium, or external PDF tools.

## Next Task

Continue with a non-overlapping native no-GPU markerPDF metadata/parser boundary, preferably FileSpec/PieceInfo generation handling for non-XMP streams, page/catalog metadata source priority, or another searchable-PDF parser edge with focused tests.
