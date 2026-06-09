# Pandoc PDF Engine Handoff Page Action Policy Slice

Session: `port-dev-pandoc-pdf-handoff-20260609T055907Z`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T055907Z`
Base accepted HEAD: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`
Date: 2026-06-09 UTC

## Scope

Implemented one bounded native fake-runner diagnostic for produced PDF bytes:
page lifecycle action policy metadata. `PdfEngineHandoff` now summarizes page
`/AA` open and close actions into `pdfPageActionPolicy` and
`finalPdfPageActionPolicy`, including affected pages, open/close page lists,
trigger counts, action type counts, script action count, remote target count,
launch action count, review status, and deterministic issue diagnostics.

This does not execute JavaScript, named actions, launch actions, submit-form
actions, Pandoc, Haskell runners, or any PDF rendering engine. It only maps
already-produced fake-runner PDF bytes into review metadata for WordPress import
queues.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed
  in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` before editing.
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed with `1 test files, 1298 assertions, 1 failures` while
  `pdfPageActionPolicy` was absent.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1317 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`,
  `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Adds one focused PHP PASS case.
- `PdfEngineHandoffTest.php` focused assertion count moves from the prior
  lane-status baseline `1305` to final `1317` assertions.
- `lanes/pandoc/lane-status.json` `phpPass` moves from `2411` to `2412`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `2800` to
  `2801`.
- PDF engine inventory moves from `12` mapped cases and `108` assertions to
  `13` mapped cases and `120` assertions.

## Non-Overlap

This slice does not repeat PDF page timing `/Dur` and `/Trans` policy, viewer
preference policy, name-tree policy, active-action extraction, JavaScript name
tree extraction, platform launch target extraction, form submit/export policy,
signature ByteRange policy, visual signature appearance policy, annotations,
collection metadata, page viewports, page content streams, or catalog
requirements. It only adds page-level lifecycle action policy summary derived
from page `/AA` open/close actions already extracted by the fake-runner parser.

## Dependency Closure

No new native support component is needed. The slice reuses
`PdfEngineHandoff` produced-PDF object parsing, page action extraction, the
existing action target scheme helper, fake-runner diagnostics, and the existing
WordPress PDF handoff example. Full upstream Pandoc/Haskell runner parity,
TeX/PDF engine rendering, Typst/browser/roff rendering, JavaScript/action
execution, and external PDF validation remain out of scope for this isolated
lane.

## Follow-Up

Next non-overlapping PDF engine handoff work can target richer artifact
consistency, additional annotation appearance policy, active-action chain depth
policy metadata, or another bounded produced-byte review gap without running
external PDF engines, validators, browser renderers, or online services.
