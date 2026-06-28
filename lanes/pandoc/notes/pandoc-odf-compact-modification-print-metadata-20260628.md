# ODF compact modification and print metadata

Hook: `plib-b6ijf`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

This slice aligns compact `OpenDocumentPackage` meta.xml parsing with the rich
`OdfReader` package metadata path for ODF edit and print provenance. Compact
package summaries now preserve `meta:modification-date`,
`meta:modification-time`, `meta:printed-by`, `meta:print-date`, and
`meta:print-time` without exposing package bytes or invoking external office
tools.

The focused regression extends the compact metadata/statistics case and verifies
the new fields through `metadata()`, package `summarize()`, and
`readContentDocument()` metadata handoff.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 2,119 assertions, 0 failures

Parity accounting: no mapped denominator change claimed. This is a compact/rich
ODF metadata parity closure inside native PHP package ingestion.
