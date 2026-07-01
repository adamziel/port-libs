# XML/HTML5 DOM Popover Control Issues

Bead: `plib-oeoon`
Date: 2026-07-01 UTC
Area: Pandoc XML/HTML5 DOM primitives

## Behavior

`XmlHtmlDom::summarizeHtmlFragment()` now adds a bounded
`html-popover-control-issue-review` packet for elements that define popovers or
declare popover invoker attributes.

The packet rolls up:

- which popover-related attributes were present;
- whether the element defines a valid popover;
- whether a `popovertarget` invoker can actually invoke a valid target;
- issue codes for invalid popover states, invalid target references, invalid
  target states, invalid target actions, and target actions with no target.

This is metadata-only DOM review support. It does not change serialized HTML
and does not invoke Pandoc, browser engines, office suites, online sanitizers,
external validators, online services, live provider tests, or live-service
provider tests.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomPopoverControlIssueCodesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomPopoverControlIssueCodesTest.php`
  - `1 test files, 51 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/XmlHtml5DomTest.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomButtonCommandDialogPolicyTest.php lanes/pandoc/tests/XmlHtmlDomPopoverControlIssueCodesTest.php`
  - `7 test files, 9573 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  - `58 test files, 8728 assertions, 0 failures`
- `git diff --check -- lanes/pandoc/src/XmlHtmlDom.php lanes/pandoc/tests/XmlHtmlDomPopoverControlIssueCodesTest.php`

## Non-Overlap

This does not repeat accepted popover state parsing, popover target lookup,
button `commandfor` dialog/popover targeting, anchor positioning, iframe policy,
or fragment serialization work. It only adds consolidated issue-code metadata
for popover control review.
