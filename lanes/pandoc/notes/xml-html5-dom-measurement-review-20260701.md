# XML/HTML5 DOM Measurement Review

Slice: `plib-isebv`

## Scope

`XmlHtmlDom` now carries explicit metadata-only review provenance for HTML
`<progress>` and `<meter>` measurement summaries. The existing numeric summary
behavior is preserved, including progress indeterminate state, meter defaults,
and bounded/clamped values, while the summary now also exposes:

- raw source attributes for value/range/threshold fields
- progress and meter review policy names
- validity booleans for values, ranges, maximums, and meter thresholds
- clamping state for author values outside the effective range
- ordered issue-code lists and issue records for invalid numbers, nonpositive
  progress maxima, reversed meter ranges, out-of-range values, and bad meter
  thresholds

Labelable-control summaries reuse the same measurement packet, so `<label>`
handoff sees the same range/validity decisions as direct element summaries.

## Accounting

- `benchmarkDenominator.mapped`: `2872 -> 2873`
- `mappedXmlHtmlDomMeasurementReviewCases`: `1`
- `xmlHtmlDomMeasurementReviewAssertions`: `80`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomMeasurementReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMeasurementReviewTest.php`

The focused test passed with `1 test files, 80 assertions, 0 failures`.

No browser engine, external XML/HTML validator, upstream Pandoc runner, or other
converter was invoked.
