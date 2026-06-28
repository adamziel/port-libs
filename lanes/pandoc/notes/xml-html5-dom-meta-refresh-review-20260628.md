# XML/HTML5 DOM Meta Refresh Review

`XmlHtmlDom::metaRefreshSummary()` now carries metadata-only review fields for
`<meta http-equiv="refresh">` navigation handoff.

- Preserves the parsed delay and URL fields while adding delay validity, URL
  kind/scheme/safety, redirect intent, and issue codes.
- Flags invalid or missing delay values, malformed `url=` assignments, empty
  refresh URLs, unsafe schemes such as `javascript:`, and non-HTTP absolute
  redirect schemes.
- Records `redirectFollowed=false`; the lane does not fetch, navigate, execute
  browser logic, or validate the target externally.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomMetaRefreshReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMetaRefreshReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomMetaRefreshReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
