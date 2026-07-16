# Pandoc LaTeX Writer Figure/Table Command Slice

## Scope

- Added bounded native `LatexWriter` output for `figure` and `table` AST blocks
  that were previously dropped by the LaTeX writer path.
- Figures now preserve safe `latex-placement` metadata, image source/alt text,
  and captions as visible LaTeX source commands.
- Tables now preserve captions, column alignment, table head/body/foot rows, and
  bounded colspan output through `longtable` and `\multicolumn` source.
- Kept the slice under `lanes/pandoc` and did not add any external renderer,
  converter, validator, Pandoc, TeX/PDF, office, archive, Node, or browser
  dependency.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 3 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 56989 assertions, 0 failures

## Accounting

- `phpPass` moves from 2825 to 2826 with one focused LaTeX writer figure/table
  command pass case.
- `phpFail` remains 0.
- The mapped denominator moves from 728 to 729 focused checks.
