# markerPDF Type3 MissingWidth FontMatrix Advance Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T125428Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T125428Z`

Base accepted HEAD: `97c11b6d278bd1942bd719cfd7817066baa00cb7`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through pdftext dictionary extraction before Marker block/span assembly. Under the current no-GPU lane scope, the native PHP fallback owns the low-level PDF font advance boundary before WordPress paragraph grouping.

Relevant PDF parser behavior is that Type3 glyph metrics live in glyph space and are converted through the font `/FontMatrix` before text-space advance decisions. The existing native parser already normalized Type3 `/Widths` and CharProc `d0`/`d1` width vectors this way; this slice extends the same boundary to descriptor `/MissingWidth` fallback.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

## Native Behavior Added

`PdfTextExtractor::fontWidthMetrics()` now transforms Type3 `FontDescriptor /MissingWidth` through the Type3 `/FontMatrix` before storing it as the default glyph advance. Simple Type1/TrueType MissingWidth behavior is unchanged, explicit Type3 `/Widths` still win, and Type3 CharProc widths still have precedence for glyphs that declare valid `d0`/`d1` metrics.

The focused fixture uses:

- a Type3 font with `/FontMatrix [0.002 0 0 0.001 0 0]`;
- `/FirstChar 65 /LastChar 66 /Widths [500 500]`, so source glyphs C-F fall outside explicit widths;
- `/FontDescriptor /MissingWidth 500`, which must become a 1000-unit text advance after FontMatrix normalization;
- two positioned lines where the first line falsely splits as `CD EF` with raw 500-unit MissingWidth, while the second line retains a real positioned gap as `CD EF`.

Before the source change, a current-base probe returned first-line text `CD EF` and raw 12pt two-glyph styled bboxes. After the change, the first line emits `CDEF` with 24pt two-glyph spans, while the real second-line gap remains `CD EF`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes Type3 descriptor MissingWidth through FontMatrix before current advance gaps on current base
1 test files, 354 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-missingwidth-fontmatrix-currentbase.php
```

The smoke emits `type3_missingwidth_fontmatrix_join_preserved=true`, `type3_missingwidth_real_gap_preserved=true`, `raw_missingwidth_false_gap_excluded=true`, `type3_missingwidth_bboxes_preserved=true`, `real_gap_bbox_preserved=true`, `raw_missingwidth_bboxes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `CDEF` and `CD EF`.

## Status Delta

- Behavior tests: `1841 -> 1842`.
- Focused assertions in `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `340 -> 354`.
- Focused new assertions: `14`.
- WordPress scenarios: `1672 -> 1673`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, Type3 font dictionary parser, FontDescriptor MissingWidth resolver, FontMatrix width transform, text-position advance estimator, styled-span bbox path, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.

## Non-Overlap

This does not repeat accepted simple-font positive `/Widths` average fallback, direct or indirect `/MissingWidth` resolution with default FontMatrix, explicit Type3 `/Widths` FontMatrix normalization, Type3 CharProc `d0`/`d1` width precedence, Type0 CIDFont `/W` or `/DW`, vertical `/W2`, CIDSet/default CIDFont grouping, source-space word-spacing, TJ adjustments, relative `Td`, absolute `Tm`, text rise, negative spacing, rotated matrix, or pdftext dictionary span metadata. The bounded behavior is specifically non-default Type3 `/FontMatrix` normalization for descriptor `/MissingWidth` fallback before native WordPress text advance and styled-span geometry review.
