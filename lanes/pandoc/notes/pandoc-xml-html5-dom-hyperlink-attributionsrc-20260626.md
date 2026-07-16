# Pandoc XML/HTML5 DOM Hyperlink Attributionsrc Slice - 2026-06-26

Slice: `plib-m0p8l`, XML/HTML5 DOM core blocker.

Added bounded native PHP review metadata for hyperlink `attributionsrc` on
`a` and `area` elements. The summary now records:

- empty `attributionsrc` as a source-origin registration request;
- non-empty space-separated registration URL records;
- unsafe registration URLs, including JavaScript URLs;
- non-HTTP absolute registration URLs, while still allowing relative URLs as
  local source records;
- raw HTML and WordPress handoff preservation without fetching any endpoint.

This is metadata-only XML/HTML DOM provenance. It does not implement browser
Attribution Reporting behavior, perform network requests, invoke Pandoc,
launch a browser, or expand broader sanitizer/tree-builder parity.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomAttributionSrcReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomAttributionSrcReviewTest.php`
  - Result: 1 test file, 39 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomAttributionSrcReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  - Result: 6 test files, 9,390 assertions, 0 failures.

Status delta:

- `lane-status.json` `phpPass`: `438 -> 439` on the rebased main base
- `phpFail`: stays `0`

Full `lanes/pandoc/tests` remains blocked by the existing broad baseline
recorded in `lane-status.json`; this slice claims only the focused XML/HTML DOM
attributionsrc behavior above.
