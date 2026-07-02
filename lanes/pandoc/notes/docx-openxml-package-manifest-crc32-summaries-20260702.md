# DOCX/OpenXML ZIP package manifest CRC32 summaries

Work item: `plib-q97dx`

## Summary

`DocxOpenXmlReader` now promotes `ZipPackage::packageManifestPreflight()`
CRC32 and duplicate-CRC32 aggregate fields into DOCX/OpenXML package
provenance. The handoff exposes `zipPackageManifestCrc32*` fields through
`packageProvenance.summary` and matching `packageManifestCrc32*` fields through
`packageProvenance.zipPackage`.

The projected metadata includes CRC32 summary counts, ordered CRC32 hex
buckets, duplicate CRC32 hex counts, duplicate entry counts, duplicate hex
lists, duplicate summaries, directory roots, compression method names, entry
names, and source-record byte totals. Entry payload bytes remain hidden; the
summaries carry only metadata already present in the shared ZIP manifest.

## Non-overlap

This slice does not change ZIP parsing, OPC relationship resolution, DOCX XML
conversion, media extraction, or byte exposure policy. It only wires existing
shared ZIP manifest CRC32 rollups into DOCX package-review provenance.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackageManifestCrc32SummariesTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestCrc32SummariesTest.php`
  - 1 file, 29 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackageManifestCrc32SummariesTest.php lanes/pandoc/tests/DocxOpenXmlPackageManifestExpansionRatioBucketsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 3 test files, 12,563 assertions, 0 failures

Direct-format parity remains active for the Pandoc lane. No Pandoc binary,
office suite, TeX/browser engine, `zip`/`unzip`, Jupyter, Node tooling, online
service, or external validator was invoked.
