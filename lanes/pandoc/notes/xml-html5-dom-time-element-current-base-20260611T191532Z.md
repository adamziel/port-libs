# XML/HTML5 DOM Time Element Current-Base Slice

Bead: plib-a1k90
Date: 2026-06-11 UTC
Base: origin/main caf9a25cb0aa59f2d1bac93bd20943bde28670be

Implemented a bounded native PHP XML/HTML5 DOM blocker slice in `XmlHtmlDom`:

- HTML `time` element summaries now expose temporal metadata for reviewer handoff.
- `datetime` attributes are distinguished from text-source values and preserved raw.
- Valid global datetime, month, week, and standalone time values are normalized and classified.
- Invalid temporal values stay visible as invalid metadata without blocking deterministic serialization.
- No Pandoc, browser renderer, online sanitizer, external validator, online service, or live provider test was invoked.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 test file, 710 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 65477 assertions, 0 failures

Accounting:

- `phpPass` 3103 -> 3104
- `mappedXmlHtmlDomTimeElementCases`: 1
- `xmlHtmlDomTimeElementAssertions`: 22
