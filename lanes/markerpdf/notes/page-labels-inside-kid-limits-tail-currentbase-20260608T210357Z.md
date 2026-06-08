# markerpdf PageLabels Inside Kid Limits Tail Boundary Current Base

- Slice: `markerpdf-page-labels-boundary-current-base-20260608T210357Z`
- Scope: native no-GPU searchable-PDF PageLabels parsing under `lanes/markerpdf/**`.
- Source truth: PDF page-label number trees use `/Nums`, `/Kids`, and inclusive `/Limits`; the pinned upstream `sddai/markerPDF` manifest (`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) delegates searchable-PDF import metadata to PDF parser output, while OCR/model parity is out of scope for this lane.

## Behavior

This patch locks the current-base recovery boundary where a later PageLabels child has `/Limits` that start inside a range already claimed by an earlier child but includes a valid section after that earlier upper bound.

- Stale entries inside the earlier claimed range stay suppressed.
- The later child can still contribute non-overlapping tail sections beyond the earlier range.
- `PdfTextExtractor`, `extractLabeledPageTexts()`, `MarkerAppPreview::pageLabels()`, summary metadata, and page-image preview metadata stay aligned.

The implementation names the kid-limit suppression predicate in both the native text extractor and preview fallback instead of keeping the boundary test inline in two places.

## Evidence

- Added focused test: `lanes/markerpdf/tests/PdfPageLabelsInsideKidLimitsTailBoundaryCurrentBaseTest.php`.
- Added WordPress smoke: `lanes/markerpdf/examples/wordpress-pdf-page-labels-inside-kid-limits-tail-currentbase.php`.
- Expected focused delta: +1 PHP behavior test PASS and +1 WordPress scenario.

Commands run for handoff:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsInsideKidLimitsTailBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-inside-kid-limits-tail-currentbase.php
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsInsideKidLimitsTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsTouchingKidLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsOverlappingKidLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsInheritedTouchingKidLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsSameLowerExtensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsKidLowerMismatchBoundaryCurrentBaseTest.php
php lanes/markerpdf/examples/wordpress-pdf-page-labels-inside-kid-limits-tail-currentbase.php
git diff --check -- lanes/markerpdf
```

Results:

- PHP lint: all changed PHP files reported no syntax errors.
- Focused boundary run: 6 selected test files, 70 assertions, 0 failures.
- WordPress smoke: exits 0 with `inside_overlap_rejected=true`, `tail_after_claim_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Diff check: `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels work for touching endpoint kids, same-lower range extension rejection, inherited endpoint clipping, kid lower mismatch, duplicate catalog keys, Type/PageLabel validation, trailer-root fallback, UTF-8/UTF-16 prefixes, generation-exact operands, or value-node top-level keys. It covers the adjacent but previously unpinned case where an overlapping later child starts inside an earlier claim and then has a valid tail after the claim.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF parser, object lookup, number-tree parser, text extractor, and preview inventory path. It does not invoke Python, OCR/model workers, GPU dependencies, external PDF tools, or live services.
