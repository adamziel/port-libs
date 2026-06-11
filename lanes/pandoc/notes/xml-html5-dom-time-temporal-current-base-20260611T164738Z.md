# XML/HTML5 DOM time temporal metadata current-base slice

- Bead: `plib-f14wi`
- Base: current `origin/main` at `f99ec6e05`
- Scope: HTML5 DOM summaries now preserve `<time>` temporal metadata for reviewer handoff, including raw `datetime` attributes, visible labels, normalized date/datetime/week/month/year/time/duration values, invalid value classification, missing machine-value state, and deterministic serialization.
- Native boundary: implemented in PHP DOM helpers without invoking Pandoc, browser renderers, online sanitizers, external validators, online services, or live provider tests.
- Focused verification: `php -l lanes/pandoc/src/XmlHtmlDom.php`; `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`; `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed 1 test file, 721 assertions, 0 failures.
- Full verification: `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 65377 assertions, 0 failures.
