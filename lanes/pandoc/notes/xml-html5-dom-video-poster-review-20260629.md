# XML/HTML5 DOM Video Poster Review

`XmlHtmlDom` now summarizes bounded `<video poster>` resource provenance before
raw HTML and WordPress handoff. The review packet preserves the raw poster
attribute while classifying URL kind, scheme, unsafe schemes, empty values, and
non-HTTP absolute targets without fetching poster images or invoking browser
media loaders.

This is scoped to DOM review metadata only. It does not claim media decoding,
poster image inspection, layout, preload behavior, browser networking, or
full HTML5 media element parity.

Accounting:

- `phpPass`: `470` -> `471`
- `mappedXmlHtmlDomVideoPosterReviewCases`: `+1`
- `xmlHtmlDomVideoPosterReviewAssertions`: `+41`

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomVideoPosterReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomVideoPosterReviewTest.php` -> 1 file, 41 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomVideoPosterReviewTest.php lanes/pandoc/tests/XmlHtmlDomMediaPreloadReviewTest.php lanes/pandoc/tests/XmlHtmlDomMediaControlsPolicyTest.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 4 files, 6,348 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php` -> 62 files, 11,670 assertions, 0 failures.
