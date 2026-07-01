# CSV/TSV Control Character Policy Diagnostics - 2026-07-01

Slice: `plib-b0jz3`, CSV/TSV direct reader diagnostics follow-up.

Current `origin/main` already carries the repaired `DelimitedTextReader`
implementation from the stale failed branch: CSV/TSV imports now preserve C0
control bytes in field text while reporting bounded review metadata in the
`delimitedText` packet.

The review packet includes:

- `controlCharacters.policy = report-c0-del-controls-except-ht-lf-cr`;
- total, NUL, quoted-field, and unquoted-field control counts;
- bounded byte/text samples with stable byte offsets, source row/column
  positions, codepoints, names, quote buckets, and source path context;
- row-repair annotations connecting sampled controls to relaxed padded or
  unchanged rows;
- a stable `delimited-text-control-characters` warning diagnostic for CSV and
  TSV inputs without altering BOM, input-prefix, quote/escape, trailing-field,
  or row-width behavior.

Focused coverage in `DelimitedTextReaderTest.php` exercises NUL and other C0
controls inside quoted and unquoted CSV fields, literal TSV fields, bounded
samples, source path propagation, diagnostics, row/column positions, and
row-repair summaries.

Validation:

- `php -l lanes/pandoc/src/DelimitedTextReader.php`
- `php -l lanes/pandoc/tests/DelimitedTextReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DelimitedTextReaderTest.php`
  passed with 1 file, 446 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed with 1 file, 360 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` was attempted and remains
  baseline-red outside this slice with 534 files, 142,294 assertions, and
  8,912 failures. The first visible failures are in DocBook reader, HTML writer
  global-attribute review, LaTeX writer, and Markdown surge tests, not the
  focused CSV/TSV reader coverage.
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`

No Pandoc binary, spreadsheet application, browser renderer, Node tooling,
online validator, online service, live provider, or external validator was
invoked.
