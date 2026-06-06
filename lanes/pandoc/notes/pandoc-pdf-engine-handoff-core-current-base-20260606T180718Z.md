# Pandoc PDF Engine Handoff ExtGState Slice

Date: 2026-06-06 UTC
Base accepted HEAD: 11a2e57d1384f7898502502ab620e40838291fb1
Micro-slice: pandoc-pdf-engine-handoff-core-current-base-20260606T180718Z

## Summary

`PdfEngineHandoff` now inspects bounded produced-PDF page `/ExtGState`
resources in fake-runner output without invoking Pandoc or any PDF engine. The
handoff records page/resource provenance, inherited page-tree resource state,
inline versus indirect graphics-state dictionaries, stroking and nonstroking
alpha, blend modes, overprint flags/mode, alpha-source/text-knockout flags, and
soft-mask references.

The WordPress PDF engine smoke now exposes `pdfGraphicsStates`,
`pdfGraphicsStateBlendModes`, `finalPdfGraphicsStates`, and
`finalPdfGraphicsStateBlendModes` so review packets can surface opacity,
blend-mode, and soft-mask provenance from produced artifacts.

## Status Delta

- `lane-status.json` `phpPass`: 1381 -> 1382.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 1794 -> 1795.
- `pdfEngineHandoffCoreCases`: 10 -> 11.
- `mappedPdfEngineHandoffCoreCases`: 10 -> 11.
- `pdfEngineHandoffCoreAssertions`: 95 -> 105.
- Focused PDF test movement: 656 -> 666 assertions.

## Source Truth And Scope

This slice stays within the existing bounded PDF fake-runner contract: inspect
metadata already present in produced PDF bytes and report deterministic handoff
diagnostics. It reuses the native PHP PDF object parser and page-tree resource
inheritance scanners already used for fonts, image XObjects, and Form XObjects.

No Pandoc, Cabal/Haskell runner, TeX/PDF engine, Typst, browser renderer, roff
renderer, external PDF validator, online service, live provider test, or
live-service provider test was executed.

## Verification

- Red-first focused run:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed with `1 test files, 656 assertions, 1 failures` because
  `pdfGraphicsStates` was not emitted for produced-PDF `/ExtGState` resources.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 666 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- Syntax checks:
  `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- Whitespace check:
  `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
PDF object tokenizer/value readers, page-tree traversal, fake-runner
diagnostics, lane test harness, and WordPress smoke output path.

Follow-up remains bounded: full graphics-state rendering, blend compositing,
color-management validation, PDF/A or PDF/UA validation, live engine execution,
and full upstream Pandoc/Haskell runner parity are out of scope for this slice.
