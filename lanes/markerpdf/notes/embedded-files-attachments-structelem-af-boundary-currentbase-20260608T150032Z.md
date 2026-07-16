# MarkerPDF StructElem Associated-File Attachment Boundary

Session: `port-dev-markerpdf-attachments-20260608T150032Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T150032Z`
Base accepted HEAD: `5630749445dec12d9837e4ce484cdb4300d60c36`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts searchable PDF text through `pdftext.dictionary_output()` and PDF page text APIs before model/OCR stages. Attachment payloads are not visible text. Under the current no-GPU scope, native PHP preflight owns PDF FileSpec and associated-file review metadata without executing Python, models, external PDF tools, annotation actions, or attachment payload text.

PDF 2.0 associated files can be attached to structure elements with `/AF`. This is relevant to tagged PDFs where a WordPress import should retain source/provenance attachments for a tagged region while keeping embedded XML payloads review-only.

## Implementation

- `PdfAttachmentExtractor` now scans the selected catalog `StructTreeRoot` `/K` tree for typed `/StructElem` dictionaries with well-formed `/AF` arrays.
- StructElem associated FileSpecs are added to lightweight `attachmentSummary()` rows with `structure_object_id`, `structure_role`, `structure_title`, `structure_associated_file_index`, and `structure_associated_file_source=structure_element_af`.
- Existing malformed `/AF` guards are reused: duplicate `/AF`, trailing operands after `/AF`, and malformed indirect `/AF` arrays are skipped before FileSpec review.
- `PdfEmbeddedFileExtractor` now includes the same StructElem `/AF` rows in full EmbeddedFiles inventory under `source=structure_element_associated_files`.
- Duplicate EmbeddedFiles mirrors now preserve StructElem association metadata when the same FileSpec is also present in a name tree/catalog/page path.

## Red-First Evidence

Before the source change, the new focused test failed because StructElem `/AF` rows were not visible to attachment preflight:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStructElemAssociatedFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries StructElem associated files into attachment summaries while rejecting malformed AF decoys (lanes/markerpdf/tests/PdfAttachmentStructElemAssociatedFileBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php

php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php

php -l lanes/markerpdf/tests/PdfAttachmentStructElemAssociatedFileBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentStructElemAssociatedFileBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-attachment-structelem-af-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-structelem-af-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStructElemAssociatedFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries StructElem associated files into attachment summaries while rejecting malformed AF decoys

1 test files, 57 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStructElemAssociatedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentAssociatedFileArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentIndirectAssociatedFileArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructTreeMarkedContentAssociatedFilesCurrentBaseTest.php lanes/markerpdf/tests/PdfPageThreadStructTreeAssociatedFileCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAssociatedFilesMarkedContentAltCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 1262 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-structelem-af-boundary-currentbase.php
```

Result: emits `attachment_count=1`, `embedded_file_count=1`, `source=structure-associated-file`, `structure_role=ArticleTitle`, `malformed_structelem_af_rejected=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted catalog `/AF`, page `/AF`, annotation `/AF`, FileAttachment annotation `/FS`, EmbeddedFiles name tree, Portfolio, PieceInfo, related-file, encrypted EFF, EOF/xref/object-stream, indirect `/AF` array, or metadata/page-property StructTree associated-file review slices. The bounded new behavior is only making typed StructElem `/AF` FileSpecs count as attachment/EmbeddedFiles review rows while preserving the malformed `/AF` boundary and payload exclusion.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF tokenizer, object/xref selection, structure tree traversal, FileSpec parsing, stream-filter decoding, checksum review, and existing WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.

Root harness: not run - isolated micro-slice.
