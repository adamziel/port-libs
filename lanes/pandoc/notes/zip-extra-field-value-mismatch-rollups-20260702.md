# ZIP Extra-Field Value Mismatch Rollups

Bead: plib-oduq3

Date: 2026-07-02

## Slice

- Added package-level ZIP extra-field value mismatch ID rollups to `ZipPackage::extraFieldPreflight()` and `ZipPackage::extraFieldPolicyPreflight()`.
- Surfaced the same rollups through OPC ZIP entry and raw central-directory manifest preflights as `zipMismatchedExtraFieldValueIdCount`, `zipMismatchedExtraFieldValueIds`, and `zipMismatchedExtraFieldValueIdHexes`.
- Kept entry-level mismatch diagnostics unchanged while making the aggregate blocker IDs available without walking nested `zipExtraFields`.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` - 1 file, 6,074 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` - 1 file, 5,390 assertions, 0 failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
