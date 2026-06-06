# PDF Engine Handoff Core Current Base - Catalog Requirements

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T233321Z`
Base accepted HEAD: `fb092ed186786bef855e1696220b3eb8b77788bd`
Date: 2026-06-06 UTC

## Behavior

`PdfEngineHandoff` now extracts bounded produced-PDF catalog policy metadata from fake-runner output bytes:

- `/NeedsRendering` as `pdfNeedsRendering` / `finalPdfNeedsRendering`.
- `/Requirements` arrays as `pdfCatalogRequirements` / `finalPdfCatalogRequirements`.
- Requirement subtype, object reference, inline or indirect handler object, handler type/name/code/version, and top-level requirement keys.
- Diagnostics for `pdf-byte-needs-rendering:*`, requirement count, subtype counts, and handler count.

This is a metadata handoff only. It does not execute JavaScript, requirement handlers, TeX/PDF engines, Typst, browser renderers, roff, Pandoc, Haskell runners, online services, or external PDF validators.

## Evidence

Baseline focused test before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 666 assertions, 0 failures`

Red-first focused test after adding the catalog requirements fixture:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 668 assertions, 1 failures`
- Failure: the new `/NeedsRendering` fixture returned `null`.

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- `git diff --check -- lanes/pandoc`

Final focused test result: `1 test files, 677 assertions, 0 failures`.

## Status Delta

- `lane-status.json` `phpPass`: `1417 -> 1418`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1830 -> 1831`.
- `pdfEngineHandoffCoreCases`: `10 -> 11`.
- `mappedPdfEngineHandoffCoreCases`: `10 -> 11`.
- `pdfEngineHandoffCoreAssertions`: `95 -> 106`.

## Non-Overlap

This slice avoids the recent PDF handoff work for XMP/PDF-A metadata packets, output intents, page output intents, tagged structure, URI base, page display metadata, page viewports, page content streams, annotation appearances, optional content memberships, ExtGState, and active-action extraction. It only adds catalog-level `/NeedsRendering` and `/Requirements` metadata.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP bounded PDF byte scanner, catalog dictionary lookup, indirect-object resolver, and fake-runner diagnostics already present in `PdfEngineHandoff`.

Full renderer parity remains outside this slice and would require explicit authorization for TeX/PDF engines, Typst, browser renderers, roff, or upstream Pandoc/Haskell runner work.
