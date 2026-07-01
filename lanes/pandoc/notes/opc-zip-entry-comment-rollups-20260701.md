# OPC ZIP Entry Comment Rollups

`OpcRelationshipGraph` now carries ZIP package-manifest entry comment rollups
through both constructed-package and raw central-directory OPC manifest
preflights.

The handoff exposes metadata-only fields already produced by the shared ZIP
package manifest:

- commented entry names;
- entry comment counts and summary counts;
- source-record byte totals for commented entries;
- central-directory comment offsets, byte counts, and SHA-256 hashes;
- entry comment byte-exposure policy flags.

The raw central-directory path normalizes its entry records into the same
summary shape as `ZipPackage::packageManifestPreflight()` so callers can compare
parsed and raw package review surfaces without exposing comment bytes.

Validation:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  (5,349 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  (6,061 assertions, 0 failures)
