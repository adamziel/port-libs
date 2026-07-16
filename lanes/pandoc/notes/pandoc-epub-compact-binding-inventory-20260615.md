# pandoc-epub-compact-binding-inventory

Scope: bounded native PHP EPUB3 package ingestion slice for OPF media-type binding inventory in compact package review packets.

## Summary

`EpubPackage::summary()` now includes OPF `<bindings>` as a first-class `media-type-bindings` case in `compactPackageReport` and `wordpressImport.compactPackageReport`. The case reports bound media types, unique handler IDs and handler package parts, local/external/missing/encrypted handler buckets, byte-exposure counts, byte totals, and binding diagnostics.

Rebased onto current main `8a475da1ae`.

The slice does not execute handlers or fetch remote handlers. It preserves static review metadata only and does not invoke Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Evidence

- phpPass moves `15329 -> 15330`; phpFail remains `0`.
- Mapped upstream cases move `15000 -> 15001`.
- Added `mappedEpubCompactBindingInventoryCases = 1`.
- Added `epubCompactBindingInventoryAssertions = 20`.
- Focused `EpubPackageTest.php`: `1 file, 3879 assertions, 0 failures`.
- Full `lanes/pandoc/tests`: `181 files, 165466 assertions, 0 failures`.

## Non-overlap

This does not repeat accepted OPF binding handler target provenance, encrypted binding-handler diagnostics, manifest fallback chains, media overlays, NCX binding selection, package inventory local-header order, metadata links, collection links, or resource-property byte policy. The new surface is only compact report aggregation for the already parsed OPF media-type binding inventory.
