# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260604T093250Z`

Base accepted HEAD: `1897321874cf88908aabd37434234bcbcba16d7e`

## Behavior Added

- Added `PdfEngineHandoff`, a bounded native PHP planner for Pandoc-style PDF
  engine handoff.
- Maps supported PDF engine names and absolute executable paths by basename
  into intermediate families:
  `latex`, `context`, `html5`, `ms`, and `typst`.
- Produces argv-only command plans for `pdflatex`, `lualatex`, `xelatex`,
  `latexmk`, `tectonic`, `context`, `pdfroff`, `wkhtmltopdf`, `weasyprint`,
  `prince`, `pagedjs-cli`, and `typst` without invoking any executable.
- Stages LaTeX intermediate source through the existing `LatexWriter`, keeps
  metadata summaries from the accepted Pandoc `meta` AST shape, and rejects
  unsafe absolute or parent-relative handoff paths.
- Adds fake-runner diagnostics for staged source validation, missing PDF
  output, non-PDF bytes, source mismatch, and engine exit failures.
- Added `examples/wordpress-pdf-engine-handoff.php`, a WordPress-relevant smoke
  showing a PDF review packet handoff and fake-runner result without executing
  a renderer.

## Source Truth

- Uses the accepted Pandoc static inventory plus the recorded
  `pandoc-pdf-engine-handoff-core` dependency row as source truth for this
  bounded support-library behavior.
- This ports the PDF-output handoff contract: choose the intermediate writer
  family, preserve engine options as argv entries, stage bounded source, and
  report fake-runner diagnostics.
- It does not implement TeX, Typst, browser layout, roff, PDF rendering,
  Pandoc execution, or upstream Haskell runner parity.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 file, 50 assertions, 0 failures, 5 PASS lines.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed.
- `git diff --check -- lanes/pandoc` passed.

## Non-Overlap

This does not repeat math/TeX conversion, LaTeX writer math handling,
doctemplates, ZIP/OPC, DOCX/ODT package parsing, table geometry, citation/CSL,
archive compression, or upstream-runner dependency audit behavior. The new
surface is PDF engine planning and fake-runner diagnostics only.

## Dependency Closure

No external support component is needed for this slice. It reuses the existing
Pandoc AST, `MarkdownReader`, and `LatexWriter` surfaces and adds the bounded
native PDF engine handoff support directly under `lanes/pandoc/src`. Full PDF
rendering remains intentionally out of scope: real TeX, Typst, browser, roff,
and PDF engines must stay in separate execution environments and are not
activated by this lane patch.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status. Broader local Pandoc lane verification remains subject to the
pre-existing archive-compression `GzipStream` blocker recorded by the previous
math slice.

Root harness: not run - isolated micro-slice.
