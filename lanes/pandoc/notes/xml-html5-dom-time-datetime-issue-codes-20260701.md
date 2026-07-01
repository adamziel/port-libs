# XML/HTML5 DOM time datetime issue codes

Slice: `plib-1z5qt` XML/HTML5 DOM core blocker.

`XmlHtmlDom::summarizeHtmlFragment()` now carries additive
`html-time-datetime-value-review` metadata for `<time>` elements. The existing
normalized `timeDatetime*` and `timeValue*` fields are preserved, while missing,
empty, invalid, unsafe, and invalid text-fallback values now expose stable issue
records, issue-code lists, counts, and conforming flags.

The focused fixture keeps the review metadata local to native PHP DOM summary
handoff. It preserves serialized HTML and WordPress raw HTML output, does not
fetch or validate external resources, and does not invoke Pandoc, browser
engines, TeX, Node, office suites, zip/unzip, validators, or live services.

Manifest counters:
- `mappedXmlHtmlDomTimeDatetimeIssueCodeCases`: `1`
- `xmlHtmlDomTimeDatetimeIssueCodeAssertions`: `47`

Focused validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTimeDatetimeIssueCodesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTimeDatetimeIssueCodesTest.php`
