# markerPDF xref Prev chain plus-signed attachment size current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T001807Z`
Base accepted HEAD: `f52b2d5079c4d5ea31714d32add9d4f1c34a68d9`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF object loading to pdftext/PDFium before conversion. In this no-GPU PHP lane, the native parser owns xref `/Prev` traversal and embedded-file metadata before WordPress attachment import.

PDF numeric tokens can carry a leading plus sign. A current xref-stream `/Prev +N` section may select current EmbeddedFiles rows while the embedded-file stream's `/Params /Size +N` declares the payload size. The imported attachment should preserve the current file and declared-size review metadata, not drop the declared size or fall back to stale previous attachments.

## Implementation

- `PdfEmbeddedFileExtractor::dictionaryIntegerValue()` now accepts `[+-]?\d+`, matching the signed integer handling already used by the metadata and text parser paths.
- `PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php` adds a current embedded-file fixture with `/Prev +...` and `/Params << /Size +59 >>`.
- `wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php` reports the plus-signed `/Prev` attachment import and plus-signed declared-size preservation in its WordPress smoke metadata.

## Evidence

Red-first after adding the focused assertions, before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs embedded-file imports when xref-stream Prev uses a plus-signed integer operand
Expected: 59
Actual: NULL
1 test files, 487 assertions, 1 failures
```

Green after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
27 PASS cases
1 test files, 500 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

The smoke reports `embedded_file_plus_signed_declared_size_selected=true`, `attachment_preflight_plus_signed_declared_size_selected=true`, `plus_signed_xref_prev_operand_used_for_attachment_import=true`, `embedded_file_stale_prev_attachment_excluded=true`, `attachment_preflight_stale_prev_attachment_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat damaged xref-row offset repair, stale explicit-offset repair, wrong current-offset owner repair, omitted current-row graph repair, classic table `/Prev` repair, indirect/compressed `/Prev` helpers, sparse latest `/Root` or `/Info` inheritance, latest free-row suppression, encryption inheritance, object-stream carrier repair, or live OCR/model/PDFium execution.

The bounded behavior here is only plus-signed integer parsing in the xref `/Prev` embedded-file import path and the embedded stream `/Params /Size` review metadata that reaches WordPress attachment summaries.

## Dependency closure

No new support component is needed. This reuses the native PHP xref stream parser, `/Prev` chain walker, embedded-file extractor, attachment summary path, and WordPress smoke renderer. Full upstream parity for pdftext, pypdfium2/PDFium rendering, Surya/Torch OCR/layout/table models, Texify, Streamlit/FastAPI workers, benchmark model downloads, and external PDF tools remains intentionally out of scope for this no-GPU markerPDF slice.
