# markerPDF Font Width Huge Finite Tf Current Base

Session: `port-dev-markerpdf-font-width-advance-20260606T024232Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260606T024232Z`

Base accepted HEAD: `b16fe7b8f1a76ae151268ab15841f7714fcf2332`

## Source Truth

Pinned upstream `sddai/markerPDF` routes searchable PDF text through pdftext/PDFium before Marker groups text into lines, spans, blocks, and Markdown. Under the current no-GPU markerPDF scope, this PHP lane maps the native PDF text-state and font-width advance boundary needed before WordPress import without running OCR, Surya, Texify, Torch, PDFium, Python model workers, or external PDF tools.

The bounded PDF behavior is an overlarge but finite numeric operand on the `Tf` text-state operator. `Tf` font size participates in glyph advances and styled bboxes; a pathological value that overflows downstream advance geometry must fail closed before it creates infinite review coordinates or suppresses a real word gap.

## Behavior Added

`PdfTextExtractor::fontSizeOperand()` now rejects font-size operands whose absolute value exceeds the existing native font advance metric bound. This preserves the previous valid font size when a finite but unsafe `Tf` operand appears in a content stream.

The focused fixture draws `AB` at 12pt, attempts to switch the same font to a 309-digit finite font size before `CD`, then places `EF` with an absolute `Tm` that should remain a word-gap boundary. The native extractor now emits `ABCD EF`, keeps all three styled spans at 12pt, and preserves finite bboxes `[[0,0,24,12],[24,0,48,12],[72,0,96,12]]`.

## Evidence

Red-first probe before the source edit:

```text
array (
  0 => 'ABCDEF',
)
...
'bbox' => [24.0, 0.0, INF, 1.0E+308]
```

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 522 assertions, 0 failures
```

Adjacent font/CMap family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
7 test files, 906 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-huge-finite-tf-boundary-currentbase.php
```

The smoke emits `huge_finite_tf_ignored=true`, `previous_font_size_preserved=true`, `word_gap_preserved_after_rejected_tf=true`, `false_joined_text_excluded=true`, `styled_bboxes_are_finite=true`, `styled_bboxes_preserved=true`, `line_bbox_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by a Gutenberg paragraph containing `ABCD EF`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2343 -> 2344`
- `wordpressScenarios`: `2009 -> 2010`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `510 -> 522` assertions
- Focused PASS case delta: `+1`

## Non-Overlap

This does not repeat accepted simple-font positive-width averaging, quote-operator spacing, terminal character/word spacing, relative or scaled `Td`, absolute `Tm` styled gaps, text matrix vertical scale, negative or rotated text matrices, text rise, horizontal/vertical `TJ` backtracking, `TJ` drawn extent before same-line `Tm`, unresolved width slots, exact-generation `/Widths`, `/LastChar` clipping, malformed width range rejection, non-finite or huge finite `/Widths` entries, non-finite or huge finite `TJ` adjustments, Type0 `/W` or `/W2` arrays, negative first CID rejection, Type3 FontMatrix width normalization, CMap source-width fallback, image XObject review, xref repair, metadata, annotations, forms, supplied tables, or model/OCR work. The new boundary is specifically finite-but-overlarge `Tf` font-size rejection before text advance, word-gap, and styled-bbox calculation.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, content-token parser, numeric operand parser, text-state `Tf` handling, simple-font width metrics, text-line grouping, styled-span bbox construction, and WordPress smoke renderer. Full upstream OCR/model/PDFium benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
