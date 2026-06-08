# Pandoc PDF Engine Handoff Current-Base Slice

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T130253Z`
- Accepted base: `1ea9ca5bf68d5249e21c102f28ab7d021c8d674e`
- Behavior: native produced-PDF page resource-class metadata handoff.

`PdfEngineHandoff` now preserves bounded `/ProcSet`, `/Pattern`, and `/Shading`
resource names from fake-produced PDF page resource dictionaries. The metadata
is reported through `pdfPageResourceSources` and carried into
`finalPdfPageResourceSources` for multipass fake-runner summaries, so WordPress
PDF review packets can flag renderer-created procedure sets, tiling/shading
patterns, and gradient resources without running a PDF engine.

## Evidence

- Baseline focused command before the slice: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Baseline result: `1 test files, 854 assertions, 0 failures`
- Red-first command after adding the new expectations: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Red-first result: `1 test files, 847 assertions, 1 failures`; `pdfPageResourceSources` only reported `Font`, `XObject`, `ColorSpace`, `ExtGState`, and `Properties`.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Final result: `1 test files, 865 assertions, 0 failures`
- Focused delta: `+1` PHP PASS case, `+11` assertions
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Example result: `pdf engine handoff self-test ok`
- Manifest delta: `benchmarkDenominator.mapped` `2066 -> 2067`; PDF engine handoff core `12 -> 13` cases and `108 -> 119` assertions.

## Non-Overlap

This slice avoids the previously accepted PDF XMP/PDF-A, output-intent,
page-output-intent, tagging/structure-tree, URI base, page-display, name-tree
inventory, active-action, annotation-detail, link-target, embedded-file,
encryption, catalog presentation/permission metadata, marked-content
properties, destination options, page content operator summaries, font/image/
color-space/form-XObject/graphics-state resource summaries, and optional-content
metadata surfaces. It owns only the page resource-source inventory expansion for
`/ProcSet`, `/Pattern`, and `/Shading` names in produced PDF bytes.

## Dependency Closure

No new support component is needed. The implementation reuses existing native
PHP `PdfEngineHandoff` PDF object, value, array, dictionary, page-tree, and
fake-runner inspection helpers. Pandoc, Cabal/Haskell runners, TeX/PDF engines,
Typst, browser renderers, roff renderers, external PDF validators, JavaScript,
online services, live provider tests, and live-service provider tests remain out
of scope for this lane slice.

## Follow-Up

Potential next PDF-engine gaps remain safer stream/filter policy metadata,
viewer-review diagnostics, richer destination action validation, and real
renderer parity, all still bounded away from engine execution unless explicitly
authorized.
