# EPUB Auxiliary Navigation Target Policy

Slice: `pandoc-epub-auxiliary-navigation-target-policy`

This slice keeps EPUB3 package ingestion native to PHP and extends compact
`EpubPackage` package review metadata so non-primary EPUB navigation sections
such as list-of-illustrations and list-of-tables expose a target policy report.

`auxiliaryNavigationTargetPolicy` now classifies auxiliary nav targets as local,
remote, missing, valid, or byte-blocked while preserving ZIP byte provenance,
query/fragment suffixes, diagnostics, and WordPress import handoff fields.

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file, 3986 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 181 files, 165672 assertions, 0 failures

Metric movement:

- `phpPass`: 15333 -> 15334
- `phpFail`: 0
- mapped upstream cases: 15004 -> 15005
- `mappedEpubAuxiliaryNavigationTargetPolicyCases`: 1
- `epubAuxiliaryNavigationTargetPolicyAssertions`: 36
