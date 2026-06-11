# EPUB3 Compact Collection Role Vocabulary Provenance

Slice: `plib-z7blr`

Base: `71ce25fbede5f0fddff3c600124768b6a12172a5` (`origin/main` at slice start)

The compact EPUB3 package ingestion path now preserves OPF collection
role vocabulary provenance for native PHP reviewer handoff. Collection
records include the raw role string, split role values, primary valid
role, per-token classification, prefixed vocabulary IRI resolution,
absolute URL-with-fragment roles, invalid token diagnostics, missing
fragment diagnostics, unknown prefix diagnostics, duplicate role
diagnostics, nested collection propagation, and WordPress import
collection diagnostics.

Verification on the starting base:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  passed 1 test file, 1583 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 44 test files, 66722 assertions, 0 failures.

Lane status movement: one compact EPUB3 package role vocabulary PASS case
with 38 top-level focused checks; `phpPass` moves from 3133 to 3134 while
`phpFail` remains 0.
