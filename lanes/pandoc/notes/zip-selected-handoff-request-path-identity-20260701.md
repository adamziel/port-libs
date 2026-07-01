# ZIP selected handoff request path identity

Date: 2026-07-01
Slice: `plib-t9fq2`

`ZipPackage::entryHandoffPreflight()` now carries package-path identity for
selected handoff requests even when the requested entry is missing from the ZIP.
Missing optional and required requests keep their normalized request path,
directory root, path-segment position review, basename/stem, and extension
metadata in the selected handoff manifest without reading package payload bytes.

The selected handoff manifest also records compact
`packagePathIdentitySourceCounts` and
`entryNamesByPackagePathIdentitySource` rollups so package importers can tell
which rows came from actual ZIP entry metadata and which rows were derived from
missing request paths.

This stays within native PHP ZIP/OPC package preflight behavior. It does not
invoke Pandoc, office suites, TeX/browser engines, Typst, `zip`/`unzip`, Node
tooling, live services, or external validators.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,626 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 5,261 assertions, 0 failures

Direct-format parity remains active in lane status. Broad full-suite baseline
failures remain tracked separately.
