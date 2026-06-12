# XML/HTML5 DOM iframe srcdoc review provenance

Slice: `plib-bagry`, XML/HTML5 DOM core blocker.

## Behavior

`XmlHtmlDom` now treats iframe `srcdoc` as inert review source and exposes
bounded provenance in iframe summaries:

- raw `srcdoc` byte length and SHA-256;
- parse success/failure state and unsafe declaration diagnostics;
- top-level element names and normalized text hash;
- static link hrefs, image sources, form actions, active element names, and
  embedded-resource element names.

The helper parses `srcdoc` through the same safe fragment loader used for
ordinary HTML fragments. It refuses oversized `srcdoc` review payloads and
reports unsafe/unparseable declarations instead of attempting repair that could
hide source hazards.

## Non-Overlap

This does not repeat accepted XML/HTML5 DOM work for form owners, form labels,
select options, dialog state, disclosure/output controls, media source/track
summaries, image-map provenance, inert custom elements, microdata attributes,
revision provenance, quote attribution, or raw iframe fallback preservation.
The new surface is limited to static `srcdoc` review metadata on iframe
summaries.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 1398 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 70912 assertions, 0 failures`

Status delta:

- `phpPass`: `3202 -> 3203`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3239 -> 3240`
- `mappedXmlHtmlDomIframeSrcdocReviewCases`: `1`
- `xmlHtmlDomIframeSrcdocReviewAssertions`: `25`

No Pandoc, browser renderer, online sanitizer, external validator, online
service, live provider test, or live-service provider test was executed.
