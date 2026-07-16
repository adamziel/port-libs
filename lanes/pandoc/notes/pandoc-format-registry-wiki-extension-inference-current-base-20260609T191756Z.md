# Pandoc Wiki Extension Inference Registry Slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki file
extension inference.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `Text.Pandoc.Format.formatFromFilePath` maps `.dokuwiki` to `dokuwiki`
  and `.wiki` to `mediawiki`.
- No other accepted Pandoc wiki token is file-extension inferred in the pinned
  upstream source.

## Native PHP Status

The registry exposes the extension inference map and derived inferred/non-inferred
wiki buckets for review accounting only. Direct wiki reader/writer parity remains
explicitly unsupported; no native PHP wiki reader or writer is registered.

No Pandoc executable, Cabal/Haskell runner, browser renderer, office suite,
online service, live provider test, external validator, or external converter
was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 236 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 56969 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2823 -> 2824`
- `lane-status.json` focused checks: `726 -> 727`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3043 -> 3044`
- `mappedPandocFormatRegistryWikiExtensionInferenceCases`: `1`
- `pandocFormatRegistryWikiExtensionInferenceAssertions`: `23`
