# PDF Engine Handoff Core Current Base - Structure Attributes

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T164152Z`
Accepted base: `f548c0e7c0c0e27d77af5a4032e60b4aaf51015e`

## Scope

This slice adds bounded native fake-runner inspection for produced-PDF tagged structure attribute metadata. `PdfEngineHandoff` now exposes `pdfStructureAttributes` and `finalPdfStructureAttributes` for `/StructElem` `/A` dictionaries and referenced attribute objects.

The handoff records:

- owning structure element object and `/S` type;
- inline or referenced attribute dictionary source;
- `/O` owner, `/R` revision, layout placement/writing/text/block/inline alignment, and `/BBox`;
- list numbering metadata;
- table row span, column span, scope, and header references.

No Pandoc, renderer, PDF engine, JavaScript, external validator, or online service is executed.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` failed with `1 test files, 885 assertions, 1 failures` because `pdfStructureAttributes` was absent.
- Final focused: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 893 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed.
- PHP lint passed for `lanes/pandoc/src/PdfEngineHandoff.php`, `lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- Adds one named focused PHP PASS case.
- `PdfEngineHandoffTest.php` focused assertions increase by `+8` in the new structure-attribute test path.
- `lane-status.json` `phpPass` increases from `1695` to `1696`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped` increases from `2115` to `2116`.
- PDF engine handoff inventory moves from `12` to `13` mapped cases and from `108` to `116` focused assertions.

## Non-Overlap

This does not repeat prior PDF-engine slices for XMP/PDF-A metadata, output intents, URI base, page display metadata, LegalAttestation metadata, tagged structure-root metadata, active actions, AcroForm dictionary/calculation-order metadata, form-field actions/action targets, page resource source inventory, transition direction, embedded files, or annotation appearance/rich-media metadata. It narrows the new behavior to structure element attribute dictionaries.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP `PdfEngineHandoff` PDF object parser, value parser, fake-runner result wiring, and WordPress PDF handoff example. Full upstream Pandoc PDF writer parity, TeX/Typst/browser/roff rendering, PDF/UA validation, JavaScript execution, external PDF validators, online services, live provider tests, and live-service provider tests remain out of scope for this lane slice.

## Follow-Up

Next PDF-engine work should choose a non-overlapping produced-PDF handoff gap such as parent-tree number-tree details, role/class maps beyond structure attributes, marked-content property inheritance edges, or additional PDF/UA review metadata while preserving the no-engine fake-runner boundary.
