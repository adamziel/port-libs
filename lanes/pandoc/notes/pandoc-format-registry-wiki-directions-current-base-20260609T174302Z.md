# Pandoc Wiki Format Direction Registry Slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki
format direction buckets.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry` keeps the accepted upstream wiki input tokens:
  `creole`, `dokuwiki`, `jira`, `mediawiki`, `tikiwiki`, `twiki`, and
  `vimwiki`.
- `PandocFormatRegistry` keeps the accepted upstream wiki output tokens:
  `dokuwiki`, `jira`, `mediawiki`, `xwiki`, and `zimwiki`.
- The new direction registry derives bidirectional, input-only, and output-only
  buckets from those accepted token lists.

## Native PHP Status

The direction registry keeps every direct wiki reader/writer parity status
explicit as `unsupported` when the format is present for that direction, and
`not-applicable` when the upstream token is absent for that direction. This does
not claim native PHP reader or writer support for wiki formats.

No Pandoc executable, Cabal/Haskell runner, browser renderer, office suite,
online service, live provider test, external validator, or external converter
was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 137 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 41 test files, 56726 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2807 -> 2808`
- `lane-status.json` focused checks: `710 -> 711`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3034 -> 3035`
- `mappedPandocFormatRegistryWikiDirectionCases`: `1`
- `pandocFormatRegistryWikiDirectionAssertions`: `50`
