# Pandoc Wiki Format Parity Summary Registry Slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki
parity-summary metadata.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry::wikiFormatParitySummary()` derives counts from the
  existing accepted wiki input/output token lists, direction buckets, extension
  inference map, and support maps.
- The summary records 9 wiki-family formats: 3 input-output formats, 4
  input-only formats, and 2 output-only formats.
- Extension inference remains bounded to 2 mappings: `.dokuwiki` and `.wiki`.

## Native PHP Status

The parity summary is accounting metadata only. Direct wiki reader/writer parity
remains explicitly unsupported: all 7 wiki input tokens and all 5 wiki output
tokens are unsupported, no native wiki implementation classes are registered,
and `directParityClaimed` remains false.

No Pandoc executable, wiki renderer, Cabal/Haskell runner, TeX/PDF engine,
browser renderer, online service, live provider test, external validator, or
external converter was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 735 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58399 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2905 -> 2906`
- `lane-status.json` focused checks: `808 -> 809`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3094 -> 3095`
- `mappedPandocFormatRegistryWikiParitySummaryCases`: `1`
- `pandocFormatRegistryWikiParitySummaryAssertions`: `9`
