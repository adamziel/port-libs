# ZIP Package Manifest Data Descriptor Hash

Hook: `plib-jng87`, conflict-resolution replacement for `plib-wisp-yxqf`.

## Summary

- Current `main` already carries data-descriptor provenance in
  `ZipPackage::packageManifestPreflight()` through the newer
  `dataDescriptorBytes` manifest schema.
- This replacement keeps that schema and verifies streamed entries expose
  `usesDataDescriptor`, `dataDescriptorOffset`, `dataDescriptorBytes`,
  `dataDescriptorEnd`, and `dataDescriptorSha256`.
- The deterministic package manifest hash is explicitly covered with descriptor
  byte counts and descriptor SHA-256 values in the hashed payload.
- Raw strict import and instantiated strict import return the same descriptor
  manifest for shared ZIP/OPC package handoff paths.

## Accounting

This is an additive ZIP/OPC package provenance check. It does not change
direct-format reader parity rows; it closes the package-source hash coverage gap
for ZIP entries that use data descriptors between compressed payload bytes and
the next local header or central directory.

## Verification

- `git diff --check`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `jq empty` on changed JSON files, if any
- `rg` for conflict markers in changed files
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
