# markerPDF Embedded Files EOF Full Extractor Boundary Current Base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T115253Z`
Session: `port-dev-markerpdf-attachments-20260605T115253Z`
Base accepted HEAD: `186e05ffb048700eaab1327fa6ded3ac8809ef92`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through `pdftext.dictionary_output()` and PDF page text APIs; attachment payloads and FileSpec dictionaries are not promoted into visible text.
- This native no-GPU lane keeps attachment and EmbeddedFiles handling as WordPress review/import metadata. It must not execute Python, models, external PDF tools, attachment actions, or stale payload text.
- Terminal `%%EOF` bounds the active PDF byte range. Object-looking bytes appended after terminal EOF are outside the active document revision and must not replace the current `/Names /EmbeddedFiles` FileSpec or EmbeddedFile stream rows.

## Implementation

`PdfEmbeddedFileExtractor::extractEmbeddedFiles()` now trims input bytes through the terminal `%%EOF` before building its object inventory and selecting the catalog. This aligns the full embedded-file extractor with the lightweight `PdfAttachmentExtractor` preflight boundary added in the earlier EOF slice.

The focused fixture defines a current EmbeddedFiles name-tree FileSpec and stream before `%%EOF`, then appends same-object stale FileSpec and EmbeddedFile stream definitions after EOF. Before the fix, full extraction selected `post-eof-stale.csv`; after the fix it keeps `current-eof.csv`, current `/AFRelationship /Data`, current checksum match state, and current payload bytes. The lightweight attachment summary is asserted in the same focused case to prove both paths agree.

## Evidence

Red-first focused run after adding the test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileEofBoundaryCurrentBaseTest.php
```

Result: `1 test files, 4 assertions, 1 failures`; failure selected `post-eof-stale.csv` instead of `current-eof.csv`.

Focused run after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileEofBoundaryCurrentBaseTest.php
```

Result: `1 test files, 25 assertions, 0 failures`.

Adjacent family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: `3 test files, 883 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-embedded-file-eof-boundary-currentbase.php
```

Result: emits `embedded_file_count=1`, `attachment_count=1`, `filename=current-eof.csv`, `relationship=Data`, `terminal_eof_bounds_full_embedded_file_scan=true`, `terminal_eof_bounds_attachment_summary_scan=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

```bash
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfEmbeddedFileEofBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-embedded-file-eof-boundary-currentbase.php
```

Result: no syntax errors detected.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the earlier lightweight attachment preflight EOF slice, platform FileSpec filename selection, AFRelationship/checksum review, related-file review, name-tree limits pruning, portfolio/PieceInfo/XMP/OutputIntent attachment metadata, xref `/Prev` EmbeddedFiles selection, object-stream attachment rows, duplicate name-tree keys, or attachment mirror behavior. The new behavior is only terminal-EOF bounded object scanning in the full `PdfEmbeddedFileExtractor` path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, catalog EmbeddedFiles name-tree parser, stream decoder, checksum review, and existing WordPress smoke pattern. GPU/OCR/model execution and external PDF tools remain intentionally out of scope under the current markerPDF lane direction.
