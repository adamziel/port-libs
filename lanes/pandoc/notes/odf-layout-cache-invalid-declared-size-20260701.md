# ODF layout-cache invalid declared size

ODF/ODT layout-cache sidecar summaries now preserve invalid `manifest:size`
diagnostics in both reader and compact package metadata.

- Rich `packageLayoutCaches` reports expose `declaredSizeRaw`,
  `declaredSizeValid`, `declaredSizeInvalid`, `invalidDeclaredSizeCount`,
  and `odf-layout-cache-invalid-declared-size`.
- Compact `OpenDocumentPackage::summarize()['packageLayoutCaches']` exposes
  the same invalid-size fields and issue code.
- Layout-cache bytes remain blocked with
  `layout-cache-package-bytes-blocked`; this is package metadata only.
- Direct-format parity: no direct text/table/media conversion behavior changes.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderLayoutCachePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLayoutCachePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
