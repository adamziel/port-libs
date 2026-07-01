# CSV/TSV Row Repair Provenance - 2026-07-01

Slice: `plib-dicc3`

## Scope

This follow-up extends the native PHP `DelimitedTextReader` review packet for
CSV and TSV row repairs without changing the rendered table AST. The actual
relaxed behavior remains `pad-to-wide-row`; shorter rows are padded to the
widest parsed row and over-wide rows remain visible in the table output.

New provenance in `rowRepairSummary` includes:

- explicit relaxed repair records and counts;
- strict first/header-row repair projections, including rows that would be
  padded or truncated under that width;
- original versus repaired column counts for both policies;
- generated-column and dropped-source-column index lists;
- skipped blank-row records;
- trailing-empty-field row records.

Focused coverage now asserts the CSV and TSV summary shape for relaxed padding,
strict mismatch/projection metadata, blank rows, trailing empty fields, original
and repaired column counts, quote/escape/multiline preservation, and input-prefix
handling.

## Verification

- `php -l lanes/pandoc/src/DelimitedTextReader.php`
- `php -l lanes/pandoc/tests/DelimitedTextReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/*.php`

Focused result: 2 files, 852 assertions, 0 failures.

Full lane result: 534 files, 142340 assertions, 8912 failures. The failures are
outside this DelimitedTextReader slice and include existing broad
Markdown/native-extension, HTML/native handoff, and table geometry expectation
baselines.

No external Pandoc binary, spreadsheet application, browser renderer, Node
tooling, online service, live provider, or external validator was used.
