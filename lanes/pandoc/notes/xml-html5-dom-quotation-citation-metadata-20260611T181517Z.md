# XML/HTML5 DOM Quotation Citation Metadata

Bead: `plib-flg66`
Base: `d84ad700e`
Date: 2026-06-11 UTC

This slice extends native XML/HTML5 DOM reviewer summaries for inert
`blockquote`, `q`, and `cite` elements. It records block vs inline quotation
kind, raw and trimmed `cite` attribute provenance, normalized quote text,
blockquote footer attribution text, descendant citation texts/counts, standalone
`cite` text, dataset provenance, and unchanged deterministic serialization.

No Pandoc binary, browser renderer, online sanitizer, external validator,
online service, live provider test, or live-service provider test was invoked.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 1 test file, 671 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64966 assertions, 0 failures

Focused coverage added one `XmlHtmlDomTest` case with 26 assertions.
