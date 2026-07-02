# Pandoc roff/manual format ship gate slice

Mapped one bounded direct-format registry gate case for Pandoc roff/manual
formats.

## Source Truth

- Upstream inventory remains pinned to the 2026-06-03 Pandoc manual and source
  commit `912bfa5e2e3f5c74eb125dfc19404f67c61ca58b`.
- `PandocFormatRegistry::roffManualFormatShipGate()` derives direct reader and
  writer blockers from `roffManualFormatReviewPacket()`.
- Accepted roff/manual input tokens remain `man` and `mdoc`; accepted output
  tokens remain `man` and `ms`.
- Direct reader and writer parity stays blocked for every accepted token because
  no native PHP roff/manual reader or writer is registered.

## Native PHP Status

The ship gate is accounting metadata only. It reports blockers, activation
requirements, and diagnostics without invoking Pandoc, roff, groff, mandoc,
manual renderers, Cabal/Haskell, browser tooling, office suites, external
validators, online services, or converter shell-outs.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 382 assertions, 0 failures

## Metric Delta

- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2307 -> 2308`
- `mappedPandocFormatRegistryRoffManualShipGateCases`: `1`
- `pandocFormatRegistryRoffManualShipGateAssertions`: `23`
- `lane-status.json` `phpPass`: `461 -> 462`
