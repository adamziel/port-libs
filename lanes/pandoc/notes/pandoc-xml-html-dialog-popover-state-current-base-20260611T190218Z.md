# XML/HTML dialog popover state slice

Bead: `plib-s86cf`

Base: current `origin/main` `30462ed7c`

## Scope

- Added HTML dialog open-state reviewer metadata to `XmlHtmlDom::summarizeHtmlFragment()`.
- Preserves `popover`, `popovertarget`, and `popovertargetaction` state for dialog/popover handoff.
- Normalizes valid popover states and target actions while retaining raw invalid values for review.
- Keeps the path native PHP only; no Pandoc, browser engine, online sanitizer, external validator, online service, live provider, or live-service provider calls.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test file, 708 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65562 assertions, 0 failures`

Lane status: `phpPass` moves `3104 -> 3105`.
