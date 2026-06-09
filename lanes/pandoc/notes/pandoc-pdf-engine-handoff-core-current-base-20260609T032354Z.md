# Pandoc PDF Engine Handoff PageLabels Policy

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T032354Z`
Base: `ddc41a8931d632461ea1dfb31e90d2be40b8de1c`
Date: 2026-06-09 UTC

## Scope

This slice adds bounded native fake-runner inspection for produced PDF `/PageLabels` number-tree policy. It preserves the existing page-label extraction surface and adds `pdfPageLabelPolicy` on a fake run plus `finalPdfPageLabelPolicy` on a fake run sequence.

The new policy summary walks direct `/Nums` entries and bounded `/Kids`, records node objects, limits, page indexes, entry counts, kid counts, review status, and issues for malformed structures:

- missing child references
- invalid or out-of-order `/Limits`
- overlapping or unsorted kid limits
- out-of-order `/Nums`
- page indexes outside the produced page count
- page numbers outside node limits

No Pandoc, TeX, Typst, browser, Word, LibreOffice, Haskell test binary, zip/unzip, online service, or external PDF validator was run.

## Evidence

Rework-note check:

```bash
ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true
```

Result: no current Pandoc rework note was present for this lane.

Red-first focused test:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result before implementation: `1 test files, 1170 assertions, 1 failures`; the new PageLabels policy assertion failed because `pdfPageLabelPolicy` was not exposed.

Focused test after implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1178 assertions, 0 failures`.

Example smoke:

```bash
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
```

Result: `pdf engine handoff self-test ok`.

Syntax checks:

```bash
php -l lanes/pandoc/src/PdfEngineHandoff.php
php -l lanes/pandoc/tests/PdfEngineHandoffTest.php
php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php
```

Result: no syntax errors detected in all three changed PHP files.

Lane diff check:

```bash
git diff --check -- lanes/pandoc
```

Result: passed with no whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2224` to `2225`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2633` to `2634`
- `pdfEngineHandoffCoreCases`: `12` to `13`
- `mappedPdfEngineHandoffCoreCases`: `12` to `13`
- `pdfEngineHandoffCoreAssertions`: `108` to `118`
- Focused assertion delta in `PdfEngineHandoffTest.php`: `+10`

## Dependency Closure

No new support component is needed. This reuses the native `PdfEngineHandoff` fake runner, object/value parsing helpers, page-count extraction, page-label extraction, focused PHP tests, and local WordPress-oriented example smoke.

Upstream runner parity remains gated on a hydrated Pandoc checkout and Haskell test executables, which are intentionally not executed in this lane. The local handoff covers the bounded PHP format contract for PDF-output planning diagnostics.

## Non-overlap

This does not repeat prior engine planning, sidecar, log, SyncTeX, page box, page-label extraction, name-tree, destination, XMP, output intent, tagged-PDF, annotation, rich-media, embedded-file, form, signature, DSS, action, optional-content, collection, thread, encryption, or marked-content slices. It is limited to PageLabels number-tree policy diagnostics on fake-produced PDF bytes.

## Follow-up

The next non-overlapping PDF handoff can target tagged-PDF parent-tree integrity or xref repair diagnostics without introducing external engine execution.
