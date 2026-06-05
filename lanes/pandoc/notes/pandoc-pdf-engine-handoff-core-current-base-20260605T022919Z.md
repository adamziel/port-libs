# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T022919Z`

Base accepted HEAD: `b80c68434be07af571e5625640d1b99ec3344e2d`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded missing PDF engine
  executable diagnostics.
- Fake-runner results now expose `engineMissingProgram` and
  `engineMissingProgramName` so isolated renderer packets can distinguish a
  missing `xelatex`, `typst`, browser/HTML, or roff executable from a renderer
  that actually ran and failed.
- Missing-program state can come from an explicit fake-runner
  `missingProgram` flag or from command-not-found/ENOENT-style stdout/stderr
  paired with the planned engine program.
- Missing executable packets fail with `engine-program-missing` before generic
  `engine-exit-*` or missing-PDF output diagnostics. Ordinary renderer exits
  still report the existing engine-exit reasons.
- Extended `PdfEngineHandoff::fakeRunSequence()` to preserve the final
  missing-program state from the final fake pass.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review
  packet smoke exposes renderer-absent triage metadata alongside the existing
  PDF, resource, bibliography, recorder, SyncTeX, log, and multipass
  diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` dependency row as source truth.
- The dependency row explicitly includes missing-program diagnostics in the
  PDF handoff contract. This slice ports that bounded fake-runner behavior
  without executing any PDF engine.
- It does not implement or invoke Pandoc, TeX/PDF engines, Typst, browser
  layout/rendering, roff, BibTeX, Biber, external template engines, Haskell
  test binaries, or online services.

## Verification

Baseline focused check before adding the red test:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 215 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 217 assertions, 1 failure. The new missing-program
  test failed because exit 127 still reported `engine-exit-127`.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 226 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 19 test files, 5799 assertions, 0 failures.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.

- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, expected ordinary TeX
sidecar inventory, engine warning/error log extraction, bibliography sidecar
classification, generated PDF output metric validation, resource-file
validation, SyncTeX/source-map extraction, TeX recorder `.fls` dependency
parsing, or multipass rerun-state aggregation. The new surface is explicit
missing PDF engine executable triage in fake-runner results.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Real PDF rendering, real executable discovery, real
`.fls` generation, real SyncTeX generation, real bibliography execution, and
remote resource fetching remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
