# Pandoc roff/manual format registry slice 2026-06-09T174354Z

## Summary

This slice makes the roff/manual family explicit in the native PHP Pandoc
format registry:

- Input registry family: `man`, `mdoc`.
- Output registry family: `man`, `ms`.
- Extension inference evidence: `.ms` and `.roff` map to `ms`; numbered manual
  suffixes `.[1-9]` map to `man`.

Direct-format parity accounting remains conservative. The roff/manual entries
are explicitly marked `unsupported` with no PHP implementation class until a
native `man`, `mdoc`, or `ms` reader/writer exists.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed: 1 file, 87 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 40 files, 56606 assertions, 0 failures.

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, browser renderer, office suite, external validator, online service,
Node tooling, or zip/unzip command was used for this slice.
