# XML/HTML5 DOM Popover Target Duplicate Review

`XmlHtmlDom` now preserves duplicate `popovertarget` idref provenance in bounded
review metadata. Popover controls report target counts, all matching target
summaries, a `duplicate-target` target kind, and
`duplicate-popover-target-element` diagnostics before WordPress raw HTML handoff.

The handoff stays inert: it does not invoke browser popover activation, dispatch
events, fetch resources, or run external validators. Duplicate ids make
`popoverTargetInvokesPopover` false even when the first matched element has a
valid `popover` state.

Validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomPopoverTargetDuplicateReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomPopoverTargetDuplicateReviewTest.php`
  passed with 1 test file, 26 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomPopoverTargetDuplicateReviewTest.php`
  passed with 2 test files, 6,250 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  passed with 69 test files, 12,028 assertions, 0 failures.
