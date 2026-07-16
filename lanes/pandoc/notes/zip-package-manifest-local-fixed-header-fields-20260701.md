# ZIP package manifest local fixed-header fields

Date: 2026-07-01
Slice: `plib-rwnlu`

`ZipPackage::packageManifestPreflight()` now carries package-level local header
fixed-field provenance for every entry. The manifest records the per-entry
local fixed-header offsets, fixed field offsets, central/local fixed values,
data-descriptor placeholder state, match status, issue lists, and package-level
issue counts before reader-specific handoff.

The focused fixture covers stored and descriptor-backed deflated entries,
asserts the fixed local header byte offsets and placeholder values, and checks
that strict import and raw strict import expose the same package manifest.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,957 assertions, 0 failures

Direct-format parity remains unchanged. This slice only extends bounded native
PHP shared ZIP/OPC package provenance and does not invoke Pandoc, office suites,
TeX/browser engines, `zip`/`unzip`, Node tooling, Jupyter, live services, or
external validators.
