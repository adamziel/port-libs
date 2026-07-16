# markerPDF Embedded Files Attachment Relationship Mirror Current Base

Session: `port-dev-markerpdf-attachments-20260608T122825Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T122825Z`
Base accepted HEAD: `03d7c4f1ec7ff6e233514aae2d1542db24fa965c`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible searchable-PDF text extraction on the `pdftext.dictionary_output()` / PDF page-text path. FileSpec attachment payloads are not visible body text, so the native PHP WordPress boundary is review metadata only: summarize attachment provenance and associated-file relationship state without executing Python, GPU models, external PDF tools, PDF actions, or embedded payloads.

PDF associated files use FileSpec `/AFRelationship` names to describe provenance. The native `PdfEmbeddedFileExtractor` already marked missing relationships in its provenance review, but the lightweight `PdfAttachmentExtractor::attachmentSummary()` path lost that status when an EmbeddedFiles name-tree attachment was later mirrored by catalog `/AF`.

## Implementation

`PdfAttachmentExtractor` now copies `relationship`, `relationship_role`, and `relationship_status` from catalog/page/annotation associated-file mirror candidates onto an existing EmbeddedFiles summary row when the embedded stream collapses to the same attachment.

When a summary row is known to be an associated file but no `/AFRelationship` is available, it now records:

- `relationship_status=missing_pdf_associated_file_relationship`

This preserves standard `/Source` provenance for mirrored catalog `/AF` FileSpecs and makes missing associated-file relationships explicit for WordPress review, while keeping attachment bytes out of `attachmentSummary()` rows.

## Red-First Evidence

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAssociatedRelationshipMirrorCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries associated-file relationship review through name-tree mirror summaries (lanes/markerpdf/tests/PdfAttachmentAssociatedRelationshipMirrorCurrentBaseTest.php)
Values are not identical
Expected: 'Source'
Actual: NULL

1 test files, 12 assertions, 1 failures
```

After the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAssociatedRelationshipMirrorCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries associated-file relationship review through name-tree mirror summaries

1 test files, 42 assertions, 0 failures
```

Adjacent attachment relationship/mirror checks:

```text
php tools/run-tests.php \
  lanes/markerpdf/tests/PdfAttachmentAssociatedRelationshipMirrorCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentAfRelationshipBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php \
  lanes/markerpdf/tests/PdfAttachmentExtractorTest.php

Focused test run: 5 selected test files (root lock skipped)
24 PASS lines
5 test files, 647 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-associated-relationship-mirror-currentbase.php
```

Passed. The smoke emits `relationship_statuses=["standard_pdf_associated_file_relationship","missing_pdf_associated_file_relationship"]`, `associated_file_sources=["catalog_af","catalog_af"]`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted attachment payload extraction, `/Params` checksum and size review, Mac resource-fork metadata, encrypted EFF redaction, duplicate FileSpec or EF key fail-closed behavior, PDF Portfolio collection metadata, FileAttachment annotation presentation, related-file pairs, name-tree ordering, xref repair, or the heavier `PdfEmbeddedFileExtractor` provenance tests. The bounded new behavior is only lightweight attachment-summary relationship metadata when catalog/page/annotation associated-file mirrors collapse onto an existing EmbeddedFiles name-tree attachment.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object/value parser, FileSpec parsing, EmbeddedFile stream decoding, attachment mirror dedupe, and WordPress smoke pattern. Full OCR, Surya/Texify/Torch, PDFium rendering, table model inference, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
