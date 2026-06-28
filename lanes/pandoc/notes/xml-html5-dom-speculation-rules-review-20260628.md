# XML/HTML DOM speculation rules review

Slice: `plib-7zx1v`

`XmlHtmlDom` now summarizes inert `<script type="speculationrules">` JSON as
metadata-only target provenance. The review records prefetch/prerender rule-set
counts, per-rule source mode, list URLs with kind/scheme/unsafe flags,
document-rule `where` metadata, `requires` tokens, eagerness, referrer policy,
and issue codes for malformed rule sets, non-object rules, unsafe URLs,
non-string URLs, and invalid policy tokens.

This stays bounded to DOM review metadata before WordPress raw HTML handoff. It
does not fetch prefetch/prerender targets, execute browser speculation rules,
invoke a browser engine, or claim full HTML reader parity.

Validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomSpeculationRulesReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSpeculationRulesReviewTest.php`
  -> 1 test file, 54 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSpeculationRulesReviewTest.php lanes/pandoc/tests/XmlHtmlDomScriptBlockingReviewTest.php lanes/pandoc/tests/XmlHtmlDomImportMapReviewTest.php`
  -> 3 test files, 143 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  -> 46 test files, 10,946 assertions, 0 failures.
