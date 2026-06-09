# Pandoc roff/manual section suffix registry slice 2026-06-09T220210Z

## Summary

Mapped one bounded direct-format registry accounting case for Pandoc roff/manual
manual section suffixes.

- `PandocFormatRegistry::inferRoffManualFormatFromExtension()` now treats
  dotted numeric man sections with alphabetic suffixes, such as `.3p`, `.5ssl`,
  `.7tcl`, and uppercase input variants, as `man`.
- Malformed manual suffixes such as `.3-p`, `.3.1`, `.3_foo`, and `.10ssl`
  remain uninferred.
- Review packets expose both numbered section patterns for `man` while preserving
  `mdoc` as non-extension-inferred and keeping `man`, `mdoc`, and `ms` direct
  native reader/writer parity explicitly unsupported.

This is registry metadata only. It does not parse or render roff/manual sources.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 414 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57533 assertions, 0 failures

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, browser renderer, external validator, online service, live provider
test, or live-service provider test was used.

## Metric Delta

- `lane-status.json` `phpPass`: `2856 -> 2857`
- `lane-status.json` focused checks: `759 -> 760`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3062 -> 3063`
- `mappedPandocFormatRegistryRoffManualSectionSuffixCases`: `1`
- `pandocFormatRegistryRoffManualSectionSuffixAssertions`: `17`
