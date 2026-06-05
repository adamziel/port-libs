# markerPDF EmbeddedFiles Attachment Portfolio Folder Boundary

Session: `port-dev-markerpdf-attachments-20260605T225819Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T225819Z`
Base accepted HEAD: `d7b71434dec2c6a757eb3d2214aee89ec790a158`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps attachment/artifact metadata outside visible Markdown text: searchable text is extracted through PDF parser paths before OCR/model fallback, while output artifacts and metadata are separate review surfaces. In the native no-GPU PHP lane, PDF Portfolio `/Collection` and `/EmbeddedFiles` attachment metadata are therefore parser/review boundaries before WordPress import.

This slice maps the PDF Portfolio `/Collection /Folders` attachment boundary. Folder dictionaries describe attachment organization and may reference FileSpecs, child folders, siblings, dates, and private data. The native preflight must carry bounded folder navigation metadata for review without promoting attachment payloads or folder-private streams into Gutenberg text.

## Implementation

- `PdfAttachmentExtractor` now carries bounded `/Collection /Folders` metadata into lightweight attachment summaries:
  - folder object id, depth, sibling index, child/next folder references;
  - folder name, description, id, creation/modification dates, and UTC-normalized date review;
  - folder `/F` FileSpec references with filename/path review and relationship/description metadata;
  - cycle/depth protection and `payload_bytes_included=false`.
- `PdfEmbeddedFileExtractor` mirrors the same folder-tree metadata in full embedded-file review rows.
- `wordpress-pdf-attachment-portfolio-folder-boundary-currentbase.php` demonstrates a WordPress-safe portfolio import list with three folders and no payload leakage.

The focused fixture includes a root folder, a child folder, a sibling folder, a deliberate sibling-child cycle back to the root, two embedded FileSpecs, a string-only external folder file pointer, and a folder-private metadata stream decoy.

## Verification

Syntax checks:

```sh
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentPortfolioFolderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-portfolio-folder-boundary-currentbase.php
```

All reported `No syntax errors detected`.

Focused new test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPortfolioFolderBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS carries bounded Portfolio Collection folder tree metadata into attachment review

1 test files, 59 assertions, 0 failures
```

Focused family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPortfolioFolderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentPortfolioCollectionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Result:

```text
3 test files, 558 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-attachment-portfolio-folder-boundary-currentbase.php
```

Result: emitted `folder_count=3`, `folder_names=["Exports","Reports","Archive"]`, `cycle_guard_keeps_three_rows=true`, `summary_payloads_omitted=true`, `embedded_file_private_folder_payload_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Portfolio `/Collection` schema/sort propagation, FileSpec `/CI` field-value review, page/catalog `/AF` extraction, FileAttachment annotation mirrors, platform `/EF` key selection, related-file `/RF`, encrypted `/EFF`, PieceInfo/XMP/OutputIntent review, object-stream/xref attachment repair, date UTC review, Mac Params resource forks, duplicate FileSpec key rejection, or runtime chunk conversion preflight. The bounded new behavior is only `/Collection /Folders` folder-tree review metadata for attachments.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, FileSpec filename/path review, attachment payload suppression, date normalization, and WordPress smoke pattern. GPU/model execution, PDFium rendering, OCR/model table recognition, Surya/Texify/Torch, and exact upstream model benchmark parity remain intentionally out of scope for this markerPDF no-GPU slice.
