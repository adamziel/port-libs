# XML/HTML5 DOM iframe credentialless/CSP review

Slice: `plib-ul74w` XML/HTML5 DOM core blocker.

`XmlHtmlDom` now reports iframe `credentialless` and `csp` attribute provenance
as metadata-only review records before raw HTML and WordPress handoff. The slice
records canonical boolean use, noncanonical credentialless values, CSP directive
names, fetch directive names, source schemes, report endpoints, invalid directive
names, duplicate directives, and empty-policy diagnostics. It reuses the existing
native PHP CSP scanner and does not fetch frames or enforce browser policy.

`credentialless` is also registered as an HTML boolean attribute in the legacy
DOM serializer and the `Html5DomFragment` serializer so canonical empty/name
values round trip deterministically.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomIframeCredentiallessCspReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomIframeCredentiallessCspReviewTest.php` -> 1 file, 48 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php` -> 34 files, 7,444 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php` -> 3 files, 3,068 assertions, 0 failures
