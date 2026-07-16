# markerPDF PageLabels generation boundary

## Source Truth

- Upstream markerPDF gets page-local text and document structure through PDF page iteration; native PHP PageLabels remain page-break/review metadata and do not alter visible text.
- PDF `/PageLabels` is a catalog number tree that maps zero-based physical page indexes to label dictionaries. The label dictionary can provide `/S`, `/P`, and `/St`; label ranges continue until the next number-tree entry or document end.
- Relevant parser behavior: pikepdf exposes `/Root /PageLabels` as a number tree for page labels (<https://pikepdf.readthedocs.io/en/stable/api/models.html>), and pypdf's page-label helper resolves labels from `/Nums` entries and falls back to physical indexes when unreliable (<https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/>). References in `/Nums` still carry an object generation, so native extraction must not bind `30 0 R` to `30 1 obj`.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium, PDFium, Poppler, Ghostscript, Python, or external PDF tooling was run.

## Implementation

- `PdfTextExtractor` now resolves indirect PageLabels value dictionaries by exact object generation before parsing `/S`, `/P`, and `/St`.
- `MarkerAppPreview` keeps a direct object body map by generation during preview parsing and uses it for PageLabels indirect dictionaries, kid nodes, limits, and nested operands, keeping `openPdfSummary()`, `pageLabels()`, and `getPageImagePlan()` aligned with native text extraction.
- Added a focused fixture where `/Nums [0 30 0 R ...]` must use `30 0 obj << /P (Cover-) >>` and reject a same-number higher-generation decoy `30 1 obj << /P (stale-high-generation-) /St 99 >>`.
- Added a WordPress smoke that emits page-break metadata for `Cover-`, `Body 4`, and `Body 5` while proving the stale high-generation labels are absent.

## Evidence

Red-first after adding the focused test and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
FAIL keeps generation-exact indirect PageLabels dictionaries before WordPress page metadata
Expected: ["Cover-","Body 4","Body 5"]
Actual: ["stale-high-generation-99","Body 4","Body 5"]
1 test files, 40 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsBoundaryCurrentBaseTest.php
PASS keeps generation-exact indirect PageLabels dictionaries before WordPress page metadata
1 test files, 46 assertions, 0 failures
```

Additional focused verification for this handoff is recorded in the final worker report.

## Non-Overlap

This does not repeat accepted PageLabels direct `/Nums`, indirect `/Kids`, inherited/local `/Limits`, indirect `/S` `/P` `/St` operands, escaped catalog names, PDFDocEncoding prefixes, alphabetic repeated-letter formatting, viewer preferences, outline page-label propagation, or page transition/action review. The bounded behavior is generation-exact indirect PageLabels value dictionaries and preview alignment.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, generation-indexed object body table, PageLabels number-tree parser, marker-app preview summary, and WordPress block smoke path.
