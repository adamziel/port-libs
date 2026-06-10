# XML/HTML5 DOM Void Boundary Current-Base Slice

## Scope

Bead: `plib-q1f2`

Mapped a bounded XML/HTML5 DOM primitive for HTML5 `track` and `wbr` parser boundaries. The slice stays inside `lanes/pandoc` and does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Implementation

- `XmlHtmlDom::protectHtmlRcdataElements()` now closes selected HTML5 void start tags in non-raw text segments before libxml parsing.
- The boundary normalization is limited to `track` and `wbr` to preserve existing `source`, `embed`, and `param` reader contracts.
- Comment text and script/raw-text contents are skipped so tag-like reviewer text remains literal.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed: 1 file, 320 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed after rebase onto `651ccb330`: 44 files, 60285 assertions, 0 failures.
