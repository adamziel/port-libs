# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T003008Z`

Base accepted HEAD: `abe9ba451f2204d997f7a6665fcf6e8510428993`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bibliography sidecar
  classification for `.bcf`, `.bbl`, `.blg`, and `.run.xml` files produced by
  TeX-style bibliography workflows.
- The fake runner now records bibliography artifact SHA-256 hashes, bibliography
  log file paths, bibliography warnings, bibliography errors, and
  Biber/BibTeX rerun-needed state without invoking BibTeX, Biber, Pandoc, or a
  PDF renderer.
- Bibliography log errors now fail otherwise PDF-like fake results with
  `bibliography-log-error`, so stale PDFs cannot hide a failed bibliography
  handoff.
- Extended `PdfEngineHandoff::fakeRunSequence()` to aggregate bibliography
  warnings/errors, preserve final bibliography artifact hashes, and report when
  a final fake pass clears an earlier bibliography rerun requirement.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress review
  packets expose first-pass bibliography sidecars and a final fake `.bbl`
  artifact after rerun clearing.

## Source Truth

- Uses the accepted Pandoc static inventory plus the existing
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports the bounded PDF handoff contract for bibliography side effects:
  record the data an isolated renderer/bibliography runner would have produced,
  classify missing or failed bibliography handoffs, and keep rerun state
  explicit for import review queues.
- It does not implement TeX, Typst, browser layout, roff, PDF rendering,
  Pandoc execution, external template rendering, BibTeX, Biber, citeproc, or
  upstream Haskell runner parity.

## Verification

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 143 assertions, 1 failure. The new bibliography
  sidecar test failed because `bibliographyArtifactsSha256` and related result
  keys were absent.

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 162 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);'`
  passed.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, expected sidecar
inventory, engine warning/error log extraction, generated PDF output metric
validation, or multipass rerun-state aggregation. The new surface is
bibliography sidecar classification and bibliography rerun/error handoff.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, or legacy DOC/CFB behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted Pandoc AST,
`MarkdownReader`, and `LatexWriter` surfaces. Real PDF rendering and real
bibliography execution remain intentionally out of scope: TeX, Typst, browser,
roff, PDF engines, BibTeX, Biber, citeproc, and bibliography managers must stay
in separate execution environments and are not activated by this lane patch.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.

## Follow-Up

- Keep resource-file dependency manifests as a separate PDF handoff slice.
- Keep SyncTeX/source-map metadata as a separate PDF handoff slice.
- Keep any real renderer execution policy as a separate bounded planning slice
  that still does not execute engines from this lane.
