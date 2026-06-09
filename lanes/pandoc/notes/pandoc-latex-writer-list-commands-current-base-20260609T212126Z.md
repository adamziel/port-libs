# Pandoc LaTeX Writer List Command Slice

## Scope

- Added bounded native `LatexWriter` output for Pandoc ordered-list metadata
  that was previously dropped by the LaTeX writer path.
- Ordered lists now preserve start numbers with `\setcounter`, list style with
  depth-aware enumerate label commands, and delimiter choices for period,
  one-paren, and two-parens list labels.
- Nested ordered lists use the matching LaTeX enumerate counter depth
  (`enumi`, `enumii`, `enumiii`, `enumiv`) while existing task-list item labels
  remain visible.
- Kept the slice under `lanes/pandoc` and did not add any external renderer,
  converter, validator, Pandoc, TeX/PDF, office, archive, Node, or browser
  dependency.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 4 assertions, 0 failures
- `git diff --check -- lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57422 assertions, 0 failures

## Accounting

- `phpPass` moves from 2847 to 2848 with one focused LaTeX writer ordered-list
  command pass case.
- `phpFail` remains 0.
- The mapped suite progress moves from 750 to 751 focused checks.
