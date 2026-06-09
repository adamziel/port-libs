# Pandoc LaTeX Writer Unsupported Command Slice

## Scope

- Added bounded native `LatexWriter` output for unsupported raw block/inline
  formats and native fallback constructors that were previously dropped by the
  LaTeX writer path.
- Unsupported commands render as escaped `\texttt{...}` review text in quote
  blocks or inline positions, keeping raw/native source hints visible without
  executing or passing through unsupported commands.
- Kept the slice under `lanes/pandoc` and did not add any external renderer,
  converter, validator, Pandoc, TeX/PDF, office, archive, Node, or browser
  dependency.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 5 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57516 assertions, 0 failures

## Accounting

- `phpPass` moves from 2855 to 2856 with one focused LaTeX writer unsupported
  command pass case.
- `phpFail` remains 0.
- The mapped denominator moves from 3061 to 3062; mapped suite progress moves
  from 758 to 759 focused checks.
