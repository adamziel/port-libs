# markerPDF font-width short-array current-base

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260608T204357Z`

Base: `6479f65c1465d77f871d7146aaaa2d022aa27e3f`

## Source Truth

Upstream markerPDF gets searchable-PDF text positioning from pdftext/PDFium before OCR/model stages. In the native no-GPU PHP scope, simple-font `/Widths` arrays must follow the PDF contract where `/LastChar - /FirstChar + 1` entries are declared for the width range. An underdeclared array is malformed and must not be used as authoritative text-advance evidence for WordPress paragraph grouping.

## Implementation

`PdfTextExtractor` now rejects underdeclared simple-font `/Widths` arrays when `/LastChar` is present. The extractor falls back to Base14/default font advances instead of using a partial width array to collapse positioned word gaps. Complete `/Widths` arrays remain authoritative.

## Evidence

Pre-fix probe on the assigned boundary fixture:

`php -r 'require "tools/bootstrap.php"; ... /FirstChar 65 /LastChar 66 /Widths [1000] ...'`

Result: the native extractor returned `["AB"]` and normalized both spans to 1000-unit advances, so the positioned gap was suppressed.

After fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceShortWidthsBoundaryCurrentBaseTest.php`

Result: `1 test files, 24 assertions, 0 failures`.

Adjacent font-width regression run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceShortWidthsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceDuplicateDwBoundaryCurrentBaseTest.php`

Result: `3 test files, 78 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-short-array-currentbase.php`

Result: exits 0 with `underdeclared_widths_rejected=true`, `base14_advance_fallback=true`, `positioned_word_gap_preserved=true`, `font_payload_hidden_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-cmap-cid-type3-width-spacing-bundle-currentbase.php`

Result: exits 0 with Type3/CMap/CID width spacing preserved.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat malformed declared width tokens, indirect `/Widths` helper-tail rejection, duplicate CIDFont `/DW`, Type3 CharProc width extraction, CID `/W` or `/W2` metrics, CMap source-width fallback, or quote/TJ spacing behavior. The new boundary is only underdeclared simple-font `/Widths` arrays that would otherwise provide partial advance evidence before WordPress text grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, simple-font metric parser, Base14 width table, text-position advance grouping, styled-span extraction, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, decryption, JavaScript/action execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
