# XML/HTML5 DOM datalist association issue rollup

Slice: `plib-p1wx1` XML/HTML5 DOM core blocker.

`XmlHtmlDom::summarizeHtmlFragment()` now emits compact issue rollups for
`input-list-datalist-idref-review` summaries. Inputs with `list` attributes
carry datalist issue counts, issue-code counts, affected reference IDs, invalid
raw reference strings, and duplicate-target counts alongside the existing
detailed issue records.

The rollup is metadata-only. This slice does not execute browser datalist
behavior, mutate form state, run validators, fetch resources, invoke external
Pandoc, or use office-suite, TeX/browser-engine, Typst, Jupyter, Node,
ZIP/unzip, validator, or live-service tooling.

Post-rebase validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomDatalistAssociationIssueRollupTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomDatalistAssociationIssueRollupTest.php`
  - Result: 1 file, 29 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomDatalistAssociationIssueRollupTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFormSuccessfulControlReviewTest.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php lanes/pandoc/tests/XmlHtmlDomSelectOptionStateReviewTest.php lanes/pandoc/tests/XmlHtmlDomFileInputReviewTest.php lanes/pandoc/tests/XmlHtmlDomLabelControlAssociationReviewTest.php lanes/pandoc/tests/XmlHtmlDomCheckableInputStateReviewTest.php`
  - Result: 9 files, 6,719 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 83 files, 12,694 assertions, 0 failures.

Manifest accounting:

- `mappedXmlHtmlDomDatalistAssociationIssueRollupCases`: `0 -> 1`.
- `xmlHtmlDomDatalistAssociationIssueRollupAssertions`: `0 -> 29`.
- `benchmarkDenominator.mapped`: `2883 -> 2884`.
