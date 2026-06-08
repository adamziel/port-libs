# markerpdf font width advance FontMatrix array tail boundary current base

- Session: `port-dev-markerpdf-font-width-advance-20260608T222401Z`
- Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260608T222401Z`
- Base accepted HEAD: `1a91e11e37bf1452c01f3630ee84977c3a03b00f`

## Source truth and scope

This no-GPU markerPDF slice stays in native searchable-PDF parsing. Upstream markerPDF routes searchable PDF text through parser/pdftext extraction before OCR/model stages, and the current supervisor scope excludes Surya/Texify/Torch/model execution. For Type3 fonts, `/FontMatrix` is an array value used to scale glyph advance widths; an indirect helper object that starts with a valid array but contains trailing top-level tokens before `endobj` is malformed and should fail closed before text-advance word-gap grouping.

No local markerPDF upstream checkout was available in `.upstream-cache` for direct fixture replay, so this patch uses the existing native PHP PDF scanner/object resolver behavior and the manifest's no-GPU searchable-PDF scope as the source-of-truth boundary.

## Behavior implemented

`PdfTextExtractor::topLevelPdfMatrixValueAfterName()` now resolves `/FontMatrix` through the exact single-top-level-array helper. A clean direct or indirect array is still accepted. An indirect object such as `[0.002 0 0 0.001 0 0] /Tail` is rejected as a FontMatrix value, causing Type3 width advances to use the default matrix and preserve the positioned word gap.

The fixture proves:

- malformed tailed helper: visible text is `AB CD`, runs remain `AB` and `CD`, and styled bboxes use default Type3 advances;
- clean indirect helper: visible text remains `ABCD`, showing valid width scaling is preserved;
- helper/font payload strings such as `Tail`, `T3FontMatrixArrayTail`, and `Ft3tail` stay out of extracted text.

## Red-first evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBaseTest.php`

Result: `1 test files, 13 assertions, 1 failures`.

The malformed helper was incorrectly accepted and produced `ABCD` instead of the expected `AB CD`. The valid-helper control passed.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBaseTest.php` => `1 test files, 24 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixArrayTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceFontMatrixReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthPrecedenceBoundaryCurrentBaseTest.php` => `6 test files, 84 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(FontWidth|FontType3|CMap).*CurrentBaseTest\.php$')` => `136 test files, 2992 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-font-width-fontmatrix-array-tail-currentbase.php` exits 0 with `visible_text=AB CD`, `fontmatrix_array_tail_rejected=true`, `positioned_word_gap_preserved=true`, and no Python/OCR/models/external PDF tools
- PHP lint was run for the changed source, test, and example
- Root harness: not run - isolated micro-slice

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP PDF object resolver, exact array helper, Type3 font metric path, text advance grouping, styled bbox extraction, and WordPress smoke harness. It does not invoke Python, OCR/model workers, multiprocessing, PDFium/PIL, external PDF tools, or live services.

## Non-overlap

This is distinct from earlier font-width slices for FontMatrix numeric operand tails, tailed scalar number helpers, unresolved FontMatrix element references, Type3 CharProc width vectors and precedence, simple-font `/Widths`, CID `/W`/`DW`/`W2`, quote/TJ spacing, composed TJ adjustments, CMap source-width mapping, xref repair, metadata, annotations/forms, stream filters, and OCR/model behavior. The new boundary is specifically an indirect Type3 `/FontMatrix` array helper object with trailing top-level PDF tokens.
