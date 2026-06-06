# markerPDF Font Text-State Spacing Advance Boundary Current Base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T082952Z`

Base accepted HEAD: `d4fda378b493c1d4cfb146e3047adc8146f5470a`

## Behavior

Native PDF text extraction now rejects overlarge finite `Tw` word-spacing operands and quote-operator word/character spacing operands before applying current text advance or styled-span bbox geometry. This matches the existing bounded font advance guard used for simple/CID widths, `Tf`, `Tc`, `Tz`, and `TJ` adjustments, preventing false WordPress text gaps and unbounded bbox review data from searchable PDFs.

The slice stays inside the no-GPU markerPDF scope: it uses native content-stream parsing only and does not run OCR, Surya, Texify, Torch, Python model workers, pypdfium, PIL, or external PDF tools.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 12 assertions, 2 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects overlarge finite Tw word spacing before current advance bboxes on current base
PASS rejects overlarge finite quote operator spacing before styled advance bboxes on current base
1 test files, 28 assertions, 0 failures
```

Adjacent focused family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
4 test files, 1246 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-text-state-spacing-advance-boundary-currentbase.php
```

Expected smoke flags: `tw_spacing_rejected=true`, `quote_spacing_rejected=true`, `tw_bbox_bounded=true`, `quote_bbox_bounded=true`, `visible_text_excludes_font_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The existing native PHP PDF tokenizer, text-state parser, font-width resolver, and styled-text bbox pipeline are reused.

## Next

Continue with non-overlapping no-GPU markerPDF native parser gaps around CMaps, font metrics, xref repair, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
