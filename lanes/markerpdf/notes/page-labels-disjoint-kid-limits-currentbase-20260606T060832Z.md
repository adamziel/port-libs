# markerPDF PageLabels disjoint kid limits boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260606T060832Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible paragraph text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page indexes. Kid `/Limits` bound child number-tree ranges; a child whose local `/Limits` do not intersect inherited parent limits cannot contribute labels and must not change sibling ordering or claimed-range behavior.
- This no-GPU slice stays inside the native parser boundary: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, PIL rendering, Python runner, or external PDF tools were run.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now skips kid nodes whose local `/Limits` merge to no range with inherited parent limits before sorting sibling kids and claiming contributed ranges.
- `MarkerAppPreview::pageLabelSections()` mirrors the same boundary for fallback preview inventory paths.
- Added a focused fixture where `[1,1]` tries to stale-label page 2, `[9,10]` is disjoint from the parent `[0,3]` range, `[0,2]` is the valid wider range, and `[3,3]` remains a valid final range.
- Added a WordPress smoke that emits Gutenberg page-break metadata for `Front iv`, `Front v`, `App-Z`, and `End-` while proving stale labels are excluded.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDisjointKidLimitsSortBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores disjoint PageLabels kid Limits before sorting overlapping siblings
Expected: ["Front iv","Front v","App-Z","End-"]
Actual: ["Front iv","stale-overlap-77","App-Z","End-"]
1 test files, 1 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDisjointKidLimitsSortBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores disjoint PageLabels kid Limits before sorting overlapping siblings
1 test files, 12 assertions, 0 failures
```

Focused PageLabels plus preview family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
Focused test run: 16 selected test files (root lock skipped)
16 test files, 526 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-disjoint-kid-limits-currentbase.php
```

The smoke emits `disjoint_kid_ignored=true`, `overlapping_stale_kid_rejected=true`, `wide_range_continuation_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2424 -> 2425`
- WordPress scenarios: `2067 -> 2068`
- New focused assertions: `12`
- Focused PageLabels family: `16 test files / 526 assertions / 0 failures`
- Root harness: not run - isolated micro-slice

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, direct/indirect null reset values, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only kid nodes whose local `/Limits` are disjoint from inherited parent `/Limits` and therefore must not disable sibling `/Limits` sorting before overlapping stale labels are rejected.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Live OCR, Surya/Texify/Torch models, PDFium/pypdfium raster execution, PIL, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
