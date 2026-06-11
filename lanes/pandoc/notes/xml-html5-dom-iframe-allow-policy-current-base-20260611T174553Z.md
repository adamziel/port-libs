# XML/HTML5 DOM Iframe Allow Policy

- Bead: `plib-l26oa`
- Base: `origin/main` `af666c9e0623a55f27a9f93e973b6c7e195dfff3`
- Slice: bounded XML/HTML5 DOM iframe permissions-policy provenance

## Scope

`XmlHtmlDom` already preserved iframe `allow` as raw text. This slice keeps that raw value and adds native PHP summaries for review packets: directive count, feature names, per-directive raw text, raw tokens, keyword tokens such as `self`, `src`, and `none`, origin-like tokens, and wildcard state.

The change stays inside iframe embedded-resource metadata and preserves the existing sandbox token and fallback text behavior. It does not invoke Pandoc, browser renderers, online sanitizers, external validators, online services, or live provider tests.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed `1 test file, 605 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` passed `44 test files, 64451 assertions, 0 failures`
