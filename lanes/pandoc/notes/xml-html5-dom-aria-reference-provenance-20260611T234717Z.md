# XML/HTML5 DOM ARIA reference provenance - 2026-06-11

Bead: plib-28c3y

Scope: XML/HTML5 DOM core blocker only. HTML fragment summaries now preserve ARIA ID-reference provenance for reviewer handoff, including raw ID token order, resolved target element names/text, missing IDs, duplicate ID targets, deterministic raw HTML serialization, and WordPress raw block propagation.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed 1 test file, 909 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 67316 assertions, 0 failures.

External tools intentionally not invoked: Pandoc, browser renderers, online sanitizers, external validators, online services, live provider tests, or live-service provider tests.
