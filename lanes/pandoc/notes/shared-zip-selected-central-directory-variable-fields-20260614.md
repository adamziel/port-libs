# Shared ZIP selected central-directory variable fields

## Scope

- Slice: `pandoc-zip-selected-central-directory-variable-field-handoff`
- Base: current main `f533906a11`
- Area: shared ZIP/OPC selected-entry package handoff before DOCX, EPUB, and ODF readers expose package bytes.

## Change

- `ZipPackage::entryHandoffPreflight()` now carries selected-entry central-directory fixed-header and variable-field byte provenance.
- The selected-entry source span metadata includes raw central name, central extra field, raw central comment, review-field byte buckets, offsets, and SHA-256 digests.
- Aggregate selected-entry byte counters now include central-directory fixed-header, variable-field, raw-name, extra-field, raw-comment, and review-field buckets.
- Central-directory record accounting now reports a mismatch issue when derived fixed plus variable fields do not cover the selected central record bytes.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed `1 test files, 4582 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests` passed `46 test files, 82365 assertions, 0 failures`.

No Pandoc, office suites, TeX/PDF engines, browser renderers, zip/unzip, ZipArchive, external validators, online services, live provider tests, or live-service provider tests were invoked.
