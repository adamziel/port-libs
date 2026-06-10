# Pandoc XML/HTML5 DOM Media Resource Review

## Scope

This slice adds bounded native PHP XML/HTML5 DOM reviewer metadata for HTML
media resources:

- `audio` and `video` summaries now expose controls/autoplay/loop/muted state,
  normalized preload policy, video poster paths, direct `src` resources, child
  `source` resources, text-track resources, and fallback text.
- Deterministic HTML serialization now preserves libxml parser-nested children
  under HTML5 void elements such as `source` and `track`, so no source, track,
  or fallback reviewer content is silently dropped.

This stays inside `lanes/pandoc` XML/HTML5 DOM support and does not fetch media
bytes, validate media payloads, invoke browser renderers, or shell out to
Pandoc.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - `2 test files, 2931 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 61375 assertions, 0 failures`

## Accounting

- Rebase refresh keeps `lane-status.json` `phpPass` at `3012`
- `lane-status.json` `phpFail`: `0`
- Rebase refresh keeps `UPSTREAM_TEST_MANIFEST.json` mapped denominator at
  `3164`
- `mappedXmlHtmlDomCoreCases`: `11 -> 12`
- New focused counter: `mappedXmlHtmlDomMediaResourceReviewCases = 1`
- New focused assertion counter: `xmlHtmlDomMediaResourceReviewAssertions = 20`
- Rebase refresh keeps the counters stable while extending the existing focused
  case to include direct `audio`/`video` `src` resources in source summaries.

No Pandoc, Cabal/Haskell runner, browser renderer, online sanitizer, external
validator, online service, live provider test, or live-service provider test was
executed.
