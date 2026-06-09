## Pandoc PDF Engine Handoff: Structure ID Tree Policy

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T042553Z`

Base accepted HEAD: `11fc57ec36d6cc974a7a65f55020cfb9f1af6d59`

Behavior added:

- `PdfEngineHandoff` now emits `pdfStructureIdTreePolicy` and `finalPdfStructureIdTreePolicy` for fake-produced PDF bytes.
- The policy reviews tagged-PDF `/IDTree` mappings against extracted `/StructElem` objects.
- Review issues cover duplicate IDs, IDs outside `/Limits`, missing object references, references that resolve but are not `/Type /StructElem`, and `/StructElem /ID` values absent from the ID tree.
- Structure element extraction now surfaces `/ID` so reviewer handoffs can cross-check ID-tree coverage.

Focused evidence:

- Baseline before the patch:
  - `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1219 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1245 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - `pdf engine handoff self-test ok`

Delta:

- `+1` focused PHP PASS case.
- `+26` focused assertions.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moved `2698 -> 2699`.
- `pdfEngineHandoffCoreCases` moved `12 -> 13`.
- `pdfEngineHandoffCoreAssertions` moved `108 -> 134`.

Dependency closure:

- No new support component is needed.
- This reuses bounded native PHP PDF object/dictionary/string/reference extraction, existing structure element extraction, fake-runner diagnostics, the lane-local WordPress smoke example, and the focused PHP test runner.
- Real Pandoc PDF generation, TeX engines, Typst, browser renderers, Haskell test binaries, office tools, zip/unzip, online services, and live-provider tests remain intentionally out of scope for this slice.

Non-overlap:

- This follows the prior parent-tree policy slice and does not repeat parent-tree MCID review, signature lock policy review, name-tree policy review, annotation/form/security extraction, or external PDF rendering.
- The next useful PDF handoff target is another non-overlapping tagged-PDF/PDF-UA policy check, such as role/class-map consistency or page-to-structure coverage.
