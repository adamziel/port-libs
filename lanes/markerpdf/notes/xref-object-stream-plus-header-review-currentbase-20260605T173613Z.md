# markerPDF xref object-stream plus-header review current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T173613Z`  
Session: `port-dev-markerpdf-object-xref-20260605T173613Z`  
Base accepted HEAD: `bcb14c0948d0135ec9c2e5e7666c4d8e81594f15`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF delegates searchable-PDF parsing to `pdftext`/PDFium-style PDF parser behavior before OCR/model stages. Under the current no-GPU scope, this lane owns native PHP parser behavior for xref-selected object streams, metadata, attachments, forms, outlines, and WordPress import review.
- PDF object-stream headers contain non-negative integer pairs. The visible-text parser already accepted leading `+` signs on non-negative header integers. This slice makes the same parser boundary apply to metadata, attachment, embedded-file, AcroForm, and outline review paths.

## Behavior

`PdfAttachmentExtractor`, `PdfEmbeddedFileExtractor`, `PdfMetadataExtractor`, `PdfAcroFormExtractor`, and `PdfOutlineExtractor` now accept `+` on non-negative object-stream header integer tokens while still rejecting negative tokens. The focused fixtures prove:

- an xref-selected compressed catalog whose header uses `+1 +0` supplies catalog language, page mode, and viewer-preference metadata;
- an xref-selected compressed FileSpec whose header uses `+4 +0` supplies WordPress attachment summary and embedded payload review;
- decoy compressed members and embedded payload bytes remain excluded from Gutenberg paragraph output and attachment summaries;
- no Python, model, OCR, PDFium, or external PDF tooling is executed.

## Red-First Evidence

Before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPlusHeaderReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL expands plus-signed object-stream header catalog metadata before WordPress review
Expected: array (0 => 'catalog')
Actual: array ()
FAIL expands plus-signed object-stream header FileSpec members before WordPress attachment review
Expected: 1
Actual: 0
1 test files, 6 assertions, 2 failures
```

## Verification

Focused plus-header test after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPlusHeaderReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS expands plus-signed object-stream header catalog metadata before WordPress review
PASS expands plus-signed object-stream header FileSpec members before WordPress attachment review
1 test files, 37 assertions, 0 failures
```

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamPlusHeaderReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSignedHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamAttachmentHeaderCommentCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLaterBadOffsetBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
6 PASS cases
4 test files, 149 assertions, 0 failures
```

Adjacent object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*.php lanes/markerpdf/tests/PdfParserObjectStream*.php lanes/markerpdf/tests/PdfObjectStream*.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPlusHeaderReviewCurrentBaseTest.php
Focused test run: 47 selected test files (root lock skipped)
47 test files, 973 assertions, 0 failures
```

Changed-helper regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
5 PASS cases
4 test files, 98 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-plus-header-review-currentbase.php
```

Smoke output includes `compressed_catalog_metadata_selected=true`, `compressed_filespec_selected=true`, `embedded_payload_available_to_attachment_review=true`, `payload_bytes_omitted_from_summary=true`, `decoy_member_excluded=true`, `catalog_selection_policy=explicit_member_index`, `filespec_selection_policy=explicit_member_index`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted visible-text plus-signed object-stream header parsing, header comment parsing, skipped zero object-number rows, incomplete headers, `/First` body-boundary rejection, literal/comment/nested member-offset rejection, duplicate offsets, zero-width index recovery, later bad offset slicing, stream-member rejection, object-stream carrier repair, xref-stream `/Prev` repair, or encrypted/security preflight work. The bounded behavior is only consistency for leading-plus non-negative object-stream header integer tokens across native WordPress review extractors.

## Dependency Closure

No new support component is needed. This reuses the native PHP xref-stream parser, object-stream decoder, metadata extractor, attachment and embedded-file extractors, AcroForm/outline review paths, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for the current no-GPU markerPDF lane.
