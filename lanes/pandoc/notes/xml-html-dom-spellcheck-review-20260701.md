# HTML spellcheck review

XmlHtmlDom now carries metadata-only `spellcheck` token review fields on summarized HTML elements.

The handoff preserves raw spellcheck values, normalized true/false keywords, invalid-token issue records, empty-value defaulting, and effective inherited source provenance without invoking browser editing behavior, spellcheck engines, validators, or live services.

Validation for plib-5y4fl passed on 2026-07-01:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomSpellcheckReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSpellcheckReviewTest.php` with 1 file, 64 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSpellcheckReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php` with 2 files, 6,420 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php` with 83 files, 12,729 assertions, 0 failures
