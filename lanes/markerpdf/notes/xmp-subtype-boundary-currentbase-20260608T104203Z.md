# markerPDF XMP Subtype Boundary

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T104203Z`

Base accepted HEAD: `bab5ae81604eed326531f1dc0bccc0d58503ec7f`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF metadata through parser/PDFium/pdftext boundaries before model fallback. Under the current no-GPU markerPDF scope, native PHP owns catalog `/Metadata` XMP trust boundaries without invoking OCR, Surya, Texify, Torch, raster rendering, JavaScript actions, or external PDF tools.

PDF document XMP is promoted only from the Catalog `/Metadata` indirect stream whose stream dictionary role is unambiguous: `/Type /Metadata` and `/Subtype /XML`. This slice covers the remaining Subtype side of that role boundary.

## Behavior

`PdfMetadataExtractor::metadataStreamDictionaryTypeBoundaryReview()` now treats `/Subtype` as a required XML role when `/Type /Metadata` is present:

- missing `/Subtype` returns `rejected_missing_metadata_stream_subtype`;
- literal, array, dictionary, or otherwise non-name `/Subtype` returns `rejected_non_name_metadata_stream_subtype`;
- non-XML name subtypes return `rejected_non_xml_metadata_stream_subtype`;
- a valid direct or indirect single `/XML` subtype remains accepted.

Rejected XMP packets are summarized with text values redacted, trailer `/Info` metadata remains the WordPress fallback, and XMP payload text stays out of visible paragraphs.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects metadata streams missing the XML Subtype role before XMP promotion
Expected: 'rejected_missing_metadata_stream_subtype'
Actual: 'rejected_non_metadata_xml_stream'
FAIL rejects non-name metadata stream Subtype operands before XMP promotion
Expected: 'rejected_non_name_metadata_stream_subtype'
Actual: 'rejected_non_metadata_xml_stream'
FAIL rejects non XML metadata stream Subtype names before XMP promotion
Expected: 'rejected_non_xml_metadata_stream_subtype'
Actual: 'rejected_non_metadata_xml_stream'
PASS accepts indirect single XML Subtype helpers for document XMP
1 test files, 34 assertions, 3 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpSubtypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects metadata streams missing the XML Subtype role before XMP promotion
PASS rejects non-name metadata stream Subtype operands before XMP promotion
PASS rejects non XML metadata stream Subtype names before XMP promotion
PASS accepts indirect single XML Subtype helpers for document XMP
1 test files, 72 assertions, 0 failures
```

Adjacent stream-role family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMissingTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStreamDictionaryDuplicateTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpRawDuplicateTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpRoleOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStreamObjectBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 448 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-subtype-boundary-currentbase.php
```

Result: exits `0` and emits `review_status="rejected_missing_metadata_stream_subtype"`, `info_fallback_title_selected=true`, `xmp_payload_redacted=true`, `valid_indirect_subtype_promoted=true`, `valid_indirect_subtype_has_no_rejection=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/non-stream handling, duplicate `/Metadata` entries, duplicate `/Type` or `/Subtype` keys, missing/non-name `/Type`, tailed role operands, malformed packet begin/end boundaries, internal xpacket marker handling, unreadable stream filters, stream object tail rejection, encrypted metadata source policy, named destinations, outlines, annotations, forms, OCR/model execution, or external PDF tooling.

The bounded behavior is only the `/Subtype` role requirement when the stream dictionary already declares `/Type /Metadata`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/value reader, indirect object resolver, stream decoder, XMP packet parser, metadata review summarizer, Info fallback path, and WordPress smoke renderer. GPU/model execution, OCR, PDFium/PIL rendering, external PDF tools, JavaScript/action execution, live service tests, and exact upstream model parity remain intentionally out of scope.
