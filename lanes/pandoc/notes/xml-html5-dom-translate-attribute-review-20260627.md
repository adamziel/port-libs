# XML/HTML5 DOM Translate Attribute Review

Date: 2026-06-27
Task: `plib-fk8po`

## Scope

- Added bounded `translate` attribute review metadata to `XmlHtmlDom::summarizeHtmlFragment()`.
- Explicit `translate="no"`, `translate="yes"`, and empty `translate=""` states now carry normalized review fields alongside the existing effective translation provenance.
- Invalid translate tokens are reported as diagnostics while effective translation still resolves from the nearest valid ancestor before raw HTML and WordPress handoff.

## Source Truth

- WHATWG HTML defines the translate attribute as an enumerated global attribute whose empty and `yes` states enable translation and whose `no` state disables it: <https://html.spec.whatwg.org/multipage/dom.html#attr-translate>
- This PHP slice is metadata-only. It does not invoke browser localization services, run Pandoc, execute scripts, or use external validators.

## Counters

- `phpPass`: `465 -> 466`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `2307 -> 2308`
- `xmlHtmlDomTranslateAttributeReviewCases`: `1`
- `mappedXmlHtmlDomTranslateAttributeReviewCases`: `1`
- `xmlHtmlDomTranslateAttributeReviewAssertions`: `47`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTranslateAttributeReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTranslateAttributeReviewTest.php`
  - `1 test files, 47 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTranslateAttributeReviewTest.php lanes/pandoc/tests/XmlHtmlDomLanguageAttributeReviewTest.php lanes/pandoc/tests/XmlHtmlDomDraggableAutoTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `4 test files, 6343 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  - `41 test files, 7774 assertions, 0 failures`
