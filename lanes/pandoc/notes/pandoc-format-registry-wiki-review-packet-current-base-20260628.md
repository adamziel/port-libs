# Pandoc wiki format review packet current-base slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki review
packets.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry::wikiFormatReviewPacket()` derives accepted wiki
  input/output tokens, direction buckets, file-extension inference,
  unsupported/partial reader and writer buckets, and per-format implementation
  fields from the existing registry maps.
- Extension inference remains `.dokuwiki => dokuwiki` and `.wiki =>
  mediawiki`.

## Native PHP Status

The review packet is accounting metadata only. Direct wiki reader/writer parity
remains explicitly unsupported except for the existing bounded partial Jira
reader status; no native PHP wiki writer is registered.

No Pandoc executable, wiki renderer, Cabal/Haskell runner, TeX/PDF engine,
browser renderer, online service, live provider test, external validator, or
external converter was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 267 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `457 -> 458`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2304 -> 2305`
- `mappedPandocFormatRegistryWikiReviewPacketCases`: `1`
- `pandocFormatRegistryWikiReviewPacketAssertions`: `29`
