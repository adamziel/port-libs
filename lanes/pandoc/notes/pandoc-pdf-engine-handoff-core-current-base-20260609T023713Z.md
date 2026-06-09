# pandoc-pdf-engine-handoff-core-current-base-20260609T023713Z

Base accepted HEAD: `de36f364dfd0ebd58cefb6a1b6c216bd3331ab5a`

Implemented a bounded native PDF fake-runner handoff for catalog name-tree `/Limits` policy review. The new `pdfNameTreePolicies` and `finalPdfNameTreePolicies` outputs summarize each catalog `/Names` category, per-node entry counts, `/Kids`, `/Limits`, OK/review status, and review issues for malformed child ranges: missing/invalid child limits, child limits outside parent bounds, overlapping or unsorted sibling ranges, out-of-order node limits, and names outside declared limits.

This is intentionally a support-library handoff only. It does not invoke Pandoc, TeX/PDF engines, browser renderers, Word, LibreOffice, zip/unzip, Haskell runners, external converters, online services, or live provider tests.

Focused evidence:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` - passed
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` - passed
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` - passed
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` - `1 test files, 1143 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` - `pdf engine handoff self-test ok`

Mapped movement:

- `phpPass`: `2161 -> 2162`
- `benchmarkDenominator.mapped`: `2585 -> 2586`
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`
- `pdfEngineHandoffCoreAssertions`: `108 -> 121`

Dependency closure: no new support component is needed. This reuses the existing native PHP PDF byte/dictionary walker and name-tree inventory helpers inside `PdfEngineHandoff`.

Non-overlap: prior PDF handoffs already covered output planning, sidecars, logs, destination inventories, name-tree category inventory, annotations, forms, signatures, encryption preflight, XMP/PDF-A, structure trees, and optional content. This slice only adds name-tree `/Limits` and `/Kids` policy review metadata.

Follow-up: a useful adjacent PDF handoff would validate bounded page-label number-tree balancing or tagged-PDF parent-tree reference integrity, still without running renderers.
