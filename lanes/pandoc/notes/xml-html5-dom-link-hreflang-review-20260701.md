# XML/HTML5 DOM link hreflang review

Implemented a bounded XML/HTML5 DOM metadata slice for HTML `<link hreflang>`.

- `XmlHtmlDom::summarizeHtmlFragment()` now attaches link-specific hreflang review metadata using the existing HTML language-tag analyzer.
- The packet records raw, canonical, subtag, script, region, private-use, validity, and issue-code fields.
- Invalid and empty `hreflang` values are surfaced through `linkIssues` with `invalid-link-hreflang` and `empty-link-hreflang`.
- This remains a native PHP review/provenance packet; it does not shell out to Pandoc, browsers, or external validators.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomLinkHrefLangReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomLinkHrefLangReviewTest.php lanes/pandoc/tests/XmlHtmlDomLinkFetchPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomLanguageAttributeReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5Dom*.php` passed: 84 files, 12951 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` was attempted and failed on the current base with 535 files, 142336 assertions, 8912 failures. Representative failures were in Markdown raw-attribute reader fixtures, native/table geometry writer expectations, and Unicode MarkdownReader::readBytes coverage; no failures were reported in the new link hreflang test or the broad XML/HTML DOM subset above.
