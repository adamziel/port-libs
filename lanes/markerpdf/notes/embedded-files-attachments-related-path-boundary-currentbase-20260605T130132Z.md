# markerPDF Embedded Files Attachment Related Path Boundary Current Base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T130132Z`

Base accepted HEAD: `4d32467895d9da3885ac59c6f3eee2fa22771330`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` promotes visible searchable PDF text through the `pdftext`/PDF page-text path before OCR/layout/model stages. FileSpec attachment payloads and related-file payloads are not visible text inputs. For the native no-GPU PHP boundary, `/Filespec /RF` related-file filename-stream pairs are attachment review metadata for WordPress import, not local output paths and not promoted body text.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now apply the existing FileSpec path classifier to related-file names from `/RF` filename-stream pairs:

- `related_filename_leaf`;
- `related_filename_storage_name`;
- `related_filename_path_status`;
- `related_filename_has_path_segments`;
- `related_filename_contains_parent_segment`;
- `related_filename_absolute_path`;
- `related_filename_url_scheme`.

The raw `related_filename` is preserved for review, while WordPress import code gets a basename-only storage candidate. Related payload bytes remain excluded from attachment summaries and embedded-file related rows. Encrypted FileSpec string redaction also removes the new `related_filename_*` metadata when strings are not available without decryption.

The focused fixture covers a primary `source.xml` FileSpec with `/RF << /F [(../private/review-notes.txt) 12 0 R] /UF [(https://example.test/download/private/manifest.json?token=secret) 13 0 R] >>`. The expected review rows report `review-notes.txt` and `manifest.json` as safe storage names, classify parent-segment and URL path status, keep MD5 checksum review state, and omit the related payload bytes from JSON summaries.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentRelatedFilePathBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL adds safe basename review for path shaped FileSpec related filenames (lanes/markerpdf/tests/PdfAttachmentRelatedFilePathBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 'review-notes.txt'
Actual: NULL

1 test files, 10 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentRelatedFilePathBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS adds safe basename review for path shaped FileSpec related filenames

1 test files, 50 assertions, 0 failures
```

Focused attachment/embedded family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Attachment|EmbeddedFile)' | sort)
Focused test run: 19 selected test files (root lock skipped)
...
19 test files, 1715 assertions, 0 failures
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentRelatedFilePathBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachment-related-file-path-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentRelatedFilePathBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-related-file-path-boundary-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-related-file-path-boundary-currentbase.php
```

Result: emitted `related_filename_storage_names=["review-notes.txt","manifest.json"]`, `related_path_statuses=["relative_path_segments_review_only","url_path_review_only"]`, `related_payload_bytes_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted main FileSpec path basename review, platform-matched `/EF` key selection, `/AFRelationship` role mapping, catalog/page `/AF` mirror marking, related-file checksum review, encrypted related-file payload suppression, EOF-bounded attachment scanning, decoded-length review, Portfolio/Collection metadata, PieceInfo private-stream provenance, or xref attachment selection. The new behavior is only basename/path metadata for `/RF` related-file filename-stream pairs in the attachment and embedded-file review APIs.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF object parser, FileSpec dictionary parser, embedded-stream decoder, filename path classifier, checksum review, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch models, PDFium rendering, payload text promotion, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
