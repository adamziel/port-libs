# XML/HTML DOM Form Action Provenance

Session: `plib-qyk43`

This slice extends the native PHP `XmlHtmlDom` reviewer summary for base HTML
`<form>` submission action provenance. Form summaries now expose inert review
metadata for:

- source `action` kind, scheme, unsafe URL status, and document-URL defaulting;
- raw/effective `method`, `enctype`, and `target` values;
- network-request classification for `method=dialog` versus normal form submit;
- unsafe action, non-HTTP action, invalid method, and invalid enctype diagnostics.

The raw HTML attributes remain preserved for HTML and WordPress raw handoff. No
form is submitted, no target is fetched, and no browser/form engine is invoked.

Red-first:
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormActionProvenanceTest.php`
  failed because `formActionReviewPolicy` was absent.

Validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFormActionProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormActionProvenanceTest.php`
  -> `1 test files, 63 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php`
  -> `34 test files, 7,449 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  -> `296 test files, 116,852 assertions, 9,781 failures`; broad lane remains
  baseline-red with visible failures outside this slice in
  `YamlMetadataReviewTest.php`.

Accounting:
- `phpPass`: `457 -> 458`
- `benchmarkDenominator.mapped`: `2304 -> 2305`
- `mappedXmlHtmlDomFormActionProvenanceCases`: `1`
