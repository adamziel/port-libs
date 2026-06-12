# Shared ZIP selected handoff role compression provenance

Bead: plib-manuj

Base: current main d53b14e45e

Scope: `ZipPackage::entryHandoffPreflight()` role summaries now expose selected-entry compression and expansion provenance per role. The summary includes aggregate selected expansion ratio, stored/deflated/unsupported/supported method counts, role-local compression method buckets, and unknown-expansion entry records for zero-compressed selected entries before reader handoff.

Fixture: `ZipPackageTest.php` selects a deflated main document, a stored supporting part, and an unsupported method-12 zero-compressed supporting part. The test asserts role-local byte totals, expansion ratios, compression buckets, unknown expansion records, and the unreadable-entry diagnostic for the unsupported selected package part.

Verification:

- `php -l lanes/pandoc/src/ZipPackage.php` -> no syntax errors
- `php -l lanes/pandoc/tests/ZipPackageTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> 1 test files, 3934 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 69903 assertions, 0 failures

Accounting: `phpPass` 3182 -> 3183; `mappedZipSelectedHandoffRoleCompressionCases` = 1; `zipSelectedHandoffRoleCompressionAssertions` = 28.
