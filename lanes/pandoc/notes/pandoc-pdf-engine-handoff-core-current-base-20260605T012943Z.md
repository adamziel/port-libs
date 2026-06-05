# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T012943Z`

Base accepted HEAD: `b8ecd33ac84e6271e1166e0af962cc0647a1264c`

## Behavior Added

- Extended `PdfEngineHandoff::plan()` so TeX-family handoffs that receive an
  explicit SyncTeX engine option such as `-synctex=1` record the expected
  `.synctex.gz` source-map sidecar without executing a renderer.
- Extended `PdfEngineHandoff::fakeRun()` to classify supplied `.synctex` and
  `.synctex.gz` artifacts as source-map sidecars, record SHA-256 hashes,
  decode bounded gzip bytes through the native `GzipStream` helper, extract
  SyncTeX `Input:<tag>:<path>` records, and summarize source line ranges.
- Stale source maps now fail the fake-runner result with
  `source-map-source-missing` when the sidecar does not reference the planned
  intermediate source file.
- Extended `PdfEngineHandoff::fakeRunSequence()` to preserve final source-map
  artifact hashes, files, inputs, and line ranges for multipass review packets.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review
  packet smoke exposes SyncTeX sidecar metadata alongside the existing PDF,
  resource, bibliography, log, and rerun diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the existing
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF handoff contract for source-map diagnostics:
  preserve the source-map sidecars an isolated TeX runner would produce and
  surface actionable source-file/line metadata to WordPress review queues.
- It does not implement or invoke Pandoc, TeX/PDF engines, Typst, browser
  layout/rendering, roff, BibTeX, Biber, external template engines, gzip
  binaries, Haskell test binaries, or online services.

## Verification

Baseline focused check before adding the red test:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 178 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 179 assertions, 1 failure. The new source-map test
  failed because `-synctex=1` did not add `handoff/review.synctex.gz` to the
  expected engine artifacts.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 196 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 19 test files, 5223 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.

Final lint and whitespace checks are recorded in the handoff response.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, expected ordinary TeX
sidecar inventory, engine log warning/error extraction, bibliography sidecar
classification, generated PDF output metric validation, resource-file
validation, or multipass rerun-state aggregation. The new surface is
SyncTeX/source-map sidecar planning and fake-runner source-map metadata.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted `GzipStream`
helper for bounded `.synctex.gz` decoding. Real PDF rendering, real SyncTeX
generation, real bibliography execution, and remote resource fetching remain
intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
