# Pandoc roff/manual format direction registry slice 2026-06-09T185206Z

## Summary

This slice extends the existing native PHP Pandoc format registry accounting for
the roff/manual family with explicit direction buckets:

- `man`: input-output, present in both upstream reader and writer registries.
- `mdoc`: input-only, present only in the upstream reader registry.
- `ms`: output-only, present only in the upstream writer registry.

Direct-format parity remains conservative. Present roff/manual directions report
`unsupported`, absent directions report `not-applicable`, and no native PHP
`man`, `mdoc`, or `ms` reader/writer implementation is registered.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed: 1 file, 157 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 42 files, 56866 assertions, 0 failures after rebasing on current
  `origin/main`.

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, browser renderer, office suite, external validator, online service,
Node tooling, or zip/unzip command was used for this slice.
