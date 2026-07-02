# XML/HTML DOM Popover State Review

Slice: `plib-qyk43` on `integration/pandoc-semantics-xml`.

## What Changed

- `XmlHtmlDom` now promotes `popover` attributes into a compact
  `html-popover-state-review` packet on the element summary.
- The packet records empty/`auto` and `manual` states, invalid token diagnostics,
  issue-code/count rollups, element provenance, and an explicit metadata-only
  `popoverReviewOnlyNoPopoverEngine` flag.
- Existing `popoverRaw`, `popoverState`, and `popoverValid` fields are preserved,
  and target-summary shapes are unchanged.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomPopoverStateReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomPopoverStateReviewTest.php`
  - 1 file, 39 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomPopoverStateReviewTest.php lanes/pandoc/tests/XmlHtmlDomPopoverTargetDuplicateReviewTest.php lanes/pandoc/tests/XmlHtmlDomButtonCommandForReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - 4 files, 6352 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  - 80 files, 9662 assertions, 0 failures

No browser, network, Pandoc, Node, office, TeX, unzip/zip, or external validator
is required for this metadata-only review slice.
