# markerPDF AcroForm Widget Rich Text Action Resource Review

Session: `port-dev-markerpdf-form34pdf-20260602T175653Z`
Micro-slice: `form-widget-richtext-action-resource-currentbase-20260602T175653Z`
Base accepted HEAD: `1f51384b562639ecac3cfdac5c64ef58d0a7970f`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py` delegates PDF text extraction to `pdftext.extraction.dictionary_output()` and pypdfium text pages; this lane mirrors that by treating AcroForm rich text, widget appearance/default resources, and form actions as review metadata rather than executable/imported visible text.
- PDF AcroForm variable-text fields can carry `/RV` rich text and `/DS` default style strings; widget annotations can override `/DA` and refer to the form `/DR` default resources; widget `/AA` actions remain non-executing review metadata.

## Implemented

- `PdfAcroFormExtractor` now carries inherited `/DS` default-style attributes alongside `/RV` rich-text attributes.
- `rich_text_review` now records default-style source, source object, preview, byte count, SHA-256, and explicit false import/submit/CSS exposure flags.
- Widget-local `/DA` default-appearance rows now preserve the widget object as `source_object` while still resolving font resources through effective AcroForm `/DR`.
- Added a current-base fixture proving `/V` is authoritative for WordPress import while `/RV`, `/DS`, widget `/DA` resource review, and widget `/AA /V` JavaScript are metadata only.

## Verification

- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfAcroFormWidgetRichTextActionResourceCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-widget-richtext-action-resource-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetRichTextActionResourceCurrentBaseTest.php` passed: `1 test files, 60 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceActionCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetRichTextActionResourceCurrentBaseTest.php` passed: `4 test files, 905 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-widget-richtext-action-resource-currentbase.php` passed and emitted `field_name=article.rich_widget`, `widget_source_object=8`, `widget_font_base=ReviewSans`, and all execution flags false.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php` passed: `7 test files, 1059 assertions, 0 failures`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests move `617 -> 618`.
- Mapped markerPDF/PDF semantics move `450 -> 451 / 78`.
- New mapped inventory key: `mappedPdfAcroFormWidgetRichTextActionResourceReviewBehaviors`.

## Non-Overlap

This does not repeat accepted AcroForm choice/rich-text SubmitForm/ResetForm review, AcroForm default-resource review, widget appearance/action-cycle review, calculation-order widget appearance review, signature/XFA widget state, or non-JavaScript action review. The bounded behavior is the combined variable-text `/DS` default-style review plus widget-local `/DA` source-object/resource provenance and validate-action metadata for a rich-text field.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, AcroForm field hierarchy resolver, default-resource font review, widget action walker, stream decoder, and plain text leak boundary. Full upstream markerPDF runner parity remains blocked by Python/pdftext/pypdfium/Surya/Texify/model and external OCR/rendering/runtime dependencies.
