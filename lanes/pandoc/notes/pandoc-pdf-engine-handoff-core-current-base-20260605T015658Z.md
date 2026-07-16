# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T015658Z`

Base accepted HEAD: `ecec2b9f3020be56e17c6e2e635bed38cedbf419`

## Behavior Added

- Extended `PdfEngineHandoff::plan()` so LaTeX-family handoffs that request
  recorder output with `-recorder` record the expected `.fls` dependency
  sidecar without executing a renderer. `latexmk` continues to expect `.fls`
  through its existing sidecar profile.
- Extended `PdfEngineHandoff::fakeRun()` to classify bounded `.fls` recorder
  files, hash them, parse `INPUT` and `OUTPUT` records, expose local TeX input
  files, external/system TeX inputs, and output sidecars, and fail fake packets
  with `missing-engine-input-file` when a local dependency listed by the
  recorder file is absent.
- Extended `PdfEngineHandoff::fakeRunSequence()` to preserve the final
  dependency artifact hashes, local/external inputs, outputs, and missing input
  diagnostics for multipass review packets.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review
  packet smoke exposes recorder dependency metadata alongside the existing PDF,
  resource, bibliography, log, rerun, and SyncTeX diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the existing
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF handoff contract for TeX recorder dependency data:
  preserve the dependency sidecar an isolated TeX runner would produce, keep
  local missing-input failures explicit, and retain system TeX dependencies as
  review metadata for WordPress import queues.
- It does not implement or invoke Pandoc, TeX/PDF engines, Typst, browser
  layout/rendering, roff, BibTeX, Biber, external template engines, Haskell
  test binaries, or online services.

## Verification

Baseline focused check before adding the red test:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 196 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 197 assertions, 1 failure. The new recorder dependency
  test failed because `-recorder` did not add `handoff/dependency.fls` to the
  expected engine artifacts.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 215 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.

Final lint, lane test, JSON, and whitespace checks are recorded in the handoff
response.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, expected ordinary TeX
sidecar inventory, engine log warning/error extraction, bibliography sidecar
classification, generated PDF output metric validation, resource-file
validation, SyncTeX/source-map extraction, or multipass rerun-state
aggregation. The new surface is TeX recorder `.fls` dependency planning,
parsing, missing local input validation, and multipass dependency handoff.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map contract. Real PDF rendering, real `.fls` generation, real SyncTeX
generation, real bibliography execution, and remote resource fetching remain
intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
