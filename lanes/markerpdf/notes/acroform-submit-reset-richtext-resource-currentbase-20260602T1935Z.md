# markerPDF AcroForm Submit/Reset Rich Text Resource Current Base

Session: `port-dev-markerpdf-form41pdf-20260602T1935Z`
Micro-slice: `acroform-submit-reset-richtext-resource-currentbase`
Base accepted HEAD: `2a344ae8c1b485daa88b3fe8a487f8ab30d2feff`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; its PDF text path delegates extraction to pdftext/PDFium boundaries, so this PHP lane keeps AcroForm actions, rich text, default styles, and appearance resources as review metadata instead of executable or visible text.
- PDF SubmitForm flags include XFDF, SubmitPDF, IncludeAnnotations, CanonicalFormat, ExclFKey, and EmbedForm style policy bits. The PHP port records those requested semantics, but WordPress import does not execute SubmitForm/ResetForm actions or submit PDF/annotation/default-resource payloads.
- AcroForm variable text fields can carry `/RV` rich text and `/DS` default style strings; default appearance `/DA` can resolve font names through AcroForm `/DR /Font`. These are useful review metadata, not imported Gutenberg paragraph text or CSS.

## Implemented

- `PdfAcroFormExtractor` now decodes the broader SubmitForm flag set and exposes requested submit format/policy booleans while keeping submit, annotation, PDF, and embedded-form execution flags false.
- SubmitForm and ResetForm `field_value_review.field_rows` now include `appearance_resource_review` summaries for field and widget `/DA` resources resolved through `/DR`, including font object, base font, encoding, descriptor, widget appearance provenance, and explicit non-rendering/non-execution flags.
- `PdfActionReviewExtractor` now mirrors the same SubmitForm flag decoding for shared annotation/action review metadata.
- Added a focused current-base test and WordPress smoke proving `/V` remains authoritative, `/RV` and `/DS` stay review-only, no-export fields are excluded from submit review, reset restores `/DV`, and resource/action payloads do not leak into visible text.

## Verification

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/src/PdfActionReviewExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormSubmitResetRichTextResourceCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-submit-reset-richtext-resource-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormSubmitResetRichTextResourceCurrentBaseTest.php` passed: `1 test files, 68 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetRichTextActionResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSubmitResetRichTextResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php` passed: `5 test files, 1190 assertions, 0 failures`.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm.*Test\\.php' | sort)` passed: `14 test files, 1484 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-submit-reset-richtext-resource-currentbase.php` passed and emitted `submit_format=pdf`, `resource_font_base=ReviewSerif`, `widget_resource_font_base=ReviewWidget`, `submitted_field_names=["article.rich_resource"]`, `no_export_excluded_field_names=["internal.resource_secret"]`, and all execution flags false.

## Status Delta

- Behavior tests move `718 -> 719` in `lane-status.json`.
- WordPress scenarios move `718 -> 719` in `lane-status.json`.
- Expected mapped markerPDF/PDF semantics move `516 -> 517`; no upstream runner parity is claimed.

## Non-Overlap

This does not repeat accepted AcroForm choice/rich-text SubmitForm/ResetForm value review, widget rich-text `/DS` action-resource review, AcroForm default-resource review, widget appearance/action-cycle review, field hierarchy value review, signature/XFA widget review, seed-lock action review, platform Launch/GoToE action review, or security permission/action correlation. The new behavior is SubmitForm flag policy plus submit/reset field-row `/DA`/`/DR` resource provenance in the same review boundary.

## Dependency Closure

No new support component is needed. This slice reuses native PHP PDF object parsing, AcroForm field traversal, field/widget action review, default appearance parsing, default resource font review, and visible-text extraction. Full form submission/FDF/XFDF/PDF export, JavaScript execution, appearance rendering, signature validation, pdftext/PDFium execution, Python models, OCR, and external PDF tools remain out of scope.
