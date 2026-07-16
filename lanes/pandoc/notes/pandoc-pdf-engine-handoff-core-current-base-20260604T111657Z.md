# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260604T111657Z`

Base accepted HEAD: `c47f567dd150ea86cee0136367fb0248f23fe42a`

## Behavior Added

- Extended `PdfEngineHandoff` with bounded PDF source-planning inputs:
  `templatePath`, `includeInHeader`, `resourcePaths`, and keyed template
  `variables`.
- Records Pandoc-style writer arguments such as `--template`,
  `--include-in-header`, `--resource-path`, and repeated `-V key=value`
  variable entries without invoking Pandoc or a PDF renderer.
- Merges document metadata with explicit template variables for reviewer
  handoff while keeping explicit variables as the writer-argument source.
- Records required PDF source artifacts for template and header files, and the
  fake runner now reports missing artifacts or artifact SHA-256 hashes.
- Rejects unsafe parent/absolute template, header, resource, and invalid
  variable inputs before producing a handoff plan.
- Updated the WordPress PDF review packet smoke to include a template, header,
  resource path, template variables, and source-artifact validation.

## Source Truth

- Uses the accepted Pandoc static inventory plus the recorded
  `pandoc-pdf-engine-handoff-core` dependency row as source truth for this
  bounded support-library behavior.
- This ports the PDF source handoff contract: record the intermediate writer
  inputs and artifact requirements that a later isolated renderer would need.
- It does not implement TeX, Typst, browser layout, roff, PDF rendering,
  Pandoc execution, external template rendering, or upstream Haskell runner
  parity.

## Verification

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed exactly the new source-artifact/template cases: 1 file, 52
  assertions, 3 failures.

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 file, 75 assertions, 0 failures, 7 PASS lines.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 9 files, 3047
  assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed.
- `php -r '$p="lanes/pandoc/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  passed.

`git diff --check -- lanes/pandoc` is run in the final verification pass.

## Non-Overlap

This does not repeat shared ZIP package metadata, archive compression streams,
math/TeX conversion, LaTeX writer math handling, doctemplates, DOCX/ODT
package parsing, table geometry, citation/CSL, OPC relationships, or the prior
PDF engine-family argv and PDF-byte fake-runner checks. The new surface is PDF
template/header/resource/variable source handoff and required source-artifact
validation only.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the existing Pandoc AST,
`MarkdownReader`, and `LatexWriter` surfaces. Full PDF rendering remains
intentionally out of scope: real TeX, Typst, browser, roff, and PDF engines
must stay in separate execution environments and are not activated by this
lane patch.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.

Root harness: not run - isolated micro-slice.
