# Pandoc Wiki Direct Parity Gap Summary

Mapped one bounded native PHP format-registry accounting case for Pandoc
wiki-family direct parity gaps.

## Source Truth

- `PandocFormatRegistry::wikiDirectParityGapSummary()` derives its buckets from
  the existing wiki input/output token lists and `wikiFormatRegistry()`.
- The summary records 7 input parity gaps and 5 output parity gaps.
- The 3 bidirectional wiki parity gaps are `dokuwiki`, `jira`, and
  `mediawiki`.
- `jira` remains explicitly partial input support through `JiraReader`; the
  other wiki input gap tokens remain unsupported direct readers.
- All wiki output formats remain unsupported direct writers.

## Scope Boundary

This is registry accounting metadata only. It does not add a wiki converter,
does not register native PHP direct wiki reader/writer parity, and does not
invoke Pandoc, Cabal/Haskell runners, wiki renderers, browser renderers, Node
tooling, external validators, online services, or live provider tests.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 file, 248 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/JiraReaderTest.php`
  - 2 files, 327 assertions, 0 failures

## Metric Delta

- `lane-status.json` `phpPass`: `457 -> 458`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2304 -> 2305`
- `mappedPandocFormatRegistryWikiDirectParityGapCases`: `1`
