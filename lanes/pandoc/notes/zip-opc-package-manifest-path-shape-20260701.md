# ZIP/OPC Package Manifest Path Shape

Slice: `plib-uxyy8`

## Summary

`ZipPackage::packageManifestPreflight()` now exposes deterministic entry path
shape metadata for shared ZIP/OPC package handoff:

- each package manifest entry records `pathSegments`, `pathSegmentCount`, and
  `directoryDepth` alongside its existing `directoryRoot`;
- package manifests report `maxPathSegmentCount`, `maxDirectoryDepth`, and
  central-directory-order `deepestEntryNames`;
- the path-shape fields are included in the package manifest hash payload;
- `OpcRelationshipGraph::preflightZipEntryManifest()` carries the same
  path-shape fields into OPC ZIP entry manifest rows before XML package graph
  construction.

This keeps DOCX/OpenXML, EPUB3, ODF/ODT, and raw OPC package callers on shared
native PHP ZIP/OPC provenance for package layout review without exposing entry
payload bytes.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `2 test files, 9995 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, `zip`/`unzip` command, `ZipArchive`, online service, live
provider test, or payload-expanding external tool was invoked.
