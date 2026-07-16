# AcroForm Choice Option Operand Boundary

Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T012256Z`
Base: `7ed7b0181dae439571f64983f19fbb9b6bfce3fe`
Date: 2026-06-08 UTC

## Source Truth

This is a native searchable-PDF AcroForm metadata boundary. PDF choice fields expose `/Opt` entries as either scalar option values or top-level export/display tuples. Nested arrays and dictionaries inside a tuple are object operands, not alternate top-level option labels for WordPress review metadata. The slice stays inside the no-GPU markerPDF scope and does not run OCR, layout models, Python model workers, raster inspection, JavaScript, form actions, or external PDF tools.

## Behavior

`PdfAcroFormExtractor::optionsFromEffective()` now parses nested `/Opt` choice tuples with a top-level scalar collector. The collector skips nested arrays, nested dictionaries, comments, and whitespace before reading scalar PDF operands, so decoy nested option strings cannot replace the true export/display pair. Direct scalar `/Opt` entries continue to round-trip as both value and label.

The WordPress smoke proves that the selected `/V (publish)` value maps to `Published label`, while nested array/dictionary decoys such as `decoy.export`, `Nested array label decoy`, and `Dictionary label decoy` stay out of form JSON and visible page text.

## Evidence

Red-first focused run before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChoiceOptionOperandBoundaryCurrentBaseTest.php`

Result: failed as expected with the nested decoy scalar selected as the option label and warnings from scanning into nested dictionary bytes.

Focused runs after the fix:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsChoiceOptionOperandBoundaryCurrentBaseTest.php` => 1 test file / 30 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectChoiceArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsChoiceTopIndexBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormChoiceRichTextSubmitResetCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` => 4 test files / 975 assertions / 0 failures.
- `php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfAcroForm*Test.php' | sort)` => 69 test files / 4718 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-choice-option-operand-boundary-currentbase.php` => exits 0 with `nested_option_decoys_excluded=true`, `option_text_visible_in_page_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is required. The patch reuses the lane's native PDF tokenizer/dictionary/array parsing helpers and adds only a bounded top-level scalar collection helper for AcroForm choice options.

## Non-Overlap

This does not repeat accepted AcroForm `/Fields` or `/Kids` traversal, page-widget repair, generation filtering, direct dictionary parsing, rich text, submit/reset/action handling, XFA/signature behavior, annotations, outlines, attachments, fonts, images, tables, equations, xref repair, encryption, or metadata slices. It is limited to nested operand boundaries inside choice field `/Opt` tuples.
