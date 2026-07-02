# PDF Output Intent Profile Policy

Slice: `plib-ik65p` on 2026-07-02.

`PdfEngineHandoff` now summarizes produced-PDF output intent profile provenance without executing Typst, TeX/PDF engines, or external validators. The metadata-only rollup covers document and page output intents, PDF/A and PDF/X intent counts, profile component and alternate color-space maps, profile byte/hash/skip totals, and review issues for missing or skipped profile streams.

Focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffPdfOutputIntentProfilePolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffPdfOutputIntentProfilePolicyTest.php` with 1 file, 32 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffPdfOutputIntentProfilePolicyTest.php lanes/pandoc/tests/PdfEngineHandoffTypstBoundaryMatrixSummaryTest.php lanes/pandoc/tests/PdfEngineHandoffTest.php lanes/pandoc/tests/PdfReaderTest.php` with 4 files, 5,280 assertions, 0 failures

No external Pandoc, Typst, TeX/PDF engines, office suites, browsers, Node, zip/unzip, validators, or live services were invoked.
