# EmbeddedFiles Attachment Stream Operand Boundary

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T052619Z`

Base accepted HEAD: `c162e5af21915b05e444923d010d6e56dffee14f`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF extraction and attachment handling to PDF parser dependencies before model/OCR handoff. Under the no-GPU markerPDF scope, this slice maps the native PDF parser boundary for catalog `/Names /EmbeddedFiles` FileSpec streams: malformed embedded-file stream dictionaries with stray top-level operands after `/Params` or `/DecodeParms` are rejected before WordPress attachment review or payload extraction.

## Behavior

- `PdfAttachmentExtractor` now treats trailing operands after embedded-file stream `/Filter`, `/DecodeParms`, or `/Params` boundary keys as fail-closed, matching the existing duplicate-key and malformed Params checks.
- `PdfEmbeddedFileExtractor` applies the same top-level stream boundary check for both clear payload extraction and encrypted review-only stream rows.
- Valid sibling attachments remain importable and catalog `/AF` mirrors still mark the valid EmbeddedFiles row as associated-file review metadata.
- Malformed attachment filenames, descriptions, checksums, and XML payloads stay out of attachment summaries, embedded-file payload rows, and visible WordPress text.

## Red/Green Evidence

Red-first command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentStreamOperandBoundaryCurrentBaseTest.php
```

Failed before the source fix with `Expected: 1 Actual: 3`, proving both malformed stream dictionaries were accepted as attachment rows.

Focused green command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentStreamOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 66 assertions, 0 failures`.

Attachment family command:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Attachment|EmbeddedFile|EmbeddedFiles).*Test\.php$' | sort)
```

Result: `58 test files, 4341 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-operand-boundary-currentbase.php
```

Result: exits `0` and reports `attachment_count=1`, `filenames=["valid-stream-boundary.xml"]`, `malformed_streams_excluded=true`, `summary_exposes_attachment_bytes=false`, `embedded_file_payload_available=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

```bash
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentStreamOperandBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-stream-operand-boundary-currentbase.php
```

Result: no syntax errors detected in all changed PHP files.

Diff whitespace:

```bash
git diff --check -- lanes/markerpdf
```

Result: exits `0`.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF dictionary parser, stream decoder, EmbeddedFiles FileSpec extraction, attachment summary preflight, and WordPress smoke harness. GPU/model execution, OCR, raster rendering, pypdfium/PDFium, Python, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted attachment name-tree ordering, duplicate FileSpec keys, EF/RF trailing operands, duplicate Params dictionaries, Params scalar duplicates, Params dictionary trailing operands, platform EF key selection, stream filter stacks, DecodeParms predictor handling, encrypted EFF policy, page/catalog associated files, annotation FileAttachment mirrors, xref attachment repair, metadata associated-file provenance, fonts, CMaps, xref Prev-chain repair, table geometry, or model/OCR behavior. The bounded behavior is only top-level embedded-file stream dictionary trailing operands after `/Params` or `/DecodeParms` before attachment import.
