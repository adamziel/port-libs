# markerPDF Embedded Files Attachment Indirect Params Object Current Base

Session: `port-dev-markerpdf-attachments-20260608T191537Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T191537Z`
Base accepted HEAD: `d057fc34a05090199b091f73d0a8aa3124240396`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable-PDF text through `pdftext.dictionary_output()` and PDF page text APIs in `marker/pdf/extract_text.py`; embedded-file payloads and FileSpec dictionaries are outside that visible text path. The native PHP no-GPU boundary keeps attachment payload streams and FileSpec/EmbeddedFile metadata as review data for WordPress import, not Gutenberg paragraph text.

PDF EmbeddedFile stream `/Params` dictionaries carry trusted review metadata such as `/Size`, `/CheckSum`, `/CreationDate`, and `/ModDate`. When `/Params` is an indirect object, the referenced helper must resolve to exactly one dictionary; a dictionary prefix followed by a top-level operand such as `<< ... >> 99 0 R` is ambiguous and now fails closed before attachment checksum/date/size review.

## Implementation

- `PdfAttachmentExtractor` now resolves referenced `/Params` helper objects through an exact-dictionary boundary before checking duplicate or trailing `/Size`, `/CheckSum`, `/CreationDate`, `/ModDate`, and `/Mac` metadata keys.
- `PdfEmbeddedFileExtractor` applies the same exact-dictionary boundary so full embedded-file rows and WordPress attachment summaries agree.
- Invalid tailed primary and related-file `/Params` helper objects are excluded, while sibling valid primary and related attachment rows preserve filename, relationship, size, checksum, and date review metadata.
- Attachment payload bytes remain omitted from WordPress preflight summaries, and embedded payload text remains excluded from visible PDF text extraction.

## Red/Green Evidence

Pre-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectParamsObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects indirect EmbeddedFile Params helper objects with trailing operands before attachment review
Values are not identical
Expected: 2
Actual: 3

1 test files, 1 assertions, 1 failures
```

Post-fix focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectParamsObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects indirect EmbeddedFile Params helper objects with trailing operands before attachment review

1 test files, 87 assertions, 0 failures
```

Adjacent attachment regression run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectParamsObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentParamsDuplicateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentParamsScalarDuplicateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentParamsTrailingOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentStreamOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
...
8 test files, 1339 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-indirect-params-object-currentbase.php
```

The smoke exits 0 and emits a paragraph plus a file block with `attachment_count=2`, `filenames=["valid-indirect-params.xml","related-primary-params.xml"]`, `related_filename="valid-related-params.json"`, `bad_primary_indirect_params_excluded=true`, `bad_related_indirect_params_excluded=true`, `attachment_payloads_omitted_from_summary=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted EmbeddedFiles name-tree extraction, catalog/page/annotation/structure `/AF`, FileAttachment annotation mirrors, FileSpec `/AFRelationship`, duplicate direct `/Params`, duplicate scalar `/Params`, direct `/Params` trailing operands, `/DL` operand boundaries, direct stream dictionary operand boundaries, stream filter predictor boundaries, related-file path/name-pair handling, portfolio/PieceInfo metadata, encrypted EFF redaction, Mac resource-fork review, xref repair, or EOF-bounded attachment scanning. The new boundary is specifically tailed indirect helper objects referenced by EmbeddedFile stream `/Params`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, value parser, exact dictionary boundary helpers, EmbeddedFile stream review, checksum/date metadata review, related-file extraction, fallback text exclusion, and WordPress smoke pattern. Full OCR, Surya/Texify/Torch, PDFium rendering, live model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
