# ODF ZIP Package Manifest Entry Scalars - 2026-07-01

Bead: `plib-zvikg`

This slice keeps ODF/ODT package ingestion metadata-only while promoting already-computed
per-entry `ZipPackage::packageManifestPreflight()` scalars into compact and rich ODF package
review rows.

Added per-entry ODF package provenance fields:

- `zipPackageManifestCompressionMethodName`
- `zipPackageManifestCrc32Hex`
- `zipPackageManifestCompressedSize`
- `zipPackageManifestUncompressedSize`
- `zipPackageManifestExpansionRatio`
- `zipPackageManifestVersionMadeBy`
- `zipPackageManifestMadeByHostSystem`
- `zipPackageManifestMadeByHostSystemName`
- `zipPackageManifestMadeByVersion`
- `zipPackageManifestVersionNeededToExtract`
- `zipPackageManifestCreatorVersionMeetsNeeded`
- `zipPackageManifestCreatorVersionComparison`
- `zipPackageManifestCreatorVersionDelta`
- `zipPackageManifestCreatorHostSystemIsKnown`
- `zipPackageManifestCreatorHostSystemIssues`
- `zipPackageManifestPackagePartExtension`
- `zipPackageManifestPackagePartExtensionKey`
- `zipPackageManifestExtensionlessPackagePart`

The fields are surfaced through:

- `OpenDocumentPackage::summarize()['packageInventory']['parts']`
- `OpenDocumentPackage::summarize()['packageIdentity']['packageEntries']`
- `OdfReader` rich package provenance `parts`
- `OdfReader` rich `packageIdentity['packageEntries']`

Focused validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
  - 1 file, 469 assertions, 0 failures

No Pandoc, office suite, TeX/browser engine, zip/unzip command, Node tooling, or external
validator was invoked.
