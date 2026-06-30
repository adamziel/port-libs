# ZIP Package Manifest Data Descriptor Hash

Hook: `plib-jng87`, Pandoc shared ZIP/OPC package core blocker slice.

## Summary

- Added data-descriptor provenance to `ZipPackage::packageManifestPreflight()`.
- Each streamed entry now reports `usesDataDescriptor`,
  `dataDescriptorOffset`, `dataDescriptorLength`, `dataDescriptorEnd`, and
  `dataDescriptorSha256`.
- The manifest summary now also reports aggregate descriptor entry and byte
  counts, and the deterministic manifest hash includes descriptor length and
  descriptor SHA-256 for streamed package entries.
- Raw strict import and instantiated strict import return the same descriptor
  manifest for DOCX, EPUB, ODF, and generic OPC ZIP handoff paths.

## Accounting

This is an additive ZIP/OPC package primitive. It does not change direct-format
reader parity rows; it closes a package-source provenance gap for ZIP entries
that use data descriptors between compressed payload bytes and the next local
header or central directory.

Focused ZIP manifest coverage adds one streamed-entry package-manifest case and
updates the deterministic manifest assertions for descriptor metadata fields.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 4902 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 4668 assertions, 0 failures

No Pandoc executable, office suite, `zip`/`unzip`, ZipArchive, browser renderer,
Node tooling, online service, live provider test, or external validator was
invoked.
