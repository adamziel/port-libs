# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260604T222956Z`

Base accepted HEAD: `229db65917c88ede5fb968cbc6030180ac381585`

## Behavior Added

- Extended `PdfEngineHandoff` planning with expected engine sidecar artifact
  paths for LaTeX-family and ConTeXt handoffs, plus the primary engine log
  path when one is known.
- Extended the fake runner with produced engine-artifact SHA-256 hashes,
  engine log file inventory, warning extraction, fatal error extraction, and
  rerun-needed diagnostics from stdout, stderr, and sidecar `.log` bytes.
- Empty sidecar bytes are now accepted by fake-runner file maps, because TeX
  sidecars such as `.out` may be present but empty.
- Fatal renderer log lines now fail the fake-runner result with
  `engine-log-error` even when fake PDF bytes are present, so a review packet
  cannot hide a failed renderer handoff behind a stale PDF.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review
  packet smoke exposes sidecars, log warnings, rerun diagnostics, and artifact
  hashes without invoking a renderer.

## Source Truth

- Uses the accepted Pandoc static inventory plus the existing
  `pandoc-pdf-engine-handoff-core` lane contract as source truth for this
  bounded support-library behavior.
- This ports the PDF handoff diagnostics contract: preserve the planned
  intermediate/source files, classify the side effects a renderer would
  produce, and surface actionable renderer log state to a later isolated
  runner or WordPress import review queue.
- It does not implement TeX, Typst, browser layout, roff, PDF rendering,
  Pandoc execution, external template rendering, or upstream Haskell runner
  parity.

## Verification

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed the new sidecar/log expectations: 1 file, 77 assertions, 3 failures.

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 file, 95 assertions, 0 failures, 10 PASS lines.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 13 files, 3,689
  assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed.

`git diff --check -- lanes/pandoc` is run in the final verification pass.

## Non-Overlap

This does not repeat the prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, or basic PDF-byte checks.
The new surface is sidecar artifact planning plus fake-runner log diagnostics.
It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX, math/TeX conversion, charset/Unicode, or
legacy DOC/CFB behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the existing Pandoc AST,
`MarkdownReader`, and `LatexWriter` surfaces. Real PDF rendering remains
intentionally out of scope: TeX, Typst, browser, roff, and PDF engines must
stay in separate execution environments and are not activated by this lane
patch.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.

Root harness: not run - isolated micro-slice.
