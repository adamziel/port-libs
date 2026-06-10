# Pandoc XML/HTML5 DOM Media Resource Review

## Scope

This slice adds bounded native PHP XML/HTML5 DOM reviewer metadata for HTML
media resources:

- `audio` and `video` summaries now expose controls/autoplay/loop/muted state,
  normalized preload policy, video poster paths, direct source resources,
  text-track resources, and fallback text.
- Deterministic HTML serialization now preserves libxml parser-nested children
  under HTML5 void elements such as `source` and `track`, so no source, track,
  or fallback reviewer content is silently dropped.

This stays inside `lanes/pandoc` XML/HTML5 DOM support and does not fetch media
bytes, validate media payloads, invoke browser renderers, or shell out to
Pandoc.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 356 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - `1 test files, 6574 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 61327 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `3009 -> 3010`
- `lane-status.json` `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3162 -> 3163`
- `mappedXmlHtmlDomCoreCases`: `11 -> 12`
- New focused counter: `mappedXmlHtmlDomMediaResourceReviewCases = 1`
- New focused assertion counter: `xmlHtmlDomMediaResourceReviewAssertions = 20`

No Pandoc, Cabal/Haskell runner, browser renderer, online sanitizer, external
validator, online service, live provider test, or live-service provider test was
executed.
