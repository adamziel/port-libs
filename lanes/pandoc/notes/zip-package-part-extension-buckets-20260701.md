# ZIP package part extension buckets

Work item: `plib-ebgsc`

## Summary

`ZipPackage::packageManifestPreflight()` now records neutral package-part
extension metadata for ZIP/OPC handoff. Each manifest entry includes
`packagePartExtension`, `packagePartExtensionKey`, and
`extensionlessPackagePart`, while the package manifest exposes aggregate
`packagePartExtensionSummaries`, `packagePartExtensions`, and
`extensionlessPackagePartCount` fields.

The summaries bucket file entries by normalized extension, track compressed and
uncompressed byte totals, local-record bytes, data-descriptor counts/bytes, and
the entry names in each bucket. Directory entries are preserved in the manifest
but excluded from extension buckets.

## Non-overlap

This slice does not change ZIP parsing, compression, OPC relationship handling,
DOCX/EPUB/ODF readers, media extraction, or payload exposure. It only extends
the existing deterministic native ZIP package manifest with extension rollups.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php` over the ZIP/DOCX/EPUB/ODF package-focused test
  set

No Pandoc binary, office suite, TeX/browser engine, unzip/zip command, Node
tooling, or external validator was invoked.
