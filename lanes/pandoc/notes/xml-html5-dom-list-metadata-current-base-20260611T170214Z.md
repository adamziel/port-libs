# XML/HTML5 DOM List Metadata Current-Base Slice

Bead: plib-tw6z7
Date: 2026-06-11 UTC
Base: origin/main bd4af01a0943a4e98fc2c18314718964258fd092

Implemented a bounded native PHP XML/HTML5 DOM package-ingestion blocker slice in `XmlHtmlDom`:

- HTML fragment summaries now expose `ol`, `ul`, `menu`, and `li` list metadata.
- Ordered lists preserve reversed state, raw and normalized `start` values, and marker type.
- List items preserve raw and normalized `value` ordinal provenance.
- No Pandoc, browser renderer, online sanitizer, external validator, online service, or live provider test was invoked.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 test file, 538 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 64178 assertions, 0 failures

Accounting:

- `phpPass` 3076 -> 3077
- mapped denominator 3198 -> 3199
- `mappedXmlHtmlDomListMetadataCases`: 1
- `xmlHtmlDomListMetadataAssertions`: 29
