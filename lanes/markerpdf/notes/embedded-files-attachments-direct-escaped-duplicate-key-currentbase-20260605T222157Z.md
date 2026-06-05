# markerPDF Embedded Files Attachment Direct Escaped Duplicate Key Current Base

Session: `port-dev-markerpdf-attachments-20260605T222157Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T222157Z`
Base accepted HEAD: `59b7ab61f9bf14128c46b8fe48f28d13d62b387f`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through `pdftext.dictionary_output()` and PDF page text APIs before downstream WordPress conversion. Embedded-file payloads and FileSpec dictionaries are attachment review inputs, not visible document text:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDF name objects decode `#xx` hex escapes before key comparison. A direct inline FileSpec in `/Names /EmbeddedFiles` with `/F` plus `/#46`, or `/EF << /F ... /#46 ... >>`, is therefore a duplicate filename or embedded-file reference key and must fail closed before WordPress attachment preflight.

## Behavior

`PdfAttachmentExtractor` now carries the raw FileSpec operand from EmbeddedFiles name-tree pairs into `attachmentFromFileSpecValue()`. Direct inline FileSpec dictionaries now receive the same decoded duplicate-key guard as indirect FileSpec objects before parsed metadata can select a stale filename or stale `/EF` stream.

The parsed valid-attachment path is unchanged: a following valid direct inline FileSpec still resolves the `/F` stream, checksum state, MIME type, relationship role, and byte-length summary while omitting raw payload bytes from WordPress review output.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on direct inline FileSpec escaped duplicate keys before WordPress attachment preflight (lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 3

1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on direct inline FileSpec escaped duplicate keys before WordPress attachment preflight

1 test files, 45 assertions, 0 failures
```

Adjacent attachment gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDuplicateFileSpecKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1012 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-direct-escaped-duplicate-key-currentbase.php
```

The smoke exits `0` and emits `attachment_count=1`, `embedded_file_count=1`, `filename=valid-inline.xml`, `relationship=Data`, `escaped_duplicate_filespec_rejected=true`, `escaped_duplicate_ef_rejected=true`, `payload_omitted_from_summary=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php

php -l lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-attachment-direct-escaped-duplicate-key-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-direct-escaped-duplicate-key-currentbase.php
```

## Non-Overlap

This does not repeat accepted indirect duplicate FileSpec/EF key rejection, duplicate EmbeddedFiles name-tree key ordering, direct nameless FileSpec mirror dedupe, platform filename path review, PDFDocEncoding names, `/DL`, `/AFRelationship`, encrypted EFF, related-file path/name pairs, portfolio collection, PieceInfo, annotation presentation, stream-filter stack, xref generation repair, or EOF-bounded attachment scanning. The bounded behavior is only direct inline EmbeddedFiles FileSpec operands whose escaped PDF names decode to duplicate `/F` keys before WordPress attachment preflight.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object/value tokenizer, name-tree walker, decoded PDF-name duplicate-key guard, FileSpec parser, EmbeddedFile stream decoder, attachment summary renderer, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch, PDFium rendering, external PDF tools, decryption, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
