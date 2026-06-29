# XML/HTML5 DOM iframe CSP Review - 2026-06-29

Scope: bounded XML/HTML5 DOM review metadata for iframe `csp` attributes.

`XmlHtmlDom` now preserves iframe Content Security Policy provenance without
fetching frame sources or invoking browser enforcement. The iframe summary
records:

- raw `csp` attribute value, byte length, SHA-256, directive counts, directive
  names/kinds, fetch directive names, scheme/network/report endpoint sources,
  unsafe keywords, diagnostics, and validity;
- iframe-specific aliases such as `iframeCspDirectiveNames`,
  `iframeCspIssueCodes`, and `iframeCspValid`;
- explicit `iframeCspReviewOnlyNoFrameFetch=true` and
  `iframeCspBrowserEnforcement=false` provenance.

The implementation reuses the native PHP CSP directive summarizer already used
for meta Content-Security-Policy review, with source-specific review policy and
missing-content diagnostics for iframe attributes.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomIframeCspReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomIframeCspReviewTest.php`
  - 1 file, 45 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomIframeCspReviewTest.php lanes/pandoc/tests/XmlHtmlDomContentSecurityPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomIframeCredentiallessReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 4 files, 6,358 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5Dom*.php`
  - 62 files, 11,674 assertions, 0 failures

No Pandoc executable, browser renderer, iframe loader, network fetch, external
validator, online service, or live provider test was invoked.
