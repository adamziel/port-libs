# XML/HTML5 DOM form rel review

Slice: `plib-bjn74` XML/HTML5 DOM core blocker.

This slice adds bounded native PHP review metadata for HTML `form rel`
tokens. `XmlHtmlDom` now records form relationship tokens, token counts,
known versus unknown form link types, duplicate/invalid token diagnostics,
navigation hints, and opener/noopener conflict provenance before raw HTML and
WordPress handoff.

Scope stays metadata-only: no form submission is performed, no target URL is
fetched, and no browser navigation or opener policy engine is invoked.

Metric delta:

- `lane-status.json` `phpPass`: `467 -> 468`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2308 -> 2309`
- Added `mappedXmlHtmlDomFormRelReviewCases = 1`
- Added `xmlHtmlDomFormRelReviewAssertions = 40`

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFormRelReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormRelReviewTest.php`
  passed with 40 assertions and 0 failures.
- Adjacent DOM/form gate passed:
  `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormRelReviewTest.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomInputImageSubmitterReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  with 5 files, 6,433 assertions, and 0 failures.
