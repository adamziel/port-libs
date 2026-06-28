# pandoc-xml-html-select-selection-review-20260628

Slice: `plib-8rd94` XML/HTML5 DOM core blocker.

## Scope

- Added bounded select/option static selection review metadata in `XmlHtmlDom`.
- The review exposes explicit selected option counts, effective static selected values/indexes, required placeholder-label option state, disabled-only required selection diagnostics, and multiple selected attributes on a non-multiple select.
- The handoff remains metadata-only and keeps raw HTML/WordPress output intact; it does not invoke browser form validation, script execution, network fetches, or external validators.

## Validation

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomSelectSelectionReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSelectSelectionReviewTest.php`
  - 1 file, 54 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomSelectSelectionReviewTest.php`
  - 2 files, 6,278 assertions, 0 failures.

## Accounting

- `lane-status.json` `phpPass`: `467 -> 468`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2308 -> 2309`.
- Added `xmlHtmlDomSelectSelectionReviewCases`, `mappedXmlHtmlDomSelectSelectionReviewCases`, and `xmlHtmlDomSelectSelectionReviewAssertions`.
