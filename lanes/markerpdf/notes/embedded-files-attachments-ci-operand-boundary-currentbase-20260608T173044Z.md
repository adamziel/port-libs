# markerPDF Embedded Files Attachment CI Operand Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260608T173044Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T173044Z`
Base accepted HEAD: `bb0155ef4ba8e70b3abc02eb190fa91b5dd44102`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through `pdftext.dictionary_output()` and PDF page text APIs; FileSpec collection metadata and embedded attachment payloads are not executed or promoted into visible page text. In the native no-GPU PHP lane, PDF Portfolio `/CI` collection-item data is review metadata for WordPress attachment import only.

## Behavior

This patch adds a bounded malformed FileSpec `/CI` operand boundary:

- duplicate or trailing top-level `/CI` operands on a FileSpec now set `portfolio_item_status=malformed_filespec_collection_item_omitted`;
- ambiguous `/CI` collection-item fields are omitted from both `PdfAttachmentExtractor` preflight rows and `PdfEmbeddedFileExtractor` rows;
- the underlying embedded attachment remains reviewable with filename, description, relationship, byte count, checksum, and safe file-derived collection schema fields such as `Desc`, `Size`, and `ModDate`;
- valid sibling `/CI` collection items still produce Portfolio item and `portfolio_field_values` metadata;
- raw embedded payload bytes remain absent from lightweight WordPress preflight summaries.

Red-first evidence: the focused test initially failed because malformed duplicate `/CI` operands produced no malformed status and stale collection-item metadata was still parseable:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentCollectionItemOperandBoundaryCurrentBaseTest.php
FAIL omits malformed FileSpec collection item operands while preserving attachment payload review
Expected: 'malformed_filespec_collection_item_omitted'
Actual: NULL
1 test files, 20 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentCollectionItemOperandBoundaryCurrentBaseTest.php
PASS omits malformed FileSpec collection item operands while preserving attachment payload review
1 test files, 92 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentCollectionItemOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentCollectionItemBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentPortfolioCollectionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentPortfolioFolderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataKeyOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
8 test files, 1256 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-ci-operand-boundary-currentbase.php
exits 0; emits bad_ci_subject_omitted=true, valid_ci_status=status:ready, payloads_omitted_from_summary=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted FileSpec `/FS`/`/ID`/`/V` metadata review, valid `/CI` Portfolio item extraction, Portfolio collection schema/sort/folder metadata, PieceInfo private-stream review, attachment Mac Params, related-file metadata, encrypted EFF/related-file redaction, FileSpec duplicate attachment-key rejection, EmbeddedFile `/Params` duplicate/trailing operand rejection, name-tree ordering/limits, xref repair, or stream-filter boundaries. The new behavior is only malformed duplicate/trailing FileSpec `/CI` operand suppression while retaining the attachment row.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF tokenizer/object parser, dictionary duplicate/trailing operand guards, EmbeddedFiles/FileSpec extraction, Portfolio collection review helpers, stream filter decoding, and WordPress smoke pattern. GPU/OCR/model execution, PDFium rendering, Surya/Texify/Torch workers, and exact upstream model benchmark parity remain intentionally out of scope for this markerPDF lane.
