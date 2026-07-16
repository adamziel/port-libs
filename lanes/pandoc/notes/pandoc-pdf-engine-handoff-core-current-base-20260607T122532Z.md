# Pandoc PDF Engine Handoff Current-Base Slice

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260607T122532Z`
- Accepted base: `d254c38c5435b40c715dda98fb5188c595a288f7`
- Behavior: native produced-PDF destination option metadata handoff.

`PdfEngineHandoff` now extracts bounded explicit destination metadata from fake-produced PDF bytes without invoking a renderer. The slice covers catalog `/OpenAction`, catalog `/Names /Dests` name trees, legacy catalog `/Dests` dictionaries, outline `/Dest` and GoTo `/A /D`, and annotation `/Dest` and GoTo `/A /D` sources. It records destination names, named targets, page object references, fit modes, positional fit arguments, and fit-specific left/top/right/bottom/zoom fields, then carries the same records through `finalPdfDestinationOptions` in multipass fake-runner summaries.

## Evidence

- Baseline focused command before the slice: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Baseline result: `1 test files, 719 assertions, 0 failures`
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Final result: `1 test files, 727 assertions, 0 failures`
- Focused delta: `+1` PHP PASS case, `+8` assertions
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Example result: `pdf engine handoff self-test ok`
- Manifest delta: `benchmarkDenominator.mapped` `1917 -> 1918`; PDF engine handoff core `12 -> 13` cases and `108 -> 116` assertions.

## Non-Overlap

This slice avoids the previously accepted PDF XMP/PDF-A, output-intent, page-output-intent, tagging/structure-tree, URI base, page-display, name-tree inventory, active-action, annotation-detail, link-target, embedded-file, encryption, and catalog presentation/permission metadata surfaces. It owns only bounded destination-option fit argument metadata and fake-runner diagnostics for produced PDF bytes.

## Dependency Closure

No new support component is needed. The implementation reuses existing native PHP `PdfEngineHandoff` PDF object, dictionary, array, name-tree, and fake-runner inspection helpers. Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff renderers, external PDF validators, JavaScript, online services, live provider tests, and live-service provider tests remain out of scope for this lane slice.

## Follow-Up

Potential next PDF-engine gaps remain page resource inheritance, safer stream/filter metadata summaries, richer destination action validation, and viewer-review diagnostics, all still bounded to native produced-PDF byte inspection with focused PHP tests.
