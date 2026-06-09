# Pandoc roff/manual registry review packet slice 2026-06-09T213240Z

## Summary

Mapped one bounded direct-format registry accounting case for Pandoc
roff/manual review packets.

- Extension inference now normalizes `.ms` and `.roff` to `ms`.
- Numbered manual suffixes `.1` through `.9` now normalize to `man`.
- Review packets expose `man` as input-output, `mdoc` as input-only, and `ms`
  as output-only.
- Direct native parity remains explicitly unsupported for `man`, `mdoc`, and
  `ms`; no PHP reader or writer implementation class is registered.

This is metadata-only accounting for WordPress/Pandoc lane review handoff. It
does not parse or render roff/manual sources.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 397 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57474 assertions, 0 failures

No Pandoc executable, roff renderer, Cabal solver/build/test command, Haskell
runner, browser renderer, external validator, online service, live provider
test, or live-service provider test was used.

## Metric Delta

- `lane-status.json` `phpPass`: `2850 -> 2851`
- `lane-status.json` focused checks: `753 -> 754`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3057 -> 3058`
- `mappedPandocFormatRegistryRoffManualReviewPacketCases`: `1`
- `pandocFormatRegistryRoffManualReviewPacketAssertions`: `31`
