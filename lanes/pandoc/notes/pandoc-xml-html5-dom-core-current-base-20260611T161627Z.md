# Pandoc XML/HTML5 DOM Core Current Base - Semantic Time/Data Summaries

Slice: `pandoc-xml-html5-dom-core-current-base-20260611T161627Z`

Base accepted HEAD: `289d97173`

## Implementation

- `XmlHtmlDom::summarizeHtmlFragment()` now exposes parser-side semantic summaries for HTML `time` and `data` elements.
- `time` summaries preserve raw `datetime`, normalized datetime value, datetime kind, and validity.
- Supported `time` kinds are `global-datetime`, `local-datetime`, `date`, `month`, `week`, `year`, `time`, and `duration`.
- `data` summaries preserve raw `value`, bounded normalized value, and validity.
- Deterministic HTML serialization is unchanged; this only adds reviewer metadata to the native summary structure.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 511 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63881 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `3068 -> 3069`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3193 -> 3194`.
- Added `mappedXmlHtmlDomSemanticValueCases: 1`.
- Added `xmlHtmlDomSemanticValueAssertions: 25`.

## Dependency Closure

No new support component is required. This slice reuses native PHP DOM/libxml parsing, the existing deterministic HTML serializer, and the focused Pandoc PHP test harness.

No Pandoc, Cabal/Haskell runner, browser renderer, online sanitizer, external validator, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat sanitizer-level `Html5DomFragment` time/value handoff or existing `XmlHtmlDom` revision, progress/meter, disclosure, form, media, foreign-content, RCDATA/raw-text, entity, table-foster-parenting, or DTD/entity safety slices.

It owns only parser-side semantic `time`/`data` value summaries in the core `XmlHtmlDom` helper.
