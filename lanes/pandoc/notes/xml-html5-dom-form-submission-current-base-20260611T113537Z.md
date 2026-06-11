# XML/HTML5 DOM Form Submission Slice

Session: `plib-8xzxs`
Base: `ffd6e31e5578577d820a4d341abc9c46c84490d9`

This slice extends the native PHP `XmlHtmlDom` reviewer summary for HTML form submission boundaries. `<form>` summaries now expose normalized `action`, `method`, `enctype`, `target`, `autocomplete`, `novalidate`, raw `accept-charset`, and tokenized charset values while preserving raw attributes for serialization parity.

Submitter controls now expose form override metadata for `button type=submit` and `input type=submit|image`, including `formaction`, normalized `formmethod`, normalized `formenctype`, `formtarget`, `form`, and `formnovalidate`. Invalid form `method`, `enctype`, and `autocomplete` values fall back to HTML defaults in the summary while remaining intact in raw attributes.

Verification:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> `1 test files, 436 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 62808 assertions, 0 failures`

Accounting:
- `phpPass`: `3052 -> 3053`
- `suiteProgress`: `950 -> 951` focused handoff checks mapped
