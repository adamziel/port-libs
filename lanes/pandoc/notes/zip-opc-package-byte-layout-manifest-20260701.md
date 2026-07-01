# ZIP/OPC Package Byte-Layout Manifest Slice

Slice: `plib-f0kt5`

## Summary

`ZipPackage::packageManifestPreflight()` now includes a compact
`packageByteLayout` summary derived from the existing raw ZIP byte-layout
preflight. The summary keeps package review metadata in the manifest path:

- local-region offset, size, hash, accounted bytes, and contiguity flags;
- prefix, inter-entry gap, trailing-byte, and unaccounted-byte counters;
- central-directory-to-EOCD gap metadata, including central-directory
  signature gaps;
- per-entry local span offsets, local record byte counts, next offsets,
  unclaimed bytes, and issue codes.

The same manifest fields are forwarded through OPC ZIP manifest summaries and
DOCX package provenance/zipPackage metadata. Payload bytes are not exposed, and
the slice does not invoke Pandoc, Office, zip/unzip, or external validators.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestExpansionRatioBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestExpansionRatioBucketsTest.php`
