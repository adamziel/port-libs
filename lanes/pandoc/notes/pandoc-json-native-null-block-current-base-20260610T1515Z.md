# Pandoc JSON/native Null block constructor slice

Date: 2026-06-10 UTC
Bead: plib-feov

## Scope

- Added native PHP support for Pandoc `Null` block constructors in both `NativeReader`/`NativeWriter`
  and `PandocJsonReader`/`PandocJsonWriter`.
- Imported `Null` blocks map to a shared non-rendering `null_block` AST node while preserving imported
  native constructor metadata for exact native JSON round trips.
- Generated `null_block` AST nodes emit back to Pandoc JSON/native `Null`.
- Markdown, LaTeX, and WordPress output omit the block, matching Pandoc `Null` rendering semantics.

## Verification

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php` passed 1 file / 259 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed 1 file / 387 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 60446 assertions / 0 failures.

## Accounting

- `lane-status.json` `phpPass`: 2982 -> 2983.
- `lane-status.json` focused checks: 883 -> 884.
- `phpFail` remains 0.

No Pandoc binary, JSON filter, Cabal/Haskell runner, browser renderer, external validator, online service,
live provider test, Node tooling, zip/unzip, office suite, or TeX/PDF engine was invoked.
