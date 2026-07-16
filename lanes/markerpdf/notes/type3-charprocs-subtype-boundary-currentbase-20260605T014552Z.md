# markerPDF Type3 CharProcs subtype boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T014552Z`

Base accepted HEAD: `b828ac3b472ad91b3570084ccb5b89f5b3613216`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text extraction to `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, which rely on pdftext/PDFium PDF parsing before the OCR/layout/model pipeline. Under the current no-GPU markerPDF scope, the PHP lane owns this native parser boundary: Type3 `/CharProcs` glyph programs must be hidden from fallback visible text, but only when the font dictionary itself is a top-level `/Subtype /Type3`.

## Red behavior

The current-base probe built a stream-only searchable-PDF fixture with:

- a top-level `/Subtype /Type1` font,
- a top-level `/CharProcs << /A 3 0 R >>` decoy,
- a nested private dictionary containing `/Subtype /Type3`,
- object `3 0 R` as the visible fallback content stream.

Before the patch, `PdfTextExtractor::isType3FontBody()` matched `/Subtype /Type3` anywhere in the font body. That false-positive Type3 match caused `type3CharProcObjectGenerationSet()` to suppress object `3 0 R` as if it were a glyph CharProc stream, so WordPress fallback text extraction returned no visible text.

## Implementation

- `PdfTextExtractor::isType3FontBody()` now resolves only the top-level `/Subtype` value.
- `PdfTextExtractor::type3CharProcWidths()` now uses the same Type3 helper instead of its own broad regular expression.

This preserves accepted real Type3 `/CharProcs` width handling, exact-generation CharProc stream lookup, indirect CharProcs dictionaries, top-level CharProcs parsing, and stream-only fallback exclusion for actual Type3 fonts.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php
```

Result: `1 test files, 6 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `14 test files, 736 assertions, 0 failures`.

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-subtype-boundary-currentbase.php
```

Result: no syntax errors.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-subtype-boundary-currentbase.php
```

Result: emitted `requires_top_level_type3_subtype=true`, `fallback_content_preserved=true`, `false_charproc_suppression_prevented=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by the WordPress paragraph `Visible fallback content`.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, top-level dictionary value reader, Type3 CharProc width parser, stream-only fallback extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium execution, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.

## Non-overlap

This does not repeat accepted Type3 CharProc exact stream generation lookup, indirect CharProcs dictionary generation lookup, nested CharProcs dictionary entry filtering, top-level `/CharProcs` lookup inside real Type3 fonts, FontMatrix width normalization, Type3 CMap/CIDSet/descriptor width grouping, Type3 color glyph resource review, or Type3 CharProc payload fallback exclusion. The new boundary is specifically rejecting nested `/Subtype /Type3` decoys when deciding whether top-level `/CharProcs` entries belong to a real Type3 font.
