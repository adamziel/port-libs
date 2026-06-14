# Pandoc Direct CSL Original Title Subtitle Aliases

Slice: `pandoc-csl-direct-original-title-subtitle-aliases-20260614T1303Z`
Rebased over current main `0f124c82f1`.

Implemented one bounded Direct CSL JSON citation/bibliography handoff case:
direct item input now composes `originalTitle` plus `originalSubtitle` and
compact `origtitle` plus `origsubtitle` into canonical `original-title`
metadata while retaining `original-subtitle` as raw-provenance, text-rendering,
and sort-key review metadata.

This aligns direct CSL JSON ingestion with the existing BibLaTeX
`origtitle`/`origsubtitle` handoff without invoking Pandoc, citeproc, BibTeX,
Biber, bibliography managers, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 5642 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `46 test files, 83388 assertions, 0 failures`

Accounting:

- `phpPass`: `3519 -> 3520`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3438 -> 3439`
- `mappedDirectCslOriginalTitleSubtitleAliasCases`: `1`
- `directCslOriginalTitleSubtitleAliasAssertions`: `20`

Non-goals:

This slice does not repeat accepted BibLaTeX original-subtitle handling,
direct camel original-publication aliases, translated-title subtitle aliases,
or general title-family aliases. It is limited to direct CSL original-title
subtitle composition and render/sort visibility.
