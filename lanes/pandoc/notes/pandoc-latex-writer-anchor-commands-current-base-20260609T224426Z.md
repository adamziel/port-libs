# Pandoc LaTeX Writer Anchor Command Slice

## Scope

- Added bounded native `LatexWriter` output for AST identifiers that were
  previously dropped by the LaTeX writer path.
- Block identifiers now emit sanitized `\hypertarget` wrappers, anchored
  headings also emit `\label`, inline spans emit protected `\hypertarget`
  commands, and internal `#id` links emit `\hyperlink` commands.
- External `\href` output remains unchanged.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 7 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58153 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2891 to 2892 after rebase onto current main.
- `phpFail` remains 0.
- The mapped denominator moves from 3088 to 3089 with one focused LaTeX writer
  anchor-command pass case.
