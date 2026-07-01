# ZIP package manifest directory root summaries

Date: 2026-07-01
Slice: `plib-tqmps`

`ZipPackage::packageManifestPreflight()` now carries deterministic directory
root provenance for shared ZIP/OPC package handoff:

- each manifest entry records its top-level directory root;
- package manifests summarize entry, file, and directory counts by root;
- root summaries carry compressed, uncompressed, local-record, and
  data-descriptor byte totals plus central-directory-order entry names;
- the root summaries are included in the manifest hash payload, so package
  manifest identity changes when package layout by root changes.

This stays within the native PHP bounded ZIP/OPC preflight surface and does not
invoke Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, Jupyter, Node
tooling, live services, or external validators.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 4,996 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 4,759 assertions, 0 failures

Direct-format parity remains active in lane status. Broad full-suite baseline
failures remain tracked separately.
