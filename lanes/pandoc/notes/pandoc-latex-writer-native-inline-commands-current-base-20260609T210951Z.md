# Pandoc LaTeX Writer Native Inline Command Slice

## Scope

- Added bounded `NativeReader` support for Pandoc native inline constructors that
  the LaTeX writer already knows how to emit as source commands:
  `Underline`, `Strikeout`, `Superscript`, `Subscript`, `SmallCaps`, `Quoted`,
  `RawInline` for TeX-like formats, and `Math`.
- Native JSON handoffs that previously produced `native_inline` placeholders now
  normalize to shared AST nodes, so `LatexWriter` preserves those commands
  instead of dropping them.
- Kept the slice under `lanes/pandoc` and did not add any external renderer,
  converter, validator, Pandoc, TeX/PDF, office, archive, Node, or browser
  dependency.

## Verification

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - 1 test file, 93 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58570 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, TeX/PDF
engine, browser renderer, office suite, external validator, online service,
live provider test, or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2917 to 2918 with one focused native-inline LaTeX writer
  command pass case.
- `phpFail` remains 0.
- The mapped denominator moves from 820 to 821 focused checks.
