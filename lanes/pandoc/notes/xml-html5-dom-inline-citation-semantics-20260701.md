# XML/HTML5 DOM inline citation semantics

Slice: `plib-5y4fl`

`XmlHtmlDom::summarizeHtmlFragment()` now treats inline `cite` and `q`
elements as text-level semantic nodes in addition to their existing quote and
citation provenance:

- `cite` reports `textSemantic: cited-work` with `semanticTag` and
  `semanticText` alongside `citedWork`/`citationText`;
- `q` reports `textSemantic: inline-quotation` while preserving `cite`
  attribute URL review, normalized quote text, and unsafe-cite diagnostics;
- nested text semantics, such as emphasis inside quoted text, remain visible
  through the existing child summaries.

This is metadata-only for direct raw HTML and WordPress handoff. It does not
fetch citation URLs, invoke a browser, call external validators, or shell out
to Pandoc.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomInlineCitationSemanticReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomInlineCitationSemanticReviewTest.php`
  passed with 1 file, 27 assertions, and 0 failures.
- `php tools/run-tests.php $(rg --files lanes/pandoc/tests | rg '/XmlHtmlDom.*Test\.php$' | sort)`
  passed with 35 files, 7,468 assertions, and 0 failures.
