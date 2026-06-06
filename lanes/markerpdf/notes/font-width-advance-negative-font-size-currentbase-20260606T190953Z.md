# font-width advance negative font size current-base slice

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260606T190953Z`

Accepted base: `6ee64e8398d01c4bd51ef8bc1f2d16d007c2db92`

## Scope

This slice stays in the native no-GPU markerPDF parser path. It covers a PDF text-state boundary where a `Tf` text font-size operand is negative. The local font-advance path already normalizes negative text matrices and negative horizontal scale into usable extraction geometry; this patch aligns negative `Tf` font sizes with that convention by preserving the operand magnitude before current advance and styled bbox math. Without the normalization, a negative font size can either move the text cursor backward or fall back to the previous font size, producing false same-line gaps or stale styled font-size/bbox metadata.

Non-overlap: this does not touch OCR, Surya/Texify/Torch/model execution, quote spacing, simple-font `/Widths`, CID `/W` or `/W2`, Type3 FontMatrix width normalization, `Tz`, `TJ`, `Td`, `Tm`, or existing overlarge/non-finite font-width guards.

## Evidence

Red-first focused run for the original negative `Tf` cursor bug:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 582 assertions, 1 failures`; the new negative `Tf` case returned `ABCD EF` instead of `ABCDEF`.

Red-first focused run for the final magnitude-normalization check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 587 assertions, 1 failures`; the `10 Tf` then `-12 Tf` fixture fell back to 10pt bboxes instead of normalizing to 12pt.

After the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 595 assertions, 0 failures`.

Adjacent font boundary verification:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php`

Result: `3 test files, 666 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-negative-font-size-currentbase.php`

Result: emits `<p>ABCDEF</p>` with `negative_tf_font_size_magnitude_normalized=true`, `font_size_magnitude_preserved_for_wordpress_paragraph=true`, `false_word_gap_excluded=true`, `styled_bboxes_preserved=true`, and no Python/model/external PDF tool execution.

PHP lint:

`php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-font-width-negative-font-size-currentbase.php`

Result: no syntax errors detected in all three files.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF content-stream/text-state parser and simple font width machinery. The no-GPU/model scope remains unchanged; scanned-document OCR, model layout, and exact upstream model benchmark parity are intentionally out of scope for this markerPDF worker.

## Next Task

Continue with non-overlapping native searchable-PDF behavior, preferably CMap/font-width boundaries not already covered, stream/xref repair, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
