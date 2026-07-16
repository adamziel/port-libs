# XML/HTML DOM Form Submitter Action Provenance

Session: `plib-89569`

This slice extends the native PHP `XmlHtmlDom` reviewer summary for HTML form submitters. Submit-capable `<button>` controls and `input type=submit|image` controls now expose source-preserving action provenance beside the existing compact `submitter` metadata:

- effective action source from `formaction`, owner form `action`, or document-URL default
- effective `method`, `enctype`, `target`, and `novalidate` sources
- unsafe and non-HTTP action diagnostics without submitting or fetching targets
- missing owner and disabled submitter diagnostics
- `formmethod=dialog` no-network classification

Raw attributes remain preserved for HTML and WordPress raw handoff.

Validation:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php` -> `1 test files, 70 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> `1 test files, 6224 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*.php` -> `30 test files, 7276 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` on rebased branch -> `289 test files, 115455 assertions, 9848 failures`
- `php tools/run-tests.php lanes/pandoc/tests` on `origin/main` baseline -> `288 test files, 115385 assertions, 9848 failures`
- Branch-only full-lane failures: `0`

Accounting:
- `phpPass`: `443 -> 444`
- `mappedXmlHtmlDomFormSubmitterActionProvenanceCases`: `1`
