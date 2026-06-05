# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T005825Z`

Base accepted HEAD: `41cae8b6fd1e5314059c74ad58c304aea88484db`

## Behavior Added

- Extended `PdfEngineHandoff::plan()` with bounded PDF resource dependency
  manifests for local image references discovered from the Pandoc-like AST and
  explicitly declared resource files such as bibliography or media sidecars.
- Remote resource references are recorded for review but are not fetched.
  Unsafe, query-bearing, or fragment-only document resource references are kept
  out of the required local file list.
- Extended `PdfEngineHandoff::fakeRun()` to validate declared resource files
  against fake-runner file bytes, report SHA-256 hashes for present resources,
  and fail with `missing-resource-file` when required resources are absent.
- Extended `PdfEngineHandoff::fakeRunSequence()` to expose final resource
  artifact hashes and missing resource files from the final fake pass.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress PDF review
  packets expose local media, declared bibliography resources, and remote media
  review references without resolving them through a renderer.

## Source Truth

- Uses the accepted Pandoc static inventory plus the existing
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports the bounded PDF handoff contract for renderer dependencies:
  record which local files a renderer workspace must receive, keep remote
  resources explicit for review, and validate fake produced packets without
  invoking Pandoc or any rendering engine.
- It does not implement TeX, Typst, browser layout, roff, PDF rendering,
  Pandoc execution, external template rendering, BibTeX, Biber, citeproc,
  resource fetching, or upstream Haskell runner parity.

## Verification

Baseline focused check before adding the red test:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 162 assertions, 0 failures.

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 158 assertions, 2 failures. The new resource manifest
  test failed because `resourceFiles` was absent, and the unsafe
  `resourceFiles` option was not rejected.

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 178 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 19 test files, 4947
  assertions, 0 failures.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource-path writer arguments, source-artifact validation, expected sidecar
inventory, log warning/error extraction, bibliography sidecar classification,
PDF output metric validation, or multipass rerun-state aggregation. The new
surface is required resource-file manifesting and fake-runner resource
validation.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, or legacy DOC/CFB behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted Pandoc AST,
`MarkdownReader`, and `LatexWriter` surfaces. Real PDF rendering, real
bibliography execution, and remote resource fetching remain intentionally out
of scope: TeX, Typst, browser, roff, PDF engines, BibTeX, Biber, citeproc,
bibliography managers, and online services must stay in separate execution
environments and are not activated by this lane patch.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.

## Follow-Up

- Keep SyncTeX/source-map metadata as a separate PDF handoff slice.
- Keep recursive TeX log dependency parsing as a separate fake-runner
  diagnostics slice.
- Keep remote-resource fetch policy and any real renderer execution policy as
  separate bounded planning slices that still do not execute engines from this
  lane.
