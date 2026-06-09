# Pandoc PDF Engine Handoff Active Action Policy Slice

Session: `port-dev-pandoc-pdf-handoff-20260609T071750Z`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T071750Z`
Base accepted HEAD: `606e24ec818a38feb2a796c2f2b7d182ce531afd`
Date: 2026-06-09 UTC

## Scope

Implemented one bounded native fake-runner diagnostic for already-produced PDF
bytes: active-action review policy metadata. `PdfEngineHandoff` now summarizes
the extracted active actions into `pdfActiveActionPolicy` and
`finalPdfActiveActionPolicy`, including action/source counts, source
categories, action types, chained `/Next` action count and max depth, script
actions, remote targets, launch actions, form-changing actions, review status,
and deterministic issue diagnostics.

This does not execute JavaScript, named actions, launch actions, submit-form
actions, Pandoc, Haskell runners, PDF engines, browser renderers, or external
PDF validators. It only maps produced fake-runner PDF bytes into review
metadata for WordPress import queues.

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` files existed
  in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` before editing.
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed with `1 test files, 1356 assertions, 1 failures` while
  `pdfActiveActionPolicy` was absent.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1364 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Adds one mapped PDF engine handoff behavior.
- Adds 6 focused assertions inside the existing chained-action PDF handoff
  PHP PASS case; `phpPass` remains `2482` because no new PASS case was added.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves `2861 -> 2862`.
- PDF engine inventory moves `12 -> 13` mapped cases and `108 -> 114`
  focused inventory assertions.

## Non-Overlap

This slice does not repeat page lifecycle `/AA` action policy, page timings,
viewer preference policy, name-tree policy, JavaScript name-tree extraction,
platform launch target extraction, form submit/export policy, signatures,
visual signature appearance policy, annotations, collection metadata, page
viewports, page content streams, catalog requirements, or associated files. It
only adds aggregate review policy metadata over active actions already
extracted from produced PDF bytes.

## Dependency Closure

No new native support component is needed. The slice reuses
`PdfEngineHandoff` produced-PDF object parsing, active-action extraction,
fake-runner diagnostics, and the existing WordPress PDF handoff example. Full
upstream Pandoc/Haskell runner parity, TeX/PDF engine rendering,
Typst/browser/roff rendering, JavaScript/action execution, external PDF
validation, and online services remain out of scope for this isolated lane.

## Follow-Up

Next non-overlapping PDF engine handoff work can target richer annotation
appearance policy, artifact consistency checks, PDF/A active-content validation
metadata, or another bounded produced-byte review gap without running external
PDF engines, validators, browser renderers, JavaScript, or online services.
