# ODF Package Role Byte Buckets

Date: 2026-06-11
Bead: plib-47x3e
Base: origin/main 6995e705

This slice adds aggregate package-role byte provenance to native ODF/ODT package ingestion. `OdfReader` already exposed per-part package roles in `importReport.manifest.packageProvenance.parts`; this change adds `roleCounts` and `byteCountsByRole` so review queues can classify package material without scanning every ZIP entry.

The buckets reuse the existing `packagePartRoles()` classifications and report entry counts, uncompressed/compressed byte totals, exposable byte totals, manifest-declared entry counts, and undeclared entry counts for roles such as `manifest-declared`, `odf-content`, `odf-settings`, `media-resource`, `script-package`, `package-thumbnail`, and `undeclared-package-entry`.

Verification:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: 1 test file, 3852 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 64101 assertions, 0 failures.

Accounting:
- Adds 1 mapped ODF package role byte-bucket case.
- Adds 12 focused assertions.
- Moves Pandoc lane `phpPass` from 3075 to 3076.
- Moves mapped denominator from 3197 to 3198.
