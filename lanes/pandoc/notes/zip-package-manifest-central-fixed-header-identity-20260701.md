# ZIP package manifest central fixed-header identity

Date: 2026-07-01
Slice: `plib-o4xty`

`ZipPackage::packageManifestPreflight()` now includes each entry's central
directory fixed-header byte count in the deterministic package manifest hash
payload. The existing manifest already exposed aggregate central-directory
record, variable-field, raw-name, extra-field, raw-comment, and review-field
byte totals; this slice closes the remaining fixed-header identity gap so the
per-entry manifest payload accounts for the full central-directory source
record shape.

The focused fixture covers a package with central-only extra fields and entry
comments, verifies the manifest review-field totals against
`centralDirectoryVariableFieldsPreflight()`, and confirms raw strict import and
strict import carry the same package manifest.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,039 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 4,759 assertions, 0 failures

Direct-format parity remains active in lane status. This slice only extends
bounded native PHP shared ZIP/OPC package provenance and does not invoke
Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, Node tooling,
Jupyter, live services, or external validators.
