# AcroForm Widget Appearance Export Current-Base Handoff

## Source Truth

- Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF extraction at the native/PDFium/pdftext boundary and does not execute AcroForm JavaScript, SubmitForm actions, or appearance rendering.
- PDF AcroForm button fields can carry `/Opt` export strings for radio and checkbox widgets while the field `/V` value and widget `/AS` appearance state remain separate current-state evidence.
- This slice maps those export strings to page-annotation-ordered widgets for review and SubmitForm metadata only. Export labels and SubmitForm targets remain excluded from visible WordPress text.

## Patch

- Added `button_export_options`, `button_export_review`, widget `export_value_source`, and button `effective_export_value` state in `PdfAcroFormExtractor`.
- SubmitForm field value review now uses the effective button export value when `/Opt` mapping is present, while preserving existing raw current-state behavior for button fields without `/Opt`.
- Added focused test coverage in `PdfAcroFormWidgetAppearanceExportCurrentBaseTest.php`.
- Added WordPress smoke example `wordpress-pdf-acroform-widget-appearance-export-currentbase.php`.

## Evidence

- Red-first:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceExportCurrentBaseTest.php`
  - Result before implementation: failed after 4 assertions because `button_export_review` and `effective_export_value` were missing.
- Focused green:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceExportCurrentBaseTest.php`
  - Result: `1 test files, 68 assertions, 0 failures`.
- Adjacent AcroForm batch:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceExportCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceStateCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldActionSubmitResetResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormResourceActionFileSpecCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormSubmitResetAppearanceLockCurrentBaseTest.php`
  - Result: `6 test files, 434 assertions, 0 failures`.
- Example smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-acroform-widget-appearance-export-currentbase.php`
  - Result: passed; emitted export review metadata and Gutenberg list output.
- Syntax and JSON:
  - `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php`
  - `php -l lanes/markerpdf/tests/PdfAcroFormWidgetAppearanceExportCurrentBaseTest.php`
  - `php -l lanes/markerpdf/examples/wordpress-pdf-acroform-widget-appearance-export-currentbase.php`
  - `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`
  - Result: all passed.

## Non-Overlap

This is not a repeat of prior AcroForm `/V`/`/DV` current value state, choice-field `/Opt`, widget stale `/AS`, appearance stream review, calculation-order review, submit/reset lock review, FileSpec action review, or XFA widget review. The new behavior is button-field `/Opt` export mapping through widget appearance current-base state and SubmitForm review rows.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF object parser, AcroForm field/widget traversal, page annotation ordering, appearance-state review, and non-executing action review boundaries.
