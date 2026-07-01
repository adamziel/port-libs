# CSV/TSV Input Prefix Diagnostic Summary

Work item: plib-pvtft

DelimitedTextReader now exposes stable input-prefix diagnostic rollups alongside the existing `inputPrefix` packet and mixed `diagnostics` list:

- `inputPrefixDiagnosticCount`
- `inputPrefixDiagnosticCodes`
- `inputPrefixDiagnostics`
- `inputPrefixDiagnosticSummary`

The summary is derived from the same bounded prefix diagnostics used by the main review packet. It records UTF-8 BOM handling, skipped whitespace-only prefix lines, NUL byte counts, other control-character counts, first content offset/line, selected/requested format, and sourcePath/extension context mismatch state.

The parser behavior is unchanged: supported BOM and whitespace-only prefix lines are still skipped before header parsing, CSV and TSV dialect defaults are preserved, null/control bytes remain in parsed cell text for review, and sourcePath/extension context remains metadata only.

Focused validation:

- `php -l lanes/pandoc/src/DelimitedTextReader.php`
- `php -l lanes/pandoc/tests/DelimitedTextReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php` with 1 file, 491 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/PandocConverterTest.php` with 2 files, 423 assertions, 0 failures

Full lane result and final gates:

- `php tools/run-tests.php lanes/pandoc/tests` completed with 534 files, 142339 assertions, 8912 failures, matching the broader baseline-red lane outside this CSV/TSV input-prefix slice
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- `git diff --cached --check`
- `git diff --check`
- conflict-marker scan of changed lane files
