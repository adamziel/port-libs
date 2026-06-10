# Pandoc LaTeX Writer Inline Linebreak Command Slice

## Scope

- Added bounded native `LatexWriter` output for inline Pandoc `linebreak`
  nodes so hard line breaks emit LaTeX `\\` source commands instead of being
  flattened to soft newlines.
- Preserved existing `softbreak` behavior as a plain newline.
- Kept the slice under `lanes/pandoc` and did not add any external renderer,
  converter, validator, Pandoc, Cabal/Haskell, TeX/PDF, office, archive, Node,
  or browser dependency.

## Verification

- `php -l lanes/pandoc/src/LatexWriter.php`
- `php -l lanes/pandoc/tests/LatexWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/LatexWriterTest.php`
  - 1 test file, 9 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 test files, 58849 assertions, 0 failures

## Accounting

- `phpPass` moves from 2933 to 2934 with one focused LaTeX writer hard-line
  break command pass case.
- `phpFail` remains 0.
- The mapped denominator moves from 3111 to 3112.
- `mappedLatexWriterCommandCases` and `latexWriterCommandAssertions` each move
  from 1 to 2.
