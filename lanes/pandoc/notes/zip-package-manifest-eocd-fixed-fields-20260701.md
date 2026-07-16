# ZIP package manifest EOCD fixed fields

Date: 2026-07-01
Slice: `plib-4lyqe`

`ZipPackage::packageManifestPreflight()` now carries a metadata-only
end-of-central-directory fixed-field summary in both `packageSource` and the
top-level package manifest. The summary reuses the native EOCD fixed-field
preflight offsets and scalar values, reports issue counts, and participates in
the deterministic `zip-package-manifest-v1` hash payload.

The package manifest intentionally omits the full raw package comment and full
comment hex while preserving bounded comment length, offsets, preview hex, and
byte-exposure policy. Raw strict import still retains the standalone EOCD
fixed-field preflight for diagnostic paths.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,983 assertions, 0 failures

Direct-format parity remains unchanged. This slice only extends bounded native
PHP shared ZIP/OPC package manifest provenance and does not invoke Pandoc,
office suites, TeX/browser engines, `zip`/`unzip`, Node tooling, Jupyter, live
services, or external validators.
