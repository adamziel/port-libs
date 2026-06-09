# Pandoc Wiki Format Registry Slice

Mapped one bounded direct-format registry accounting case for Pandoc wiki formats.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry` records the current manual snapshot date `2026-06-03` and the upstream manual URL used for accepted input/output format tokens.
- Wiki input tokens tracked: `creole`, `dokuwiki`, `jira`, `mediawiki`, `tikiwiki`, `twiki`, `vimwiki`.
- Wiki output tokens tracked: `dokuwiki`, `jira`, `mediawiki`, `xwiki`, `zimwiki`.

## Native PHP Status

The registry keeps all wiki reader/writer tokens explicit as `unsupported` for direct native PHP parity. Existing Markdown, JSON, DOCX, EPUB, ODT, RTF, HTML/WordPress, LaTeX, and Native support entries remain partial accounting entries only.

No Pandoc executable, Cabal/Haskell runner, browser renderer, office suite, online service, live provider test, or external validator was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 66 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 39 test files, 56418 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `2794 -> 2795`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3029 -> 3030`
- `mappedPandocFormatRegistryWikiCases`: `1`
- `pandocFormatRegistryWikiAssertions`: `66`
