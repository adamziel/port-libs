# markerPDF Type3 CharProcs width precedence boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T065656Z`

Accepted base: `13a03f44f03f1a17e55a3c59df211c0698381848`

## Source truth

Upstream markerPDF delegates searchable PDF text extraction to pdftext/PDFium
at `marker/pdf/extract_text.py`, then keeps extracted text separate from
review-only PDF payloads. In native no-GPU scope, the relevant PDF boundary is
Type3 font parsing: `/CharProcs` streams are glyph programs, not visible page
text, and `d0`/`d1` operators declare glyph metrics used for text advance.

This slice covers the conflict boundary where a Type3 font also carries a
stale `/FirstChar`/`/LastChar` `/Widths` array. Once a current CharProc stream
has yielded a usable metric for a glyph, that CharProc metric remains
authoritative for WordPress text grouping; `/Widths` still fills only glyphs
whose CharProc metric is absent or unusable.

## Red-first evidence

Before the source fix, the new focused current-base fixture failed because the
generic `/Widths` merge overwrote recovered CharProc metrics:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthPrecedenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps Type3 CharProc d0 d1 widths ahead of stale Widths arrays before WordPress grouping on current base
Expected: ['WideBlock', 'Thin Text']
Actual:   ['Wide Block', 'ThinText']
1 test files, 1 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::fontWidthMetrics()` now preserves Type3 CharProc-derived
width entries when merging simple-font `/Widths`. This keeps `d0`/`d1` metrics
ahead of stale width arrays while leaving `/Widths` as a fallback for glyphs
without valid CharProc metrics.

The focused fixture also proves CharProc payload text remains excluded from
visible WordPress paragraphs.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthPrecedenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps Type3 CharProc d0 d1 widths ahead of stale Widths arrays before WordPress grouping on current base
1 test files, 7 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name '*Type3*Test.php' -o -name '*CharProc*Test.php' \) | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 822 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-width-precedence-currentbase.php
```

The smoke emits `WideBlock` and `Thin Text`, with
`charproc_widths_override_stale_widths_array=true`,
`stale_widths_array_excluded_from_grouping=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. The patch reuses the existing native PDF
object parser, stream decoder, Type3 `/CharProcs` dictionary resolution,
FontMatrix/width parsing, simple-font width fallback, content-token parser, and
text-advance grouping. Python, pdftext, pypdfium, Poppler, Ghostscript, OCR,
model workers, and external PDF tools remain excluded by the no-GPU markerPDF
scope.

## Non-overlap

This does not repeat accepted Type3 CharProc fallback exclusion, exact object
generation selection, exact indirect CharProcs dictionary generation,
comment-split references, subtype/top-level/nested-dictionary guards,
FontMatrix scalar normalization, full `wx wy` vector transforms, initial
operator/inline-image fail-closed boundaries, resource-subtype decoys, Type3
CMap/CIDSet spacing, color glyph resources, or descriptor MissingWidth fallback.
The new behavior is specifically Type3 CharProc metric precedence when a
conflicting `/Widths` array would otherwise overwrite recovered glyph-program
metrics before WordPress text grouping.
