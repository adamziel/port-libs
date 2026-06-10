# Plain Writer Space Diagnostics Superseded

`plib-6wq1` proposed aggregate and per-block plain-writer diagnostics for
ASCII-space and Unicode-space break opportunities.

During refinery rebase on 2026-06-10, current `main` already contained the
accepted implementation from the plain-writer Unicode-space and soft-wrap
diagnostics slices:

- `PlainWriter::writeWithDiagnostics()` reports `spaceBreakOpportunityCount`
  and `unicodeSpaceBreakOpportunityCount` at aggregate and per-block levels.
- The accepted implementation also reports `maxUnicodeSpaceDisplayAdvance`,
  which is stricter than the original `plib-6wq1` slice.
- `PlainWriterTest` already covers the mixed ASCII and Unicode space wrapping
  path plus the existing tab, protected-separator, hard-break, and soft-wrap
  diagnostics.

The worker commit was therefore dropped as a duplicate during integration, and
this note records the supersession instead of replacing the stronger current
implementation.

Refinery verification on current `main`:

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 127 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 42 test files, 58256 assertions, 0 failures.
