# EPUB3 Link Href Suffix Report

Scope: compact EPUB3 package ingestion now aggregates query and fragment suffix provenance across link-bearing package metadata.

- `EpubPackage::summary()` exposes `linkHrefSuffixes` and WordPress `linkHrefSuffixItems`.
- The report covers OCF metadata links, OPF package metadata links, and nested OPF collection links.
- Review rows preserve source, source index, collection path/id/role, href, target, stripped package part, local/missing/external policy state, query, fragment, manifest id, media type, and diagnostics.
- This stays native PHP only. It does not invoke Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 file, 2808 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 46 files, 86617 assertions, 0 failures.

Metric movement:

- `phpPass`: 3671 -> 3672
- `phpFail`: 0
- `mappedEpubLinkHrefSuffixReportCases`: 0 -> 1
- `epubLinkHrefSuffixReportAssertions`: 0 -> 34
