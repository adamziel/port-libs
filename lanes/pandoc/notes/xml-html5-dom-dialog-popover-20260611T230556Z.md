# XML/HTML5 DOM dialog popover slice 20260611T230556Z

Target: current main 67332814bf.

Implemented bounded HTML reviewer summaries for dialog, inert, and popover state. `XmlHtmlDom` now reports dialog open/text state, inert global attributes, normalized popover mode, popover target IDs, and normalized target actions while preserving raw invalid values for review.

Added one `XmlHtmlDomTest` case covering an open inert dialog, manual popover content, a valid popover trigger, invalid popover/action values, ARIA/global metadata, and deterministic HTML serialization. No Pandoc, browser renderer, online sanitizer, external validator, online service, live provider test, or live-service provider test was invoked.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` - 1 test file, 860 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 test files, 66924 assertions, 0 failures
