# Type3 CharProc negative width-vector boundary current-base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T172104Z`
Accepted base: `19e469ac5fba851474b6c82ad19f3b8c0f411282`

## Source truth

This stays inside the current no-GPU markerPDF scope: native searchable-PDF parsing, font metrics, and WordPress text grouping only. Existing lane behavior already treats simple-font negative width metrics as non-importable before grouping. This slice applies the same boundary to Type3 CharProc `d0` and `d1` horizontal `wx` metrics before `FontMatrix` normalization, so a negative width vector cannot become an authoritative positive advance through `abs()`.

No OCR, Surya/Texify/Torch, raster rendering, model execution, browser/PDF engines, or external PDF tools are involved.

## Red-first evidence

Before the parser change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsNegativeWidthVectorBoundaryCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`.

Failure summary: the extractor returned `GoodWide`, `HugeGap`, and `DeltaGap`; expected `GoodWide`, `Huge Gap`, and `Delta Gap`. The negative direct `d0`/`d1` `wx` values were being converted into positive authoritative advances, collapsing WordPress word gaps that should have used `/Widths` fallback metrics.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now rejects negative `wx` for both `d0` and `d1` before returning a Type3 width vector. Valid positive vectors remain importable, `wy` vectors and `FontMatrix` behavior remain covered by the existing positive width-vector test, and `d1` bbox operand validation remains separate.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsNegativeWidthVectorBoundaryCurrentBaseTest.php`  
  Result: `1 test files, 13 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsNegativeWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsD1BBoxOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsOperandCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateMetricBoundaryCurrentBaseTest.php`  
  Result: `5 test files, 59 assertions, 0 failures`.
- `find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3CharProc*CurrentBaseTest.php' -o -name 'PdfFontType3CharProcs*CurrentBaseTest.php' \) | sort | xargs php tools/run-tests.php`  
  Result: `66 test files, 708 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-negative-width-vector-currentbase.php`  
  Result: exits `0`; review flags include `positive_width_metric_preserved=true`, `negative_d0_metric_rejected=true`, `negative_d1_metric_rejected=true`, `charproc_payload_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfFontType3CharProcsNegativeWidthVectorBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-negative-width-vector-currentbase.php`  
  Result: no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The patch reuses the native parser/tokenizer, Type3 CharProc stream decoding, existing font-width fallback machinery, text grouping, and the lane-local WordPress smoke. The model/OCR gap remains an intentional no-GPU scope limit, not a blocker for this native parser slice.

## Non-overlap

This does not repeat accepted Type3 positive width-vector FontMatrix handling, `d1` bbox operand validation, duplicate metric rejection, operand count handling, pre-metric setup, marked-content/graphics-state boundaries, stream filter boundaries, xref/object-stream behavior, metadata, annotations, image/filter review, or OCR/model handoff work.
