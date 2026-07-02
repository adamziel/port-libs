# XML/HTML5 DOM select option state issue rollup

Slice: `plib-dltnu` XML/HTML5 DOM core blocker.

`XmlHtmlDom::summarizeHtmlFragment()` now emits compact issue rollups for
`html-select-option-state-review` summaries. Select controls expose issue
counts by code, affected option values, affected optgroup labels, and selection
sources for required-value failures while preserving the existing detailed
issue records.

The rollup is metadata-only. This slice does not mutate browser state, submit
forms, run browser validators, fetch resources, invoke external Pandoc, or use
office-suite, TeX/browser-engine, Typst, Jupyter, Node, ZIP/unzip, validator,
or live-service tooling.

Post-rebase validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomSelectOptionStateReviewTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSelectOptionStateReviewTest.php`
  - Result: 1 file, 73 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomSelectOptionStateReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFormSuccessfulControlReviewTest.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php lanes/pandoc/tests/XmlHtmlDomFileInputReviewTest.php lanes/pandoc/tests/XmlHtmlDomLabelControlAssociationReviewTest.php lanes/pandoc/tests/XmlHtmlDomCheckableInputStateReviewTest.php`
  - Result: 8 files, 6,710 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 82 files, 12,685 assertions, 0 failures.

Manifest accounting:

- `mappedXmlHtmlDomSelectOptionStateReviewCases`: unchanged at `1`.
- `xmlHtmlDomSelectOptionStateReviewAssertions`: `53 -> 73`.
- `benchmarkDenominator.mapped`: unchanged at `2883`.
