# markerpdf font width rotated Td vector advance current-base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260605T213430Z`

Base accepted HEAD: `b321f6888e03ba16f542328dfc7cccbdbb2ef4a8`

## Source Truth

Native PDF text positioning applies `Td` in text space after the current text
matrix. For non-axis-aligned matrices, a horizontal text-space movement should
advance by the text matrix horizontal vector length, not only the raw `a`
component. This no-GPU slice keeps the accepted absolute `Tm`, negative matrix,
terminal spacing, Type0 vertical width, and Type3 FontMatrix boundaries while
adding the missing relative `Td` rotated/sheared advance boundary.

## Implementation

- `PdfTextExtractor` now tracks a local horizontal text position for relative
  text moves and uses the text-matrix horizontal vector magnitude for `Td`/`TD`
  word-gap decisions.
- Absolute `Tm` gap decisions keep the existing page-coordinate comparison, and
  negative horizontal matrix scales keep signed advance behavior.
- The WordPress smoke fixture now emits `rotated_td_vector_gap_*` assertions for
  extracted lines, styled bboxes, line bboxes, and a-only compaction exclusion.

## Red-First Evidence

After adding the focused fixture before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files / 453 assertions / 1 failures`.

Failure: the new rotated `Td` fixture expected lines `AB CD` and `AB CD`, but
the existing implementation collapsed both lines to `ABCD`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  - `1 test files, 468 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  - `4 test files, 809 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php | rg "rotated_td|executes_python|executes_external"`
  - emitted `rotated_td_vector_gap_lines_preserved=true`
  - emitted `rotated_td_vector_gap_first_bboxes_preserved=true`
  - emitted `rotated_td_vector_gap_second_bboxes_preserved=true`
  - emitted `rotated_td_a_only_compaction_excluded=true`
  - emitted `executes_python_or_models=false`
  - emitted `executes_external_pdf_tools=false`

Root harness not run - isolated micro-slice.

Attempted broader extractor sweep:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - `1 test files, 626 assertions, 2 failures`
  - Both failures are unrelated ToUnicode `usecmap` mapping fixtures whose
    single `<...> Tj` operands do not exercise the relative `Td` gap path
    changed in this slice.

## Non-Overlap

This patch does not repeat accepted terminal `Tc`/`Tw`, scaled `Td`, absolute
`Tm`, Type0 vertical `/W2`, Type3 FontMatrix, non-finite width/TJ operand, or
AcroForm field quadding slices. It only adds relative `Td` vector advance under
rotated/sheared simple-font text matrices.

## Dependency Closure

No new dependency or support component is needed. The behavior is implemented in
the native PHP PDF text extractor and uses existing content-token, font-width,
text-matrix, styled-span, and WordPress smoke helpers. No Python, OCR, GPU,
model worker, pypdfium, pdftext, or external PDF tool execution was used.
