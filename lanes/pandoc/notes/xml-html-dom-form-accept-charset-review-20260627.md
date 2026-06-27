# XML/HTML DOM Form Accept Charset Review

Session: `plib-6m14x`

This slice extends the native PHP `XmlHtmlDom` reviewer summary for HTML form
submission boundaries. Form summaries now classify `accept-charset` metadata
without invoking a browser, validator, network request, or external conversion
tool:

- default missing state as UTF-8;
- single valid UTF-8 declaration;
- legacy multi-token declarations such as `UTF-8 ISO-8859-1`;
- duplicate UTF-8 declarations;
- invalid token syntax that must remain reviewer metadata only.

Raw attributes and deterministic HTML/WordPress raw handoff remain unchanged.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php` -> `1 test files, 40 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php` -> `2 test files, 6264 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php` -> `33 test files, 7386 assertions, 0 failures`

Accounting:

- `phpPass`: `454` -> `455`
- Focused XML/HTML DOM family assertion delta includes the new 40 assertion
  form accept-charset review case.
