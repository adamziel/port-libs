# XML/HTML5 DOM Script Fetch Policy Issue Review

## Scope

- `XmlHtmlDom` script loading summaries now emit explicit issue codes for invalid `crossorigin`, `fetchpriority`, and `referrerpolicy` values on `<script>` elements.
- The issue records preserve raw invalid attribute values while keeping the existing normalized validity booleans and `blocking` token diagnostics.
- Raw HTML serialization and WordPress raw block handoff remain unchanged.

## Direct-Format Parity

This slice is metadata-only for the XML/HTML5 DOM lane. It does not fetch script resources, execute JavaScript, run a browser, invoke Pandoc, call an external validator, or expand direct reader/writer parity beyond review packets.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomScriptFetchPolicyIssueReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomScriptFetchPolicyIssueReviewTest.php lanes/pandoc/tests/XmlHtmlDomScriptBlockingReviewTest.php lanes/pandoc/tests/XmlHtmlDomScriptIntegrityReviewTest.php lanes/pandoc/tests/XmlHtmlDomScriptAttributionSrcReviewTest.php` -> 4 files, 137 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5Dom*.php lanes/pandoc/tests/XmlHtml5DomTest.php` -> 83 files, 12717 assertions, 0 failures
