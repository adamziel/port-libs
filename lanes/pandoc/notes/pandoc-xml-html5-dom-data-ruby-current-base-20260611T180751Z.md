# Pandoc XML/HTML5 DOM data/ruby current base 20260611T180751Z

Bead: `plib-p1wx1`
Base: `4172e4cba0597d67efc6d44978c0aba042c349c6`

## Scope

`XmlHtmlDom` now summarizes HTML5 `data` element machine-readable value metadata and ruby annotation/fallback metadata for reviewer handoff.

Covered metadata:

- `data` raw `value`, trimmed value, and visible text
- `ruby` base text excluding `rt` and `rp` children
- `rt` annotation text rollups
- `rp` fallback parenthesis text rollups

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 832 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66634 assertions, 0 failures`

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
