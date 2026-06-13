# IPYNB source digest diagnostics

Implemented a bounded native IPYNB reader metadata slice for cell source
diagnostics.

- `IpynbReader` now records per-cell source shape metadata for string, list,
  missing, and null source forms.
- Source diagnostics include source byte counts, normalized line counts,
  line-ending buckets, trailing/mixed line-ending flags, empty versus
  whitespace-only state, and SHA-256 digest/fingerprint metadata.
- Notebook-level metadata now aggregates source byte/line counts, source shape
  buckets, line-ending buckets, content-state counts, fingerprint counts, and
  duplicate fingerprint rows with cell indexes.
- The diagnostics are metadata-only and do not expose raw source text. Existing
  code/raw cell rendering remains unchanged.
- No Jupyter, Python runners, Pandoc, Node, browsers, online services, live
  providers, or external validators were invoked.

Verification:

- `php -l lanes/pandoc/src/IpynbReader.php`
- `php -l lanes/pandoc/tests/IpynbReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/IpynbReaderTest.php`
  passed: 1 file, 142 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 45 files, 75426 assertions, 0 failures.
