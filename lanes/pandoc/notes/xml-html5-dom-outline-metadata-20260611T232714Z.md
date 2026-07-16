# XML/HTML5 DOM Outline Metadata Slice

Bead: `plib-ar2oo`
Date: 2026-06-11 UTC
Base: origin/main a4e427916b

Implemented a bounded native PHP XML/HTML5 DOM reviewer-summary slice in
`XmlHtmlDom`:

- HTML `h1` through `h6` summaries now expose heading role, tag, level, and text.
- HTML `article`, `aside`, `main`, `nav`, and `section` summaries now expose
  outline-root metadata plus the nearest scoped heading label.
- Nested outline roots are isolated so parent containers do not inherit nested
  child-root headings.
- Deterministic raw HTML and WordPress raw block handoff remain native PHP.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 test file, 882 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67281 assertions, 0 failures

Accounting:

- `phpPass` 3144 -> 3145
- mapped denominator 3220 -> 3221
- `mappedXmlHtmlDomOutlineMetadataCases`: 1
- `xmlHtmlDomOutlineMetadataAssertions`: 42

No Pandoc, browser renderer, online sanitizer, external validator, online
service, live provider test, or live-service provider test was invoked.
