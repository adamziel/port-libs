# Pandoc PDF Engine Handoff Core Current Base 20260608T211334Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T211334Z`
- Accepted base: `860604a0752757d495f65dc774700e48fce8b337`
- Behavior: native fake-runner produced-PDF inspection now extracts page dictionary `/AA` lifecycle actions for page-open (`/O`) and page-close (`/C`) review rows without executing actions or renderers.

## Source Truth And Scope

The local upstream Pandoc checkout/cache was not available in this isolated worktree, so this slice follows the existing lane PDF handoff contract and neighboring focused `PdfEngineHandoffTest.php` fixtures as source truth. The implementation stays in native PHP and only parses bounded produced PDF bytes supplied to the fake runner.

Non-overlap: this does not repeat previous PDF engine sidecar/log/SyncTeX/recorder, XMP/PDF-A, output intent, tagged structure, page display/timing/viewport, annotations/actions, AcroForm actions, optional content, signatures/DSS, or platform launch action slices. It adds a page-numbered lifecycle handoff for page-level `/AA /O` and `/AA /C` dictionaries.

## Implementation

- `PdfEngineHandoff::fakeRun()` now returns `pdfPageActions`.
- `PdfEngineHandoff::fakeRunSequence()` now returns `finalPdfPageActions`.
- Produced PDF inspection traverses the catalog `/Pages` tree, resolves direct and indirect page `/AA` dictionaries, summarizes `/O` and `/C` actions through the existing bounded action summarizer, and records:
  - page number and page object
  - trigger and trigger label
  - action type and target
  - JavaScript byte count and SHA-256 when applicable
- Diagnostics now include `pdf-byte-page-actions`, per-trigger counts, per-action-type counts, and script counts.
- The WordPress PDF handoff smoke summary exposes `pdfPageActions` and `finalPdfPageActions`.

## Evidence

- No rework note existed for this lane:
  `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  `1 test files, 965 assertions, 0 failures`
- Red-first after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  `1 test files, 967 assertions, 1 failures`
  Failure was the expected missing `pdfPageActions` field.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  `1 test files, 975 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  `pdf engine handoff self-test ok`

## Dependency Closure

No new support component is needed. This reuses the native `PdfEngineHandoff` PDF object/dictionary scanner and existing bounded active-action summarizer. No Pandoc, TeX/PDF engine, Typst, browser renderer, roff, JavaScript runtime, external PDF validator, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Potential follow-up is page-action `/Next` chain expansion or Rendition/media action review metadata. Keep it separate from this page lifecycle handoff and keep action execution disabled.
