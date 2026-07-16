# Pandoc LaTeX Writer Unsupported Command Metadata Slice

## Scope

- Extended `LatexWriter` unsupported-command labels to include bounded summaries
  of structured command `arguments`, `args`, `options`, and `attributes`.
- Kept structured provenance escaped inside `\texttt{...}` review labels so raw
  TeX-like payloads remain visible but are not emitted as executable commands.
- Covered both block and inline unsupported command nodes under `lanes/pandoc`
  without adding external renderer, converter, validator, Pandoc, TeX/PDF,
  office, archive, Node, or browser dependencies.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 11 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 59378 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2949 to 2950 with one focused LaTeX writer unsupported
  command metadata pass case.
- `phpFail` remains 0.
- The mapped focused check count moves from 852 to 853.
