# Compact ODT Meta Link Policy Metadata

Bead: `plib-fsgvp`
Base: `30462ed7c`

This slice keeps compact OpenDocument package metadata handoff aligned with
ODT `meta.xml` link policy fields.

- `OpenDocumentPackage` now preserves all `meta:keyword` elements, including
  comma-separated keyword lists.
- Compact package metadata now carries `meta:template`, `meta:auto-reload`,
  and `meta:hyperlink-behaviour` attributes into package metadata, summary
  metadata, and document attrs.
- The focused test covers template href/type/title/date/show/actuate,
  auto-reload href/show/actuate/delay, hyperlink target-frame behavior, and
  package/document propagation.

Verification on 2026-06-11 UTC:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 test file, 356 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65559 assertions, 0 failures

No Pandoc, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
