# XML/HTML5 DOM textarea layout review

Slice: `plib-91hru` XML/HTML5 DOM core blocker.

## Summary

`XmlHtmlDom::summarizeHtmlFragment()` now emits additive review-only metadata
for HTML `textarea` layout and submission wrapping. Textarea summaries include
normalized/defaulted `rows`, `cols`, and `wrap` provenance, effective defaults,
hard-wrap-without-valid-cols diagnostics, value byte length, value line count,
and aggregate layout validity.

The change is metadata-only. It does not alter HTML parsing, serialized HTML,
sanitizer behavior, form submission behavior, or direct reader parity claims.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTextareaLayoutReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTextareaLayoutReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomTextareaLayoutReviewTest.php lanes/pandoc/tests/XmlHtmlDomInputHintReviewTest.php lanes/pandoc/tests/XmlHtmlDomAutocapitalizeInheritanceTest.php lanes/pandoc/tests/XmlHtmlDomTypedInputValueReviewTest.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `git diff --check`

No Pandoc binary, browser engine, office suite, TeX engine, unzip/zip command,
Node tooling, network fetch, or external validator was invoked.
