# markerPDF EmbeddedFile Params Date UTC Boundary

Session: `port-dev-markerpdf-attachments-20260605T210814Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T210814Z`
Base accepted HEAD: `c5e69f53fca004ec586e1db1a9d5907356a992ee`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF extraction through pdftext/PDFium-style page text boundaries; embedded attachment payload streams are review metadata, not visible paragraph text.
- PDF EmbeddedFile `/Params` dictionaries carry `/CreationDate` and `/ModDate` review metadata. WordPress import preflight needs raw values plus UTC-normalized review fields only when the date has an explicit timezone; timezone-free PDF dates stay raw-only to avoid false precision.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDateUtcBoundaryCurrentBaseTest.php
```

Failed before the source change:

```text
1 test files, 12 assertions, 1 failures
```

The missing field was `created_at_utc` on the canonical attachment row for an EmbeddedFile `/Params /CreationDate` with an explicit PDF timezone offset.

## Implementation

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now preserve raw EmbeddedFile `/Params /CreationDate` and `/ModDate` values while adding `created_at_utc` and `modified_at_utc` only when the date has an explicit timezone.

- Primary EmbeddedFiles name-tree rows, related-file `/RF` rows, and Mac resource-fork rows use the same UTC review boundary.
- Timezone-free attachment dates remain raw-only.
- Encrypted payload redaction removes the new UTC fields together with raw date metadata.
- Attachment summaries still omit payload bytes and do not execute Python, models, or external PDF tools.

## Verification

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDateUtcBoundaryCurrentBaseTest.php
```

Passed:

```text
1 test files, 48 assertions, 0 failures
```

Attachment-family regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDateUtcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentPortfolioCollectionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentCollectionItemBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFileNamePairBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedEmbeddedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEncryptedRelatedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentMacParamsBoundaryCurrentBaseTest.php
```

Passed:

```text
10 test files, 1360 assertions, 0 failures
```

Wider attachment/current-base sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFile*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Passed:

```text
28 test files, 1843 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-date-utc-boundary-currentbase.php
```

The smoke emitted `attachment_created_at_utc=2026-06-06T05:45:30Z`, `attachment_modified_at_utc=2026-06-05T19:30:30Z`, `related_created_at_utc=2026-06-05T23:00:00Z`, `timezone_free_related_moddate_raw_only=true`, `payload_bytes_omitted_from_summary=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted EmbeddedFiles extraction, catalog/page `/AF`, FileAttachment annotations, FileSpec `/FS`/`/ID`/`/V` metadata, `/AFRelationship` role mapping, checksum review, related-file `/RF` name pairs, EOF/xref/object-stream attachment selection, encrypted EFF redaction, portfolio/PieceInfo/XMP/OutputIntent metadata, Mac resource-fork metadata, or document XMP/Info date normalization. The bounded new behavior is only EmbeddedFile `/Params` date UTC review fields for attachment and embedded-file rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, EmbeddedFile stream metadata extraction, attachment summary redaction, and existing document-date UTC normalization semantics. GPU/model execution, PDFium rendering, live OCR, Surya/Texify/Torch model paths, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
