# ODT Configuration Package Part Provenance

This slice keeps compact ODT `Configurations2/...` package sidecars
metadata-only during package review.

`OpenDocumentPackage` now classifies non-directory `Configurations2/`
entries as `configuration-package` parts. Declared entries still preserve
manifest, ZIP size, compression, and stored CRC provenance, but bytes are not
exposed to media/review payloads and the byte policy is
`configuration-package-bytes-blocked`. Image-typed configuration sidecars are
also excluded from document media handoff.

Focused coverage:

- `keeps compact ODT configuration package parts metadata only`
- Adds 1 mapped ODT package-ingestion case.
- Adds 22 focused assertions.

Verification:

- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 671 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68847 assertions, 0 failures.

No Pandoc binary, office suite, zip/unzip CLI, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.
