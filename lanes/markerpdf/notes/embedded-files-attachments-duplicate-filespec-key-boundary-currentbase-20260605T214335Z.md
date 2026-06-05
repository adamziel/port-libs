# markerPDF Embedded Files Attachment Duplicate FileSpec Key Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260605T214335Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T214335Z`
Base accepted HEAD: `66d0408b47061a698c7ebd40ce9acc8de4ae0df1`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `pdftext.dictionary_output()` and PDF page-text APIs. Embedded-file payload bytes are not a visible text source.
- PDF attachment preflight in this native no-GPU lane summarizes FileSpec and EmbeddedFile metadata for WordPress review without executing Python, models, external PDF tools, PDF actions, or attachment payloads.
- PDF dictionaries are key maps. Duplicate FileSpec filename keys or duplicate `/EF` embedded-file stream keys make the attachment identity or stream source ambiguous, so review preflight now fails that FileSpec closed instead of accepting the parser's last key.

## Implementation

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now reject ambiguous FileSpec rows when top-level FileSpec identity keys (`/F`, `/UF`, `/DOS`, `/Unix`, `/Mac`, `/EF`) or nested `/EF` stream keys are duplicated. The lightweight extractor uses a bounded raw top-level dictionary scanner for indirect FileSpecs and EF dictionaries before building summary rows. The full embedded-file extractor applies the same duplicate-key policy to raw FileSpec bodies, including inline direct dictionaries.

The focused fixture includes two malformed attachments:

- object `10 0 R` has duplicate FileSpec `/F` filename keys;
- object `20 0 R` has duplicate nested `/EF /F` stream keys.

A separate valid object `30 0 R` remains importable. The expected WordPress review summary contains only `valid-source.xml`, excludes both stale payloads and checksums, and leaves visible page text free of attachment payload XML.

## Verification

Red-first focused command before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDuplicateFileSpecKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on duplicate FileSpec filename or EF keys before attachment preflight (lanes/markerpdf/tests/PdfAttachmentDuplicateFileSpecKeyBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 3

1 test files, 1 assertions, 1 failures
```

Focused command after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDuplicateFileSpecKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate FileSpec filename or EF keys before attachment preflight

1 test files, 43 assertions, 0 failures
```

Attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDuplicateFileSpecKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFile*CurrentBaseTest.php
Focused test run: 30 selected test files (root lock skipped)
30 test files, 2340 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-duplicate-filespec-key-boundary-currentbase.php
```

Result: emitted `attachment_count=1`, `embedded_file_count=1`, `duplicate_filespec_key_rejected=true`, `duplicate_ef_key_rejected=true`, `valid_attachment_preserved=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted EmbeddedFiles name-tree duplicate-key pruning, malformed-first duplicate name-tree continuation, platform `/EF` key selection, indirect name-tree keys, direct FileSpec mirror dedupe, EOF-bounded object scanning, generation-exact references, object-stream attachment recovery, encrypted EFF preflight, DecodeParms/filter-stack attachment boundaries, related-file review, portfolio/PieceInfo attachment metadata, or page/FileAttachment annotation mirrors. The bounded behavior here is only duplicate identity keys inside a single FileSpec or its nested `/EF` dictionary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, raw dictionary scanning, FileSpec parsing, stream decoding, full embedded-file extraction, lightweight attachment summary, and existing WordPress smoke pattern. Full markerPDF OCR/model parity remains intentionally out of scope under the current no-GPU direction.
