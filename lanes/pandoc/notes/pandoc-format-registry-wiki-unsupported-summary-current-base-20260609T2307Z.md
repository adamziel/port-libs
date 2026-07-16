# Pandoc Wiki Unsupported Format Summary Slice

Mapped one bounded direct-format registry accounting case for unsupported
Pandoc wiki reader/writer surfaces.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry` keeps the accepted upstream wiki input tokens:
  `creole`, `dokuwiki`, `jira`, `mediawiki`, `tikiwiki`, `twiki`, and
  `vimwiki`.
- `PandocFormatRegistry` keeps the accepted upstream wiki output tokens:
  `dokuwiki`, `jira`, `mediawiki`, `xwiki`, and `zimwiki`.
- The new unsupported-format summary derives unsupported-both, unsupported
  input-only, unsupported output-only, no-native-reader, and no-native-writer
  buckets from the accepted wiki direction registry.

## Native PHP Status

The summary keeps every direct wiki reader/writer parity surface explicit as
`unsupported` when the format exists for that direction, and `not-applicable`
when the upstream token is absent for that direction. This does not register a
native PHP wiki reader or writer.

No Pandoc executable, wiki renderer, Cabal/Haskell runner, browser renderer,
office suite, online service, live provider test, external validator, or
external converter was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 591 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57864 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2871 -> 2872`
- `lane-status.json` focused checks: `774 -> 775`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3076 -> 3077`
- `mappedPandocFormatRegistryWikiUnsupportedFormatCases`: `1`
- `pandocFormatRegistryWikiUnsupportedFormatAssertions`: `57`
