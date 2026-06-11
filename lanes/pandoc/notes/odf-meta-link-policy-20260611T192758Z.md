# ODF meta link policy package ingestion

Bead: `plib-fsgvp`

Scope:
- Preserve repeated `meta:keyword` elements from `meta.xml` instead of keeping only the first keyword element.
- Preserve inert ODF link-policy metadata from `meta:template`, `meta:auto-reload`, and `meta:hyperlink-behaviour`.
- Expose the metadata through `OpenDocumentPackage::metadata()`, package summaries, and the generated document `metadata` attribute.

Assertions:
- Added `preserves ODT meta link policy metadata and repeated keywords`.
- Covers template `xlink:*` provenance, template `meta:date`/`meta:name`, auto-reload `xlink:*` plus `meta:delay`, hyperlink target-frame/show policy, and summary/document handoff.
- Adds 23 focused ODT assertions.

Verification:
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` passed: 1 file, 362 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 files, 65726 assertions, 0 failures.

Boundary:
- No Pandoc binary, office suite, zip/unzip command, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
