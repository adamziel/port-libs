# XML/HTML5 DOM Hidden Attribute Review - 20260628

Slice: `plib-gal8b`

## Scope

This slice adds native PHP review metadata for HTML `hidden` states, including:

- boolean and `hidden` keyword states;
- `hidden="until-found"` find-in-page reveal candidates;
- invalid hidden-token diagnostics.

`XmlHtmlDom` now reports a bounded `html-hidden-state-review` packet with reveal mode, issue codes, element provenance, and an explicit no-browser `beforematch` dispatch flag. The raw HTML and WordPress handoff remain metadata-only and do not invoke browser layout, find-in-page services, event dispatch, Pandoc, or external validators.

## Status Movement

- `phpPass`: `467 -> 468`
- Added 1 focused XML/HTML DOM behavior test with 48 assertions.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomHiddenAttributeReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomHiddenAttributeReviewTest.php`
  - Result: 1 file, 48 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomHiddenAttributeReviewTest.php lanes/pandoc/tests/XmlHtmlDomTranslateAttributeReviewTest.php lanes/pandoc/tests/XmlHtmlDomClassTokenReviewTest.php lanes/pandoc/tests/XmlHtmlDomLanguageAttributeReviewTest.php`
  - Result: 5 files, 6,400 assertions, 0 failures.
