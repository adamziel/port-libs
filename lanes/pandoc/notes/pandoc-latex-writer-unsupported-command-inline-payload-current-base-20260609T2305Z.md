# Pandoc LaTeX Writer Unsupported Command Inline Payload Slice

## Scope

- Added bounded native `LatexWriter` fallback output for unsupported block
  commands whose child payload is inline AST content.
- Unsupported block-command quotes now keep inline child arguments visible as
  escaped LaTeX review text instead of dropping them when the node has no block
  children.
- Mixed unsupported block-command payloads keep inline runs and block children
  separated for reviewer readability.
- Kept the slice under `lanes/pandoc` and did not add any external renderer,
  converter, validator, Pandoc, TeX/PDF, office, archive, Node, or browser
  dependency.

## Verification

- Red-first after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - `renders unsupported block command inline child payloads as review text`
    failed because only the fallback label rendered and the inline children were
    dropped.
- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 6 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57913 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2875 to 2876 with one focused LaTeX writer unsupported
  command inline-payload pass case.
- `phpFail` remains 0.
- The mapped denominator moves from 3079 to 3080, with
  `mappedLatexWriterUnsupportedCommandCases` moving from 1 to 2.
