# XML/HTML5 DOM base metadata review

Slice: `plib-ug7cd` XML/HTML5 DOM core blocker.

`XmlHtmlDom::summarizeHtmlFragment()` now emits additive metadata-only review
fields for HTML `base` elements. The review preserves the existing `href` and
`target` fields while adding:

- base href kind, scheme, unsafe flag, resolved reviewer URL, usability, and
  issue codes for empty, invalid, fragment-only, scheme-relative, unusable, and
  unsafe values;
- base target raw/safe/effective names, reserved keyword detection, custom
  target detection, unsafe target diagnostics, and `_blank` fallback provenance
  for newline/tab/form-feed/`<` target hazards;
- aggregate `baseIssueCodes` and `baseValid` fields that allow low-level DOM
  reviewers to distinguish valid href-only, target-only, and combined base
  metadata from unsafe or empty records.

The change is metadata-only. It does not fetch base URLs, invoke browser URL
resolution, shell out to Pandoc or external validators, relax the sanitized
fragment policy, or alter serialized HTML. Direct-format parity remains active
in blocker notes.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomBaseMetadataReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomBaseMetadataReviewTest.php`
  - Result: 1 file, 72 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomBaseMetadataReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php lanes/pandoc/tests/XmlHtmlDomLinkFetchPolicyReviewTest.php lanes/pandoc/tests/XmlHtmlDomScriptIntegrityReviewTest.php lanes/pandoc/tests/XmlHtmlDomScriptLoadingPolicyReviewTest.php`
  - Result: 6 files, 9349 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 60 files, 11592 assertions, 0 failures.
