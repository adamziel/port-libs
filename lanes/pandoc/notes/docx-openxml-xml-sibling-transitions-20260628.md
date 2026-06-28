# DOCX/OpenXML XML sibling transitions

This slice keeps DOCX/OpenXML package ingestion native to PHP and adds
metadata-only XML sibling-transition provenance for XML-inspectable package
parts. `DocxOpenXmlReader` now reports ordered adjacent element pairs under
each parent element, parent paths/namespaces, previous/next qualified-name
buckets, same-name versus different-name counts, and interleaved non-element
node counts without exposing XML text, attribute values, package bytes, or
relationship target bytes.

Focused validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed after rebasing onto `origin/integration/pandoc-package-docx` with
  1 file, 11365 assertions, 0 failures.

Broader validation:

- `php tools/run-tests.php lanes/pandoc/tests` was attempted, but the lane
  suite currently fails outside this DOCX slice in
  `lanes/pandoc/tests/YamlMetadataReviewTest.php` (`indexes pandoc yaml
  tagged explicit flow key provenance by metadata path` and `indexes pandoc
  yaml alias provenance by metadata path`). Re-running that focused YAML test
  after the final rebase reproduced the same 2 failures.

No Pandoc binary, Office suite, TeX/browser/Typst engine, `zip`, `unzip`,
external validator, online service, live provider, or live-service provider
test was used.
