# Pandoc PDF action remote scheme provenance

Bead: `plib-fw574`

This slice extends native PHP fake-produced PDF action provenance so remote targets in active actions and page lifecycle actions keep scheme buckets, not just aggregate remote target counts.

Covered policy surfaces:

- `pdfActiveActionPolicy.remoteTargetSchemes`
- `finalPdfActiveActionPolicy.remoteTargetSchemes`
- `pdfPageActionPolicy.remoteTargetSchemes`
- `finalPdfPageActionPolicy.remoteTargetSchemes`
- diagnostics:
  - `pdf-byte-active-action-policy-remote-scheme:{scheme}:{count}`
  - `pdf-byte-page-action-policy-remote-scheme:{scheme}:{count}`

The implementation remains bounded to parsed bytes supplied to `PdfEngineHandoff::fakeRun()` / `fakeRunSequence()`. It does not shell out to Pandoc, Typst, TeX, office suites, browser engines, archive tools, Node tooling, or external PDF validators.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Focused result: `1 test files, 3463 assertions, 0 failures`.
