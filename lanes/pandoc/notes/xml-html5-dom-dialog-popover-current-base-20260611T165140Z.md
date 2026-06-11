# XML/HTML5 DOM dialog/popover slice

- Bead: plib-1qwr5
- Base: current main 96af5e2be
- Scope: shared `XmlHtmlDom::summarizeHtmlFragment()` reviewer summaries for HTML dialog and popover state.
- Change: dialog nodes now expose open/closed state and labels; popover owners expose raw and normalized popover state; popover controls expose target IDs plus raw/normalized target actions.
- Boundary: native PHP DOM handling only; no Pandoc, browser renderers, online sanitizers, external validators, online services, or live provider tests.
- Verification: `php -l lanes/pandoc/src/XmlHtmlDom.php`; `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`; `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed 1 test file, 535 assertions, 0 failures; `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 64022 assertions, 0 failures.
