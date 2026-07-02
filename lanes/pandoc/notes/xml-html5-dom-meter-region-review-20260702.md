# XML/HTML5 DOM Meter Region Review

Slice: `plib-ul9gd`

## Scope

`XmlHtmlDom` now adds metadata-only region review fields to HTML `<meter>`
measurement summaries. When the meter range and explicit `low`/`high`
thresholds are usable, the summary classifies the effective meter value and
`optimum` value into `low`, `middle`, or `high` threshold regions and records
whether both values land in the same region. It also exposes `meterPosition`,
the normalized effective value within the meter range, for reviewer handoff.

Invalid ranges, invalid values, missing thresholds, out-of-range thresholds, and
invalid optimum values keep their existing issue-code behavior and leave the new
region/alignment fields as `null`. Raw HTML serialization and WordPress handoff
remain unchanged.

## Accounting

- `benchmarkDenominator.mapped`: `2883 -> 2884`
- `mappedXmlHtmlDomMeterRegionReviewCases`: `1`
- `xmlHtmlDomMeterRegionReviewAssertions`: `20`
- `xmlHtmlDomMeasurementReviewAssertions`: `80 -> 100`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomMeasurementReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMeasurementReviewTest.php`

The focused test passed with `1 test files, 100 assertions, 0 failures`.

No browser engine, external XML/HTML validator, upstream Pandoc runner, or other
converter was invoked.
