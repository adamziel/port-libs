# Pandoc Roff Manual Format Registry Review Packet

Mapped one bounded direct-format registry accounting case for Pandoc
roff/manual reader and writer tokens.

## Source Truth

- Upstream format inventory remains pinned to Pandoc manual date `2026-06-03`
  and upstream source commit `912bfa5e2e3f5c74eb125dfc19404f67c61ca58b`.
- The roff/manual input family is `man` and `mdoc`.
- The roff/manual output family is `man` and `ms`.
- Extension inference is registry metadata only: `.ms` and `.roff` infer `ms`,
  and numbered manual suffixes `.[1-9]` infer `man`.

## Native PHP Status

`PandocFormatRegistry` now exposes a compact roff/manual review packet with
input/output token groups, direction buckets, unsupported reader and writer
buckets, extension inference, per-format implementation fields, and direct
parity booleans.

Direct roff/manual conversion remains explicitly unsupported. No native PHP
`man`, `mdoc`, or `ms` reader/writer implementation is registered.

No Pandoc executable, roff renderer, Cabal/Haskell runner, TeX/PDF engine,
browser renderer, office suite, external validator, online service, Node
tooling, or zip/unzip command was executed.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 336 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 295 test files, 117101 assertions, 9783 failures
  - Not green because the broader lane has pre-existing non-slice failures
    visible in `UnicodeTextTest.php`, `YamlMetadataReviewTest.php`, and table
    writer expectations.

## Metric Delta

- `lane-status.json` `phpPass`: `459 -> 460`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2305 -> 2306`
- `mappedPandocFormatRegistryRoffManualReviewPacketCases`: `1`
- `pandocFormatRegistryRoffManualReviewPacketAssertions`: `69`
