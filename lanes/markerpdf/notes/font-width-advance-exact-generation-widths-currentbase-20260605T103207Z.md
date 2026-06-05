# markerPDF Font Width Advance Exact Generation Widths Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T103207Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T103207Z`

Base accepted HEAD: `edbd54e9448f3320ec7b627467caded1fab93ac8`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF text extraction to `pdftext.extraction.dictionary_output` before Marker block assembly. The native no-GPU PHP path therefore has to preserve PDF parser font-width and text-advance boundaries before WordPress paragraph grouping without running Python, pdftext, pypdfium, OCR, or models.

PDF indirect references are generation-specific. A simple font that references `/Widths 20 0 R` must not resolve object `20 1 obj` just because it has the same object number and appears as the latest direct body. Width arrays drive text advance, so selecting the wrong generation can create false word gaps and collapsed styled-span bboxes.

## Native Behavior Added

`PdfTextExtractor::pdfArrayValueAfterNameResolvingObjects()` now resolves indirect array operands through `objectBodyForExactReference()` instead of using only `objectReferenceValueAfterName()` plus `$objects[$objectNumber]`.

The focused fixture uses:

- a Type1 subset font with `/Widths 20 0 R`;
- object `20 0 obj` containing wide `1000`-unit widths;
- object `20 1 obj` containing stale narrow `250`-unit widths;
- positioned `Wide` and `Block` text that should remain joined only when the generation-zero width array is selected.

Before the fix, a red-first probe emitted `Wide Block` and produced stale narrow bboxes `[[0,0,12,12],[46,0,61,12]]`. After the fix, the text remains `WideBlock` with bboxes `[[0,0,48,12],[48,0,108,12]]`.

## Evidence

Red-first probe before the source fix:

```text
array (
  0 => 'Wide Block',
)
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves exact-generation simple-font Widths arrays before current advance gaps
1 test files, 293 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `exact_generation_width_array_resolved=true`, `exact_generation_width_false_gap_excluded=true`, `exact_generation_width_stale_generation_excluded=true`, `exact_generation_width_bboxes_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests: `1726 -> 1727`.
- Focused width-advance test: `282 -> 293` assertions.
- WordPress scenarios: `1578 -> 1579`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, generation-indexed direct-object table, exact-reference resolver, simple-font width parser, text-position advance estimator, styled-span bbox path, and existing WordPress smoke renderer. Full upstream markerPDF parity remains outside this no-GPU slice for live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity.

## Non-Overlap

This does not repeat accepted simple-font average positive width fallback, direct or indirect simple-font Encoding Differences, indirect `/FirstChar`, direct or indirect generation-zero `/Widths`, unresolved width slots, `/LastChar` clipping, malformed width ranges, quote operator spacing, relative/scaled `Td`, absolute `Tm` styled gaps, text matrix/rise handling, horizontal or vertical `TJ` backtracking, vertical `/W2`, indirect CIDFont `/W` or `/W2`, Type3 FontMatrix/CharProc widths, CIDSet/default CIDFont grouping, CMap source-width fallback, xref repair, stream filters, annotations, forms, images, metadata, or security preflight. The bounded behavior is specifically exact-generation resolution of an indirect simple-font `/Widths` array before native text-advance grouping.
