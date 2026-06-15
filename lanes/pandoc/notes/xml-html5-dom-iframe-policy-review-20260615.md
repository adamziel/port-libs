# XML/HTML5 DOM iframe policy review

Slice: `pandoc-xml-html5-iframe-policy-review-a3157344c6`

Scope:

- `XmlHtmlDom` now adds iframe policy review metadata for `sandbox`, `allow`, `referrerpolicy`, `loading`, and `allowfullscreen`.
- Sandbox review reports raw value, token counts, valid tokens, invalid tokens, duplicate tokens, script/same-origin risk, and issue codes.
- Permissions policy review reports parsed directives, feature names, allow lists, invalid directives, aggregate feature names, and policy validity.
- Referrer and loading review normalize valid values and report invalid values without invoking Pandoc, browser renderers, external validators, online services, or live provider tests.
- Empty `sandbox` remains valid and represents a fully sandboxed iframe policy.

Accounting:

- `phpPass`: 3729 -> 3730
- `phpFail`: 0
- Upstream mapped cases: 3747 -> 3748
- `mappedXmlHtmlDomIframePolicyReviewCases`: 1
- `xmlHtmlDomIframePolicyReviewAssertions`: 41
- `mappedXmlHtmlDomCoreCases`: 14 -> 15
- `xmlHtmlDomCoreAssertions`: 266 -> 307

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` - 1 test file, 4278 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 46 test files, 88508 assertions, 0 failures
- PHP JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- Conflict-marker scan
