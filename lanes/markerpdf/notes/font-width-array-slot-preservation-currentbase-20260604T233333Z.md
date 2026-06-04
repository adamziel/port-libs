# markerPDF Font Width Array Slot Preservation Current Base

Session: `port-dev-markerpdf-font-width-advance-20260604T233333Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260604T233333Z`

Base accepted HEAD: `13e4a34a676f7e28d451ba51d2e80ee1c66b1a31`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is font width-array slot preservation for text advance grouping. PDF `/Widths`, CIDFont `/W` array forms, and vertical `/W2` array forms are positional data. If one indirect numeric width operand is unresolved or malformed, later numeric widths must not shift onto earlier glyph codes before WordPress paragraph grouping.

## Source Truth

Upstream `sddai/markerPDF` delegates searchable-PDF text geometry to parser-backed `pdftext` dictionaries before Marker `Span`, `Line`, and `Block` conversion. The native PHP fallback therefore has to preserve PDF font advance boundaries before deciding whether adjacent positioned text belongs in one WordPress paragraph.

This slice maps the PDF font-width contract: array entry positions correspond to character codes or CIDs. Invalid width operands may be skipped or defaulted, but they cannot compact the array and reassign subsequent widths to different glyphs.

## Native Behavior Added

`PdfTextExtractor` now has a nullable numeric array reader for width arrays. Callers that need positional width semantics preserve every array slot, skip unresolved numeric values, and keep later widths at their original offsets.

The focused simple-font fixture uses:

- `/FirstChar 65` with `/Widths [1000 1000 99 0 R 250]`;
- a missing object reference in the width slot for glyph `C`;
- an explicit narrow width for glyph `D`;
- absolute positioning that should emit `CD` only when `C` keeps its unresolved slot and falls back to the positive-width average instead of inheriting `D`'s width.

Before this change, the numeric array reader compacted the unresolved `99 0 R` item, shifted `D`'s `250` width onto `C`, and inserted a false `C D` word gap. After the change, the slot is preserved, `C` uses the default advance, `D` keeps the narrow width, and the imported paragraph is `CD`.

The same positional reader is reused for nested CIDFont `/W` and vertical `/W2` array forms so malformed members do not shift later CIDs in future searchable-PDF fixtures.

## Evidence

Red-before observation on the accepted base:

```text
array (
  0 => 'C D',
)
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
PASS applies quote operator spacing before styled font advance bboxes on current base
PASS uses font-width current text advance before relative Td word-gap decisions on current base
PASS preserves unresolved simple-font width slots before current advance gap decisions
PASS uses vertical CIDFont W2 advances for native styled span bboxes on current base

1 test files, 55 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `unresolved_width_slot_preserved=true`, `unresolved_width_false_gap_excluded=true`, `unresolved_width_slot_bboxes_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by a Gutenberg paragraph for `CD`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests: `1105 -> 1106`.
- Focused file assertions: `44 -> 55`.
- Focused new assertions: `11`.
- WordPress scenario file updated in place; scenario count unchanged.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, indirect operand resolver, simple-font Encoding Differences parser, CIDFont width parser, vertical-width parser, text advance estimator, styled-span bbox path, and WordPress smoke renderer. Live OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat the earlier average positive simple-font width fallback, quote-operator spacing, relative `Td` font advance, vertical `/W2` bbox, Type3 CharProc, CIDSet/default-width, or xref/object-stream font-resource slices. The new boundary is specifically unresolved or malformed numeric members inside positional font width arrays.
