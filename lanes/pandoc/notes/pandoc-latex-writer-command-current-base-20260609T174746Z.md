# Pandoc LaTeX Writer Command Slice

## Scope

- Added bounded native `LatexWriter` output for Pandoc-style AST nodes that
  were previously dropped by the LaTeX writer path.
- Covered sectioning commands, quote environments, verbatim code blocks,
  horizontal rules, div passthrough, spaces, inline formatting commands,
  links, images, citations, raw LaTeX inlines, and footnotes.
- Kept the slice under `lanes/pandoc` and did not add any external renderer or
  converter dependency.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 1 assertion, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - 1 test file, 1433 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6151 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 56659 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2806 to 2807 after rebasing onto current `origin/main`.
- `phpFail` remains 0.
- The mapped denominator moves from 3034 to 3035 with one focused LaTeX writer
  command pass case.
