# PageLabels kid local Limits current-base

## Scope

- Lane: markerpdf
- Micro-slice: markerpdf-page-labels-boundary-current-base-20260608T082923Z
- Base accepted HEAD: d26ecc00d103df4f8bfc0a6c5fcecf9fae053506

This slice adds focused current-base coverage for catalog `/PageLabels` child
number-tree nodes whose local `/Limits` exclude stale `/Nums` entries inside
the same leaf. The native parser must constrain child leaf entries by the
child's own local limits before merging sibling ranges into WordPress
page-break and preview metadata.

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR,
  layout, or model handoff, so native PHP `/PageLabels` remain page-aligned
  metadata rather than body text:
  https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium PageLabel unit coverage models `/PageLabels` as a catalog number tree
  with `/Kids`, `/Limits`, and `/Nums` boundaries:
  https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp

## Implementation

- Added `PdfPageLabelsKidLocalLimitsBoundaryCurrentBaseTest.php` with a
  four-page searchable PDF whose first child node has `/Limits [2 3]` but also
  carries a stale page-0 `/Nums` entry. The expected labels are physical page
  `1`, sibling `Body 4`, then child-local `App-Z` and `Back-7`.
- Added `wordpress-pdf-page-labels-kid-local-limits-currentbase.php` to prove
  the same labels flow into Gutenberg page-break metadata, preview summary
  rows, and `getPageImagePlan()` without Python, models, OCR, PDFium runtime
  execution, or external PDF tools.
- No production PHP source changed because the accepted current-base parser
  already intersects inherited and child-local `/Limits` during number-tree
  traversal.

## Evidence

- `php -l lanes/markerpdf/tests/PdfPageLabelsKidLocalLimitsBoundaryCurrentBaseTest.php`
  - No syntax errors detected.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-kid-local-limits-currentbase.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsKidLocalLimitsBoundaryCurrentBaseTest.php`
  - 1 test files / 10 assertions / 0 failures.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfPageLabels.*CurrentBaseTest\.php$|PdfPageLabelsBoundaryCurrentBaseTest\.php$' | sort)`
  - 37 test files / 704 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-page-labels-kid-local-limits-currentbase.php`
  - Emitted `markerpdf-page-labels-kid-local-limits-smoke` with labels
    `["1","Body 4","App-Z","Back-7"]`,
    `stale_local_nums_rejected=true`,
    `child_local_limits_applied=true`,
    `selected_preview_page_label="Back-7"`,
    `executes_python_or_models=false`, and
    `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf`
  - Passed with no output.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction,
indirect `/Kids`, direct kid dictionaries, inherited/root valid `/Limits`,
malformed/reversed/negative child `/Limits`, disjoint/overlapping/touching kid
range sorting, no-limits kid source order, same-lower source-order guards,
duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Kids`
keys, duplicate `/Limits` keys, descending or out-of-range `/Nums` ordering,
null resets, indirect scalar operands, generation-exact dictionaries/arrays/
keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes,
malformed dictionary/array object tails, encrypted preview fallback,
viewer-preference composition, outline page-label propagation, or page
transition/action review. The bounded behavior is only stale leaf `/Nums`
entries inside a contributing child node being constrained by that child's own
local `/Limits` before sibling merge.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, top-level dictionary/array tokenizer, PageLabels number-tree parser,
label formatter, MarkerAppPreview page inventory path, and WordPress smoke
renderer. Full upstream markerPDF model/OCR/PDFium runtime parity remains
intentionally gated by the current no-GPU/no-live-model scope.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
or converter behavior: fonts, CMaps, stream filters, xref repair, metadata,
annotations, forms, page geometry, image/filter metadata, and supplied-boundary
table or equation handoffs.
