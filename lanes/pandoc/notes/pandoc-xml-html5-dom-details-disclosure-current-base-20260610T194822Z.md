# Pandoc XML/HTML5 DOM Details Disclosure Current-Base Slice

Bead: `plib-9w91h`
Rebase base: `48391f93e30f1e2f0cc356813ab1a78020735541`

## Scope

This slice extends the native XML/HTML5 DOM summary handoff for bounded HTML `details` elements. The summary now records:

- `disclosure: details`
- boolean `open` state
- whether a first-child `summary` is explicit
- the explicit summary label, or the HTML default `Details` label when no summary element is present

The change stays inside `lanes/pandoc` and does not broaden Markdown, microdata, media, package, or browser-rendering behavior.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - 5 test files
  - 3285 assertions
  - 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files
  - 61609 assertions
  - 0 failures

No Pandoc, Cabal/Haskell runner, browser renderer, online sanitizer, external validator, online service, zip/unzip, office suite, TeX/PDF engine, Node, Jupyter, live provider test, or live-service provider test was executed.
