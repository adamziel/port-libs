# XMP Metadata Extra Operand Boundary

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T020610Z`
Base accepted HEAD: `0f344fe5e92e069e811b55e3b6740f8331906302`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` relies on PDF parser metadata boundaries before document metadata is surfaced to conversion output. In the no-GPU native PHP lane, catalog `/Metadata` is therefore treated as a single catalog-owned PDF metadata stream reference before XMP can override trailer `/Info`.

## Behavior

`PdfMetadataExtractor` now rejects a catalog `/Metadata` value that starts with a valid indirect reference but carries extra top-level operands, such as `/Metadata 5 0 R 7 0 R`.

Before this slice, that malformed value promoted object `5` as root XMP because the reference parser accepted the leading `5 0 R` prefix. The new boundary records review-only status `rejected_malformed_metadata_operand`, preserves the leading object number and trailing reference object numbers for audit, falls back to trailer `/Info`, and keeps XMP/action-tail text out of WordPress paragraphs and metadata values.

## Red-First Evidence

Before source edits, after adding the focused assertion:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects catalog Metadata references followed by extra top-level operands before XMP promotion
Expected: ['info', 'catalog']
Actual: ['xmp', 'info']
1 test files, 78 assertions, 1 failures
```

After source/test/example edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php
1 test files, 94 assertions, 0 failures
```

Adjacent focused XMP metadata boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStreamObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStreamFilterDictionaryBoundaryCurrentBaseTest.php
3 test files, 169 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-boundary-currentbase.php
```

The smoke reports `extra_operand_metadata_status=rejected_malformed_metadata_operand`, `extra_operand_metadata_operand_count=2`, `extra_operand_metadata_trailing_reference_objects=[9]`, `extra_operand_metadata_info_fallback_title=Extra Operand Metadata Info Title`, and confirms the hidden XMP title and action tail are not visible text.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted null `/Metadata`, direct dictionary, unresolved reference, non-stream metadata object, unreadable filter, duplicate key, XMP packet-marker, stream-object tail, unsafe entity/DTD, associated-file XMP, PieceInfo XMP, encrypted metadata policy, xref owner, object-stream, or Info fallback coverage. The bounded behavior is specifically a single catalog `/Metadata` key whose leading indirect reference is followed by extra top-level operands before the next dictionary key.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, XMP parser, metadata stream decoder, trailer `/Info` fallback, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for this markerPDF lane.
