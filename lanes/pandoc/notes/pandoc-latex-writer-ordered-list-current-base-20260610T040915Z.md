# Pandoc LaTeX Writer Ordered List Default Metadata

## Scope

- Preserved the later current-base LaTeX list-depth implementation while
  integrating the remaining ordered-list metadata behavior from `plib-iaik`.
- Explicit `decimal` style plus `period` delimiter now stays on the default
  enumerate label path instead of emitting a redundant
  `\renewcommand{\labelenumi}{\arabic{enumi}.}`.
- Start-only ordered-list metadata continues to emit the matching
  `\setcounter{...}{start - 1}` command, and default ordered lists remain
  unchanged.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 8 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58642 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2922 to 2923.
- `phpFail` remains 0.
- `suiteProgress` moves from 825 to 826 focused mapped checks.
- The mapped denominator moves from 3104 to 3105 with one focused LaTeX writer
  ordered-list default metadata pass case.
