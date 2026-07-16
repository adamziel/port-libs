# ODF/ODT Embedded Object Package Provenance

Slice: `pandoc-odt-embedded-object-package-provenance`

This slice adds compact `OpenDocumentPackage` review provenance for embedded ODF object packages.

- Detects manifest-declared embedded object roots such as chart and spreadsheet object packages, including URI-decoded package roots.
- Adds `packageObjects` summaries with root parts, object types, contained-part byte counts, declared/missing/undeclared contained-part counts, and issue codes.
- Adds `embedded-object-root` and `embedded-object-part` package inventory roles.
- Keeps embedded object package bytes metadata-only with `embedded-object-package-bytes-blocked`.
- Excludes object-contained preview images from normal document media handoff.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 850 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 71240 assertions, 0 failures`

Metric delta:

- `phpPass`: `3212 -> 3213`
- `phpFail`: `0`
- `mappedOdtEmbeddedObjectPackageCases`: `1`
- `odtEmbeddedObjectPackageAssertions`: `77`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped denominator: `3239 -> 3240`

No Pandoc, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.

Scope boundary:

This stays inside compact ODT package ingestion and package review metadata. It does not add full embedded object document conversion, chart rendering, OLE decoding, external object validation, or office-suite parity.
