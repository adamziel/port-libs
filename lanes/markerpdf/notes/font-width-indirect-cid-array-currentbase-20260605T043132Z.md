# markerpdf font width indirect CID array current-base slice

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T043132Z`

Accepted base: `b36dbe88ba80463d50bb6c0be8e8621b7076aace`

## Source-truth boundary

PDF CIDFont `/W` and `/W2` entries are ordinary PDF objects. A valid width entry can use the array form `c [w ...]`, and that array value can be indirect. markerPDF's upstream searchable-PDF path relies on pdftext/PDFium font advances before layout/paragraph grouping. The native no-GPU port must therefore resolve these indirect array operands before text advance, word-gap, and styled-span bbox decisions.

## Patch

- `PdfTextExtractor::cidWidthsFromWArray()` now resolves both direct and indirect width-list arrays in CIDFont `/W`.
- `PdfTextExtractor::cidVerticalDisplacementsFromW2Array()` now resolves both direct and indirect metric-list arrays in CIDFont `/W2`.
- Focused tests add horizontal `/W [1 6 0 R ...]` and vertical `/W2 [40 7 0 R ...]` fixtures.
- WordPress smoke `wordpress-pdf-font-width-indirect-cid-array-currentbase.php` emits paragraph blocks for `WideBlock` and `Thin Text` plus review flags proving no Python, models, or external PDF tools execute.

## Evidence

Red-first focused check before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Failed the new horizontal `/W` case with:

`Actual: [Wide Block, Thin Text]`

Focused run after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 169 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-indirect-cid-array-currentbase.php`

Result: `indirect_cid_w_array_resolved=true`, `wide_cid_runs_not_split=true`, `thin_cid_gap_preserved=true`, `styled_span_widths=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native PDF object-generation lookup, array parsing, and text advance paths. GPU/model OCR, Surya, Texify, Torch, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
