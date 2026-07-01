# ODF layout-cache invalid size provenance

Slice: `plib-osst3` / ODF/ODT OpenDocument package ingestion.

`layout-cache` package sidecars now preserve malformed `manifest:size`
provenance in both rich `OdfReader` package metadata and compact
`OpenDocumentPackage` summaries. Layout-cache rows expose `declaredSizeRaw`,
`declaredSizeValid`, `declaredSizeInvalid`, and `invalidDeclaredSizeCount`,
with `odf-layout-cache-invalid-declared-size` attached to the package review
item while the manifest row continues to report
`odf-manifest-invalid-declared-size`.

The slice keeps layout-cache bytes metadata-only:
`layout-cache` remains out of document media exposure and WordPress handoff,
with `layout-cache-package-bytes-blocked` unchanged.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderLayoutCachePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLayoutCachePackageSidecarTest.php`
