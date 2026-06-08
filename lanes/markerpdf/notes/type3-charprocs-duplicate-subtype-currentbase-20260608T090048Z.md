# Type3 CharProcs Duplicate Subtype Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T090048Z`

Base accepted HEAD: `ca61108bdd827e1c12cda271a01da7d8c060a0f3`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDF parser behavior before OCR/model fallback. Under this no-GPU PHP lane, Type3 `/CharProcs` are glyph programs only when the selected top-level font `/Subtype` is `/Type3`. The existing native parser already selects current top-level dictionary values for duplicate keys in nearby PDF metadata and CharProcs boundaries, so duplicate `/Subtype` entries should use the selected last top-level value instead of a stale first value.

## Red Behavior

The focused fixture covers three duplicate-subtype cases:

- `/Subtype /Type1 /Subtype /Type3`: the selected Type3 value should enable CharProc `d0` widths and keep `WideGap` joined.
- `/Subtype /Type3 /Subtype /Type1`: the selected Type1 value should ignore stale Type3 CharProc widths and preserve the simple-font `Wide Gap` word break.
- Stream-only fallback with selected `/Subtype /Type1`: `/CharProcs` streams are not Type3-private glyph programs and should not be suppressed from fallback text.

Before the patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateSubtypeBoundaryCurrentBaseTest.php
```

Result: `1 test files, 3 assertions, 3 failures`. The parser selected the first `/Subtype`, producing `Wide Gap` for the current Type3 fixture, `WideGap` for the current Type1 fixture, and suppressing the non-Type3 CharProcs fallback stream.

## Implementation

`PdfTextExtractor::isType3FontBody()` now reads `topLevelPdfLastValueAfterName($fontBody, 'Subtype')` before deciding whether `/CharProcs` are Type3-private glyph programs. This keeps the prior nested-subtype guard while matching the lane's current-value behavior for duplicate top-level dictionary keys.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateSubtypeBoundaryCurrentBaseTest.php
```

Result: `1 test files, 21 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGlyphTailBoundaryCurrentBaseTest.php
```

Result: `7 test files, 82 assertions, 0 failures`.

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfFontType3*Test.php' -o -name 'PdfFont*Type3*Test.php' -o -name 'PdfImageXObjectType3CharProc*Test.php' \) | sort)
```

Result: `65 test files, 763 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-duplicate-subtype-currentbase.php
```

Result: exits 0 and emits `last_subtype_type3_charproc_widths_selected=true`, `last_subtype_type1_charproc_widths_ignored=true`, `type1_charprocs_fallback_stream_preserved=true`, `page_charproc_payload_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, top-level dictionary value reader, Type3 font detection, CharProc width parser, stream-only fallback extractor, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium/pypdfium execution, raster rendering, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.

## Non-Overlap

This does not repeat the accepted nested `/Subtype /Type3` decoy boundary, exact CharProc stream generation lookup, indirect CharProcs dictionary generation lookup, duplicate `/CharProcs` dictionary selection, glyph-entry tail rejection, stream-valued CharProcs dictionary rejection, FontMatrix width normalization, Type3 resource fallback exclusion, image review, CMaps, xref repair, metadata, annotations, forms, OCR, or model work. The bounded behavior is only duplicate top-level font `/Subtype` selection before Type3 CharProc width grouping and fallback stream suppression.
