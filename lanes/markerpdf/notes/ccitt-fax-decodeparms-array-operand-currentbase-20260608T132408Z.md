# markerpdf-ccitt-fax-filter-boundary-current-base-20260608T132408Z

## Scope

- Lane: markerpdf
- Base accepted HEAD: f2c68bcb90cae7f8d5c420ad4c2ba78bf326142c
- Behavior: CCITT Fax DecodeParms array operands with top-level trailing operands now keep the existing fail-closed parser behavior while exposing array-specific rejection metadata.

## Source Truth

- Upstream markerPDF treats CCITT raster filters as review-only image boundaries before RGB conversion unless a native decoder is explicitly available.
- PDF stream DecodeParms operands must be well-formed dictionary/null values or well-formed arrays aligned to the filter stack. A top-level operand after a DecodeParms array is not part of the declared parameter array and is rejected before image payload review.
- This patch mirrors the existing dictionary-tail diagnostics for CCITT DecodeParms arrays: `array_with_trailing_operands` plus `reject_top_level_decodeparms_array_tail`.

## Evidence

- Red-first probe before the source edit:
  - `php -r 'require "tools/bootstrap.php"; ... PdfImageRenderer()->imageColorSpaceSoftMaskPlan("<< ... /Filter /CCF /DecodeParms [<< ... >>] 7 ... >>") ...'`
  - Existing output failed closed but only reported `decode_parms_review=malformed_ccitt_decodeparms_fail_closed` and `decode_parms_operand=malformed_operand`; it did not expose array-tail policy metadata.
- Focused test after the source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsArrayOperandCurrentBaseTest.php`
  - `1 test files, 32 assertions, 0 failures`
- Focused adjacent CCITT boundary family after the source edit:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxIndirectFilterArrayTailCurrentBaseTest.php`
  - `4 test files, 1267 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-array-operand-currentbase.php`
  - exits 0 and emits `array_with_trailing_operands`, `reject_top_level_decodeparms_array_tail`, `inline_payload_excluded_from_text=true`, and `executes_python_or_models=false`.

## Dependency Closure

- No new support component is needed. The patch reuses the existing native PHP PDF tokenizer, balanced array/dictionary readers, CCITT review-only image boundary metadata, and WordPress block smoke pattern.
- No Python, OCR, Surya/Texify/Torch, GPU, raster renderer, PDFium, browser, or external PDF tool is required.

## Non-Overlap

- Does not repeat valid CCITT DecodeParms dictionary extraction, dictionary-tail rejection, duplicate DecodeParms declarations, duplicate DecodeParms parameters, indirect filter-array tails, native-prefix boundary decoding, nested valid mask/alternate boundaries, ImageMask polarity, or CCITT row/EOB ownership.
- The only new observable metadata is array-specific failure detail for malformed CCITT DecodeParms array operands.
