# ZIP OPC creator host system provenance

Date: 2026-07-01
Slice: `plib-jvzb9`

`OpcRelationshipGraph::preflightZipEntryManifest()` and
`preflightZipCentralDirectoryManifest()` now summarize ZIP creator host-system
metadata at the OPC handoff layer. The manifests expose host counts, entry-name
buckets, OPC role buckets, handoff-kind buckets, creator-version comparison
buckets, and creator-host issue buckets while retaining the existing per-entry
creator host and version fields.

The focused fixture mixes `ms-dos-fat`, `unix`, `windows-ntfs`, and `os-x`
central-directory creator hosts across content types, package relationships,
XML, directory, and media entries, then asserts raw central-directory parity
with constructed-package manifest output.

Validation:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 5,213 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 2 files, 10,622 assertions, 0 failures

Direct-format parity accounting remains unchanged. This slice is limited to
bounded native PHP ZIP/OPC package metadata and does not invoke Pandoc, office
suites, TeX/browser engines, `zip`/`unzip`, Jupyter, Node tooling, live
services, or external validators.
