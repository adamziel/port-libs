# markerPDF Embedded Files Attachment Mac Params Current Base

Session: `port-dev-markerpdf-attachments-20260605T183549Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T183549Z`
Base accepted HEAD: `e85b68b3ad66391e6ab52eac56d93e08a3705d7b`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through `pdftext.dictionary_output()` and PDF page text APIs in `marker/pdf/extract_text.py`; embedded-file payloads and FileSpec dictionaries are not part of that visible text path:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDF EmbeddedFile stream `/Params` may carry a `/Mac` dictionary with classic Mac OS file type, creator, and resource-fork stream metadata. In this no-GPU native PHP lane, the WordPress boundary is review metadata only: summarize type/creator/resource-fork metadata without executing Python, models, external PDF tools, or promoting resource-fork payload bytes into visible text or attachment summaries.

## Behavior

`PdfAttachmentExtractor` now exposes EmbeddedFile `/Params /Mac` review metadata on primary attachment rows and related-file rows:

- `/Subtype` and `/Creator` integer values are normalized to integer, lowercase hex, and printable four-character codes when available;
- `/ResFork` is summarized as review-only stream metadata with object id, content type, byte length, sha256, declared-size match, checksum match, and dates;
- resource-fork bytes are never exposed in `attachmentSummary()` rows, and encrypted attachment policies keep resource forks suppressed with no raw encrypted bytes exposed.

`PdfEmbeddedFileExtractor` now carries the same `/Mac` metadata in extracted file metadata while keeping resource-fork content out of the returned file payload list. The main embedded file content remains extractable; the resource fork is metadata only.

## Evidence

Red-first focused run after adding the test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentMacParamsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL summarizes EmbeddedFile Mac Params resource-fork metadata without payload leakage: Condition is not true
1 test files, 17 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentMacParamsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes EmbeddedFile Mac Params resource-fork metadata without payload leakage
1 test files, 63 assertions, 0 failures
```

The WordPress smoke `wordpress-pdf-attachment-mac-params-currentbase.php` verifies `mac_file_type=TEXT`, `mac_creator=MPRT`, `resource_fork_checksum_matches=true`, `resource_fork_payload_omitted=true`, visible text remains `Mac Params Attachment Body`, and no Python/model/external PDF tooling executes.

Final focused verification:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php

php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php

php -l lanes/markerpdf/tests/PdfAttachmentMacParamsBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentMacParamsBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-attachment-mac-params-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-mac-params-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentMacParamsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDecodedLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFilePathBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1081 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-attachment-mac-params-currentbase.php
Emitted mac_file_type=TEXT, mac_creator=MPRT, resource_fork_checksum_matches=true, resource_fork_payload_omitted=true, executes_python_or_models=false, executes_external_pdf_tools=false.

php -r 'foreach (["lanes/markerpdf/lane-status.json"] as $file) { json_decode((string) file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'
lanes/markerpdf/lane-status.json: valid JSON

git diff --check -- lanes/markerpdf
passed with no output
```

## Non-Overlap

This does not repeat accepted EmbeddedFiles `/Limits`, PDFDocEncoding names, `/DL`, `/AFRelationship`, platform FileSpec `/Mac` filename selection, direct FileSpec mirror dedupe, encrypted EFF, related-file path, portfolio collection, PieceInfo, annotation presentation, xref repair, or EOF-bounded attachment scanning slices. The new boundary is specifically EmbeddedFile stream `/Params /Mac` file-info and resource-fork review metadata.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object/value parser, FileSpec parsing, EmbeddedFile stream decoding, checksum review, and existing WordPress smoke pattern. Full OCR, Surya/Texify/Torch, PDFium rendering, and model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
