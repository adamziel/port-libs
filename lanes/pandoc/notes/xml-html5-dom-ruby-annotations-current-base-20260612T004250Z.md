# XML/HTML5 DOM ruby annotation provenance

Bead: `plib-h2lvc`

This slice extends `XmlHtmlDom` reviewer summaries for HTML ruby annotation
markup. The handoff now records `ruby` base text segments, ordered `rt`
annotation text, `rtc` annotation-container provenance, and `rp` fallback
parenthesis text as inert DOM metadata while preserving deterministic raw HTML.

Accounting:

- `phpPass`: `3154 -> 3155`
- Added one focused `XmlHtmlDomTest` PASS case.
- Added `mappedXmlHtmlDomRubyAnnotationCases = 1`.
- Added `xmlHtmlDomRubyAnnotationAssertions = 29`.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with `1 test files, 1006 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 67912 assertions, 0 failures`.

No Pandoc, Cabal/Haskell runners, browser renderers, office suites,
zip/unzip, Jupyter, Node tooling, external validators, online services, live
provider tests, or live-service provider tests were run.
