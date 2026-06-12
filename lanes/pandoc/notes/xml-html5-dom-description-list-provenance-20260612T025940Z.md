# XML/HTML5 DOM Description List Provenance Slice

Bead: plib-0wlmx
Date: 2026-06-12 UTC
Base: origin/main 643ed855c0

Implemented a bounded native PHP XML/HTML5 DOM core-blocker slice in `XmlHtmlDom`:

- HTML fragment summaries now expose `dl` description-list provenance.
- `dl` summaries carry grouped `dt`/`dd` text, term/description counts, and group counts.
- `dt` and `dd` summaries expose their description-list part and normalized text.
- Direct `div` wrappers inside `dl` are preserved as separate description-list groups.
- No Pandoc, browser renderer, online sanitizer, external validator, online service, or live provider test was invoked.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 test file, 1265 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 69757 assertions, 0 failures

Accounting:

- `phpPass` 3180 -> 3181
- `mappedXmlHtmlDomDescriptionListCases`: 1
- `xmlHtmlDomDescriptionListAssertions`: 24
