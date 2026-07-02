# DOCX Package Root Relationship Resource Summary Lookup Maps

Slice: `plib-pqa9w`, DOCX OpenXML package ingestion.

`DocxOpenXmlReader` now mirrors the package-root relationship resource lookup
maps that were already computed under `packageRootRelationshipResources` into
`packageProvenance.summary`. The summary now includes directory-base-name,
base-name-stem, case-folded stem, existing/missing, and target-part lookup maps
for both package-root resource targets and nested target relationships.

This remains metadata-only package review data. Package-root resource payload
bytes and nested target bytes stay blocked by the existing byte-exposure
policies.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageRootRelationshipResourceSummaryLookupMapsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageRootRelationshipResourceSummaryLookupMapsTest.php`
  - 1 file, 36 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageRootRelationshipResourceSummaryLookupMapsTest.php lanes/pandoc/tests/DocxOpenXmlPackageRootRelationshipResourceLookupMapsTest.php lanes/pandoc/tests/DocxOpenXmlPackageRootRelationshipResourceBucketsTest.php lanes/pandoc/tests/DocxOpenXmlPackageRootRelationshipResourceReferenceSuffixesTest.php`
  - 4 files, 219 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 12508 assertions, 0 failures

No Pandoc, Word, LibreOffice, office suite, TeX/browser engine, Typst, Jupyter,
Node, zip/unzip, validators, online services, live provider tests, or
live-service provider tests were invoked.
