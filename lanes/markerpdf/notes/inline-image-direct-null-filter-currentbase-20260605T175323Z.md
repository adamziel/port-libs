# Inline image direct null Filter current-base boundary

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T175323Z`
Base: `f0c994757ade1bf76847121ddfe3ea0faea2f48b`

## Source truth

PDF dictionary entries whose value is `null` are absence-equivalent for this bounded parser path. Array-form null filter entries were already normalized for inline-image decode boundaries; this slice extends the same behavior to scalar `/Filter null` inline image operands in the native image renderer.

## Behavior

- `PdfImageRenderer` now treats scalar `/Filter null` as no concrete filter instead of a malformed filter operand.
- The focused fixture keeps a false `EI` token inside the required raw sample bytes, preserving text extraction boundaries while preview metadata reports an unfiltered decoded stream.
- No OCR, Python, PDFium, model, or external PDF tooling is used.

## Red-first evidence

Before the source change, `PdfImageRenderer::inlineImageColorSpaceMaskOutputPreviewRows('/F null', ...)` threw `InvalidArgumentException: Inline image prefix filters must be complete before output preview.`

## Focused verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` -> `1 test files, 485 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php` -> `1 test files, 516 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` -> emits `direct_null_filter_payload_excluded_until_sample_floor=true`, `direct_null_filter_treated_as_unfiltered=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`

## Dependency closure

No new support component is needed. This reuses the existing native PHP inline-image tokenizer, filter normalization, and image preview helpers.

## Non-overlap

Avoids the accepted inline-image array null-filter DecodeParms, native filter EOD surplus, Identity Crypt, malformed DecodeParms, and preview-only JPX/JBIG2/CCITT/DCT clusters. This owns only scalar direct `/Filter null` inline-image renderer normalization and its raw sample boundary smoke.
