# Pandoc Plain Writer Wrapping Diagnostics Current Base

## Scope

- Extended native `PlainWriter::writeWithDiagnostics()` to expose protected
  Unicode separator counts and normalized line-ending conversion counts.
- The new counters are reported both at the aggregate diagnostic level and per
  rendered block, so reviewer queues can distinguish ordinary wrap
  opportunities from no-break separator content and CRLF/CR source cleanup.
- Added focused coverage for a code block containing CRLF input and a no-break
  space that must stay inside the same wrapped phrase.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - 1 test file, 30 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 56751 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, TeX/PDF engine, browser renderer, zip/unzip, Jupyter, Node tooling,
external validator, online service, live provider test, or live-service
provider test was executed.

## Accounting

- `phpPass` moves from 2811 to 2812 after rebasing onto `origin/main`.
- `phpFail` remains 0.
- `suiteProgress` moves from 714 to 715 with one focused plain-writer wrapping
  diagnostics pass case.
