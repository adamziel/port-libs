# PDF Engine Handoff: Structure ClassMap

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T195247Z`
Base: `bc31b89388716ac71ed898fdfd5e12b1bec174da`

## Behavior

- Added bounded fake-produced PDF inspection for tagged-PDF `/StructTreeRoot /ClassMap` dictionaries.
- Reuses the existing PDF dictionary/reference parser and structure-attribute summarizer to expose ClassMap layout, list, and table-cell attributes as `pdfStructureClassMap` and `finalPdfStructureClassMap`.
- Emits diagnostics for total ClassMap entries, class names, attribute owner counts, and table-cell ClassMap attributes.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 925 assertions, 0 failures`.
- Focused: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 937 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed with `pdf engine handoff self-test ok`.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed.
- `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No new support component is needed. The slice reuses `PdfEngineHandoff` PDF byte inspection primitives and stays inside the fake-runner handoff contract. Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, JavaScript execution, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not modify existing tagging metadata, RoleMap extraction, parent-tree mappings, structure element accessibility fields, or per-element `/A` attribute metadata. Follow-up PDF work can target element `/C` class usage or deeper RoleMap/ClassMap interaction.
