# DOCX Chart Series And Point Metadata

Slice: `plib-q26em`

This slice extends DOCX OpenXML chart package ingestion beyond chart-part relationship provenance.
Valid `c:chartSpace` parts now expose metadata-only chart structure for chart types, series labels,
cache formulas, cached category/value points, series markers, `smooth`, `invertIfNegative`,
and `c:dPt` point overrides including marker and DrawingML fill/line styling.

The reader still blocks raw chart-part bytes from user-facing media and keeps the existing
`chart-part-bytes-blocked` / `chart-part-metadata-only` policy. Aggregate parity counters now
include `chartPartSeriesCount` and `chartPartDataPointCount` in package provenance summaries.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
