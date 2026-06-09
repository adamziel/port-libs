# Pandoc PDF Engine Handoff Annotation Appearance Policy Slice

Session: port-dev-pandoc-pdf-handoff-20260609T073203Z
Micro-slice: pandoc-pdf-engine-handoff-core-current-base-duplicate-20260609T073203Z
Base accepted HEAD: df259aa2eedc94083122c4983a2ea922c64e663c
Date: 2026-06-09 UTC

## Scope

This slice adds a bounded native PHP fake-runner diagnostic for produced PDF
bytes. `PdfEngineHandoff` now summarizes already extracted annotation `/AP`
appearance data into `pdfAnnotationAppearancePolicy` and
`finalPdfAnnotationAppearancePolicy`, including normal/rollover/down appearance
counts, selected-state coverage, appearance state names, appearance object
references, stream counts, byte totals, skipped streams, and review issues.

No TeX, Typst, browser/PDF renderer, visual validator, Pandoc binary, office
tool, external converter, online service, live provider test, or live-service
provider test was executed.

## Evidence

- No current pandoc lane rework note existed under `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline focused test before edits: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> `1 test files, 1364 assertions, 0 failures`.
- Final focused test after edits: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> `1 test files, 1376 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` -> `pdf engine handoff self-test ok`.
- PHP lint was run for changed PHP files.
- JSON validation was run for the updated lane status and upstream test manifest.
- `git diff --check -- lanes/pandoc` was run.
- Root harness was not run for this isolated micro-slice.

## Status Delta

- Added 1 focused PHP PASS case.
- Added 12 focused assertions.
- `lane-status.json` `phpPass`: 2497 -> 2498.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 2875 -> 2876.
- PDF engine handoff inventory: 12 -> 13 mapped cases, 108 -> 120 focused assertions.

## Non-Overlap

This does not repeat page action policy, active action policy, visual signature
appearance policy, signature byte ranges, annotation appearance extraction,
stream filter policy, or external PDF engine execution. It adds a policy layer
over produced-byte annotation appearance summaries that already exist in the
native fake-runner inspection path.

## Dependency Closure

No new support component is needed. The slice reuses existing produced-PDF
object parsing, annotation appearance extraction, fake-runner diagnostics, and
the WordPress PDF handoff example. External renderers and validators remain out
of scope by lane contract.

## Follow-Up

Next PDF handoff work should choose a non-overlapping produced-byte review gap,
such as artifact consistency policy, PDF/A active-content validation, output
intent diagnostics, or embedded-file handoff review metadata.
