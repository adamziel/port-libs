# Pandoc Wiki Format Review Packet Registry Slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki review
packets.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry::wikiFormatReviewPacket()` derives accepted wiki
  input/output tokens, direction buckets, file-extension inference, unsupported
  input/output lists, and per-format implementation fields from the existing
  registry maps.
- Review packet directions remain:
  - input-output: `dokuwiki`, `jira`, `mediawiki`
  - input-only: `creole`, `tikiwiki`, `twiki`, `vimwiki`
  - output-only: `xwiki`, `zimwiki`
- Extension inference remains `.dokuwiki => dokuwiki` and `.wiki => mediawiki`.

## Native PHP Status

The review packet is accounting metadata only. Direct wiki reader/writer parity
remains explicitly unsupported, and no native PHP wiki reader or writer
implementation class is registered.

No Pandoc executable, wiki renderer, Cabal/Haskell runner, TeX/PDF engine,
browser renderer, online service, live provider test, external validator, or
external converter was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 274 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57230 assertions, 0 failures

## Metric Delta

- Rebased on `c10c21cb5`.
- `lane-status.json` `phpPass`: `2839 -> 2840`
- `lane-status.json` focused checks: `742 -> 743`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3054 -> 3055`
- `mappedPandocFormatRegistryWikiReviewPacketCases`: `1`
- `pandocFormatRegistryWikiReviewPacketAssertions`: `38`
