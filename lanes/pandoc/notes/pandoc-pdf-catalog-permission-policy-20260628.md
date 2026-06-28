# PDF catalog permission policy provenance

Slice: `plib-74vn4`

`PdfEngineHandoff` now summarizes produced-PDF catalog `/Perms` signatures into
a compact `pdfCatalogPermissionPolicy` review packet. The policy carries
permission-key counts, unique permission signature counts, byte-range and
contents coverage, reference transform method counts, transform permission
values, and review diagnostics for missing catalog permission byte ranges.

This remains metadata-only native PHP inspection. It does not execute PDF
engines, Typst, TeX, office suites, browser renderers, external validators, or
online services.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: focused `PdfEngineHandoffTest.php` passed with 1 file, 3651 assertions,
and 0 failures.
