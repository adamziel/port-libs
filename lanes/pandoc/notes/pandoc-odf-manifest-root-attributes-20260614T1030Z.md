# ODF/ODT manifest root attribute provenance

Implemented bounded native PHP ODF/ODT package ingestion provenance for `manifest:manifest` root attributes.

`OdfReader` now preserves root-level manifest attribute counts, sorted names, attribute records, custom namespaced attributes, and custom attribute maps in:

- document `manifest` metadata;
- import report manifest metadata;
- package provenance summaries.

`OpenDocumentPackage` exposes the same root attribute provenance through `manifestRootAttributes()` and compact `manifestReview` summaries. This keeps package-review and WordPress import handoff metadata from dropping root-level review attributes while preserving existing manifest:file-entry handling.

The slice stays under `lanes/pandoc`. It does not invoke Pandoc, office suites, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php` -> 2 test files, 5950 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 test files, 83195 assertions, 0 failures

Accounting:

- `phpPass`: 3515 -> 3516
- `phpFail`: 0
- `mappedOdfManifestRootAttributeCases`: 2
- `odfManifestRootAttributeAssertions`: 27
