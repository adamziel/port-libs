# ODF ZIP package manifest extension buckets

Date: 2026-07-01
Slice: `plib-cl79j`

`OpenDocumentPackage` and `OdfReader` now carry the shared
`ZipPackage::packageManifestPreflight()` package-part extension buckets through
ODF/ODT compact and rich package provenance:

- `zipPackageManifestExtensionlessPackagePartCount`
- `zipPackageManifestHasExtensionlessPackageParts`
- `zipPackageManifestPackagePartExtensionSummaryCount`
- `zipPackageManifestPackagePartExtensions`
- `zipPackageManifestPackagePartExtensionSummaries`

The fields are projected into compact package inventory, compact package
identity, rich package provenance, and rich package identity. This preserves
the shared ZIP manifest accounting for extensionless package parts, normalized
extension lists, and extension summary byte/count buckets without exposing
package payload bytes.

This slice does not change ZIP parsing, ODF media extraction, byte exposure
policy, or package payload access. It only mirrors existing bounded ZIP manifest
aggregate metadata through the ODF/ODT reporting surfaces.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
  - 1 file, 265 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - 3 files, 7,795 assertions, 0 failures

No Pandoc binary, office suite, TeX/browser engine, `zip`/`unzip`, Jupyter,
Node tooling, live service, or external validator was invoked. Direct-format
parity remains active in lane status.
