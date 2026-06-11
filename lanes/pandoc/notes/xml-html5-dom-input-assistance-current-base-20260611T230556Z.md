# XML/HTML5 DOM Input Assistance Slice

Bead: `plib-gh7b7`

Current base: `4d330e2a13`

Change:
- `XmlHtmlDom::summarizeHtmlFragment()` now preserves input-assistance global attribute provenance for `inputmode`, `enterkeyhint`, and `autocapitalize`.
- Review summaries expose raw values, normalized values, and validity flags while deterministic raw HTML and WordPress raw block handoff remain intact.
- Added a focused fixture for valid mixed-case values, `autocapitalize="none"` normalization, textarea handoff, and invalid fallback values.

Verification:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` (1 file, 870 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 67070 assertions, 0 failures)

No Pandoc, browser renderer, online sanitizer, external validator, online service, or live provider test was invoked.
