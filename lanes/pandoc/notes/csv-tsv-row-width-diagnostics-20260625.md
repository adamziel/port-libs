# CSV/TSV Row Width Diagnostics - 2026-06-25

Slice: `plib-sxbdl`

## Scope

This closes the current CSV/TSV row-width diagnostics follow-up on the native
PHP `DelimitedTextReader` path. The reader already carried row-width summaries;
this slice makes the warning diagnostics themselves stable review records by
including the same row labels, expected widths, mismatch counts, and relaxed
padding rows exposed by the summary packet.

Covered behavior:

- uneven CSV row widths with a wider data row and a shorter final row;
- TSV blank rows and trailing empty fields;
- strict header-row policy mismatch diagnostics;
- relaxed pad-to-wide-row diagnostics;
- header/data width mismatch diagnostics;
- preserved quote, multiline, trailing-empty-field, blank-row, header, and table
  padding behavior.

## Verification

- `php -l lanes/pandoc/src/DelimitedTextReader.php`
- `php -l lanes/pandoc/tests/DelimitedTextReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`

Focused result: 2 files, 684 assertions, 0 failures.

No Pandoc binary, spreadsheet application, browser renderer, Node tooling,
online service, live provider, or external validator was used.
