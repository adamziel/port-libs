# Pandoc roff/manual parity-summary registry slice

Micro-slice: `pandoc-roff-manual-parity-summary-current-base-20260610T102400Z`

## Summary

This slice adds compact parity-summary accounting for the native PHP Pandoc
roff/manual format registry.

- Counts `man`, `mdoc`, and `ms` across input-output, input-only, and
  output-only direction buckets.
- Separates literal `.ms`/`.roff` extension mappings from numeric manual-section
  extension mappings, including suffixed section forms such as `.[1-9][a-z]+`.
- Reports zero registered PHP reader and writer implementations, so direct
  native parity remains explicitly unsupported for the roff/manual family.
- Exposes the same summary through `roffManualFormatReviewPacket()` for review
  dashboards.

No native `man`, `mdoc`, or `ms` converter was registered.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed: 1 file, 976 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed after final rebase: 44 files, 59409 assertions, 0 failures.

`lane-status.json` `phpPass`: `2949 -> 2950`; `phpFail` remains `0`.

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, browser renderer, office suite, external validator, online service,
Node tooling, or zip/unzip command was used for this slice.
