# XML/HTML DOM Image Map Issue-Code Rollups

2026-07-02 plib-70m03 adds a bounded XML/HTML DOM slice for image-map reviewer handoff.

- `XmlHtmlDom::summarizeHtmlFragment()` now exposes `useMapIssueCodes`, `imageMapIssueCodes`, and `areaGeometryIssueCodes` next to the existing metadata-only diagnostic records.
- The rollups cover invalid, missing, and duplicate `usemap`/`map` references plus invalid area geometry and default-area precedence diagnostics.
- `XmlHtmlDomImageMapIssueCodeRollupTest.php` maps the behavior through raw HTML serialization and WordPress raw-block handoff without browser image-map execution or external validators.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomImageMapIssueCodeRollupTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomImageMapIssueCodeRollupTest.php` (1 file, 38 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomImageMapIssueCodeRollupTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomSrcsetResourceReviewTest.php lanes/pandoc/tests/XmlHtmlDomImageLoadingRawProvenanceTest.php` (4 files, 6,470 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php` (83 files, 12,703 assertions, 0 failures)
