# Pandoc Plain Writer Wrapped Source Line Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for wrapped
source-line records.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports
  `maxGeneratedWrapBreaksPerSourceLine` so reviewer handoffs can distinguish a
  single heavily wrapped source line from several lightly wrapped lines.
- The diagnostics also expose a bounded `wrappedSourceLines` sample list with
  block index, source line index, source display width, generated break count,
  output line count, max output width, forced-wrap count, and a truncated source
  text preview.
- Plain writer output is unchanged; the new records reuse the existing native
  `UnicodeText::wrapByDisplayWidth()` and display-width helpers.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 228 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Attempted after the post-rebase focused pass. Current branch/base failed
    outside this slice: 275 test files, 104,814 assertions, 11,097 failures.
    The failure set is broad and unrelated to `PlainWriterTest.php`, with top
    failure counts in Markdown surge, CSL/BibTeX, JSON/native, and
    `UnicodeTextTest.php` files.

## Accounting

- Added one focused PHP behavior case.
- `lane-status.json` `phpPass` moves from `429` to `430`.
- Focused assertion accounting moves from `4,613` to `4,625`.
