# Pandoc Wiki Source Provenance Registry Slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki
reader/writer source provenance.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `src/Text/Pandoc/Readers.hs` registers the accepted wiki reader tokens:
  `creole`, `dokuwiki`, `jira`, `mediawiki`, `tikiwiki`, `twiki`, and
  `vimwiki`.
- `src/Text/Pandoc/Writers.hs` registers the accepted wiki writer tokens:
  `dokuwiki`, `jira`, `mediawiki`, `xwiki`, and `zimwiki`.
- `PandocFormatRegistry` now exposes the pinned upstream module, function, and
  registry-entry provenance for those wiki tokens.

## Native PHP Status

This is source-provenance accounting only. Direct wiki reader/writer parity
remains explicitly `unsupported`; no native PHP wiki reader or writer is
registered.

No Pandoc executable, wiki renderer, Cabal/Haskell runner, browser renderer,
office suite, online service, live provider test, external validator, or
external converter was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 918 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 test files, 58941 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2935 -> 2936`
- `lane-status.json` focused checks: `838 -> 839`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3113 -> 3114`
- `mappedPandocFormatRegistryWikiSourceProvenanceCases`: `1`
- `pandocFormatRegistryWikiSourceProvenanceAssertions`: `84`
