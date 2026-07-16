Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T155213Z`
Base: `f071fefb2a76a8e9eb3969229618987f332d5aff`

## Scope

- Extended the native PHP `PdfEngineHandoff` fake-runner output inspection with
  bounded produced-PDF Form XObject metadata.
- The handoff now reports `pdfFormXObjects` / `pdfFormXObjectFilters` from
  `fakeRun()` and `finalPdfFormXObjects` / `finalPdfFormXObjectFilters` from
  `fakeRunSequence()`.
- Captured page number, page object, resource name, referenced Form XObject,
  inherited-resource status, `/BBox`, `/Matrix`, nested `/Resources` presence,
  transparency `/Group` subtype/color-space/isolation/knockout metadata,
  filters, stream byte counts, and stream SHA-256 hashes.

## Source-Truth Boundary

- Uses the accepted static Pandoc inventory plus the
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This is a bounded fake-runner PDF-output diagnostic for renderer-produced
  bytes. It does not implement or invoke Pandoc, TeX/PDF engines, Typst,
  browser renderers, roff renderers, external PDF validators, JavaScript, online
  services, or live provider tests.

## WordPress Path

- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review
  packet smoke exposes Form XObject overlay metadata alongside existing image,
  font, page, tagging, signature, active-action, optional-content, form-field,
  encryption, sidecar, log, SyncTeX, and recorder diagnostics.
- This lets a review queue distinguish reusable/vector appearance resources
  from raster image XObjects before handing a PDF export off to any renderer.

## Non-Overlap

This slice does not repeat prior PDF engine-family argv mapping, template/header
resource handoff, expected TeX sidecar inventory, source/resource artifact
hashing, log warning/error extraction, missing renderer executable triage,
bibliography sidecars, SyncTeX/source-map extraction, TeX recorder `.fls`
dependency parsing, TeX transcript include graph parsing, trailer/xref/object
stream diagnostics, page tree boxes/rotations/labels/timings, font/image
XObject summaries, outlines, document-info/language/XMP/PDF-A/output-intent
metadata, catalog viewer preferences/named destinations, tagging and structure
trees, annotations/links/embedded files, AcroForm fields, digital signatures,
active actions, optional content, or encryption preflight.

## Dependency Closure

- No new support component is needed.
- Reuses native PHP `PdfEngineHandoff` byte-level PDF inspection and fake-runner
  diagnostics.
- Upstream-runner dependency closure remains gated on a hydrated Pandoc checkout
  at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus non-mutating Cabal
  planning for `test:test-pandoc` and `test:test-pandoc-lua-engine`.

## Verification

- Rework-note check:
  `ls /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  found no matching Pandoc rework notes for this slice.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed before implementation with `1 test files, 458 assertions, 1 failures`
  because `pdfFormXObjects` was absent.
- Focused after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 467 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- PHP syntax checks:
  `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  passed with `json ok`.
- Diff check:
  `git diff --check -- lanes/pandoc` passed with no output.
- Root harness:
  not run - isolated micro-slice.
