# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260604T233132Z`

Base accepted HEAD: `4e5b254a36b80b692f93413b376a79f6d854dcc7`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded generated-PDF artifact
  validation.
- Parses fake runner stdout/stderr/log text for `Output written on ...`
  summaries and records the declared output file, page count, and byte count.
- Rejects fake PDF bytes that start with `%PDF-` but do not end with a complete
  `%%EOF` trailer.
- Rejects stale PDF artifacts when the renderer log declares a byte count that
  does not match the supplied PDF bytes.
- Rejects mismatched output artifacts when the renderer log declares a
  different output PDF file.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review
  packet smoke exposes declared output metrics and PDF trailer validation
  without invoking a renderer.

## Source Truth

- Uses the accepted Pandoc static inventory plus the
  `pandoc-pdf-engine-handoff-core` dependency row as source truth for this
  bounded support-library behavior.
- This ports the fake-runner PDF artifact validation contract from the
  dependency row: generated/truncated PDF artifacts, command/log parity, and
  malformed engine-output diagnostics.
- It does not implement TeX, Typst, browser layout, roff, PDF rendering,
  Pandoc execution, external template rendering, or upstream Haskell runner
  parity.

## Verification

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed the new output-metric and truncated-artifact expectations: 1 file, 98
  assertions, 2 failures.

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 file, 115 assertions, 0 failures, 12 PASS lines.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 14 files, 3,753
  assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed.

`git diff --check -- lanes/pandoc` is run in the final verification pass.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, expected sidecar
inventory, warning/error log extraction, or rerun-needed diagnostics. The new
surface is generated PDF artifact validation against renderer-declared output
metrics and EOF trailer completeness.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX, math/TeX conversion, charset/Unicode, XML/
HTML5 DOM, or legacy DOC/CFB behavior.

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
