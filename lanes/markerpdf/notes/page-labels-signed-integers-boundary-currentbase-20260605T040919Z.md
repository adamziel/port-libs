# markerPDF PageLabels signed integers boundary

## Source Truth

- Upstream markerPDF gets page-local text and page structure through PDF page iteration; native PHP PageLabels stay page-break and review metadata and do not alter visible text extraction.
- PDF PageLabels are catalog number-tree entries keyed by zero-based physical page indexes. PDF integer tokens may include an optional leading plus sign, so `/Nums`, `/Limits`, and `/St` operands such as `+1` must be parsed consistently.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium, PDFium, Poppler, Ghostscript, Python, or external PDF tooling was run.

## Implementation

- `MarkerAppPreview` now accepts optional leading `+` signs for PageLabels page-index operands, `/Limits` operands, and `/St` start operands.
- Added a focused current-base fixture where `/Limits [+1 +2]` excludes a stale `+0` label, while `+1` and `+2` entries produce `Signed 3` and `App-Z`.
- Added a WordPress smoke that emits page-break metadata for `1`, `Signed 3`, and `App-Z` while proving the stale signed cover label is not exposed.

## Evidence

Red-first after adding the focused test and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL keeps signed PageLabels integer operands aligned across import and preview metadata
Expected: ["1","Signed 3","App-Z"]
Actual: ["1","2","3"]
1 test files, 96 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
PASS keeps signed PageLabels integer operands aligned across import and preview metadata
1 test files, 100 assertions, 0 failures
```

Additional focused verification for this handoff is recorded in the final worker report.

## Non-Overlap

This does not repeat accepted PageLabels direct `/Nums`, indirect `/Kids`, inherited/local `/Limits`, indirect operands, transitive operands, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, generation-exact dictionaries, token boundaries, trailer-root selection, viewer preferences, outline page-label propagation, or page transition/action review. The bounded behavior is only signed integer operand parsing for PageLabels preview alignment.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path.
