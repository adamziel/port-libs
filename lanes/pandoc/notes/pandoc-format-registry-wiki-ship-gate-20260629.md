# Pandoc wiki format ship gate slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki ship
readiness.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry::wikiFormatShipGate()` derives direct reader and writer
  blockers from `wikiFormatReviewPacket()`.
- The gate reports accepted wiki input/output counts, blocking direct reader and
  writer format lists, activation requirements, and accounting-only diagnostics.

## Native PHP Status

The ship gate is metadata only. It keeps direct wiki parity blocked because the
accepted wiki writer formats remain unsupported and the accepted wiki reader
formats are unsupported except for the existing bounded partial Jira reader.

No Pandoc executable, wiki renderer, Cabal/Haskell runner, TeX/PDF engine,
browser renderer, online service, live provider test, external validator, or
external converter was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 290 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `459 -> 460`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2305 -> 2306`
- `mappedPandocFormatRegistryWikiShipGateCases`: `1`
- `pandocFormatRegistryWikiShipGateAssertions`: `23`
