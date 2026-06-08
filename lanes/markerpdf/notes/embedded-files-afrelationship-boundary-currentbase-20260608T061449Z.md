# markerPDF embedded-files AFRelationship boundary current-base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T061449Z`
Base accepted HEAD: `1e4cd8062b7ccd6c5b3583fb768f31d2954dea93`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF object loading to pdftext/PDFium before conversion. In this no-GPU PHP lane, the native parser owns PDF FileSpec, EmbeddedFiles, and associated-file metadata boundaries before WordPress attachment import.

PDF `/AFRelationship` is the FileSpec field that tells importers whether an embedded file is a source, data, alternative, supplement, schema, form data, or other associated payload. Duplicate keys or trailing operands are ambiguous, so the WordPress review path should reject that FileSpec row rather than let a later relationship override a stale one.

## Implementation

- `PdfEmbeddedFileExtractor::FILE_SPEC_ATTACHMENT_BOUNDARY_KEYS` now includes `AFRelationship`.
- `PdfAttachmentExtractor::FILE_SPEC_ATTACHMENT_BOUNDARY_KEYS` now includes `AFRelationship`.
- `PdfEmbeddedFilesAttachmentAfRelationshipBoundaryCurrentBaseTest.php` covers both duplicate `/AFRelationship` and trailing-operand `/AFRelationship` FileSpec rows while preserving a clean sibling attachment.
- `wordpress-pdf-embedded-files-afrelationship-boundary-currentbase.php` emits a WordPress paragraph plus one file block for the clean Source attachment and records that ambiguous rows are suppressed.

## Evidence

Red-first after adding the focused test, before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentAfRelationshipBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects ambiguous FileSpec AFRelationship operands before embedded attachment review (lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentAfRelationshipBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 3

1 test files, 1 assertions, 1 failures
```

Green after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentAfRelationshipBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects ambiguous FileSpec AFRelationship operands before embedded attachment review

1 test files, 56 assertions, 0 failures
```

Adjacent focused coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes current xref-selected associated FileSpec AFRelationship and checksum review

1 test files, 46 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-embedded-files-afrelationship-boundary-currentbase.php
```

The smoke exits 0 and reports `attachment_count=1`, `relationship=Source`, `relationship_status=standard_pdf_associated_file_relationship`, `checksum_matches=true`, `summary_exposes_payload_bytes=false`, `ambiguous_rows_suppressed=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This slice does not repeat FileSpec checksum review, page-associated `/AF`, catalog `/Collection` schema, PieceInfo/private streams, encrypted EFF preflight, related-file `/RF`, EF key fallback, duplicate filename keys, name-tree node operand boundaries, xref generation repair, runtime preflight, OCR/model, or raster behavior.

The bounded behavior is only FileSpec `/AFRelationship` duplicate/trailing operand fail-closed handling in the EmbeddedFiles and lightweight attachment review paths.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, FileSpec dictionary boundary scanner, EmbeddedFiles extractor, attachment summary path, text extractor, and WordPress smoke renderer. Full upstream parity for pdftext, pypdfium2/PDFium rendering, Surya/Torch OCR/layout/table models, Texify, Streamlit/FastAPI workers, benchmark model downloads, and external PDF tools remains intentionally out of scope for this no-GPU markerPDF slice.
