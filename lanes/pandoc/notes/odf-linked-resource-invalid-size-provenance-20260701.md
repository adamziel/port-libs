# ODF linked-resource invalid size provenance

Slice: `plib-x6p8b` / ODF/ODT OpenDocument package ingestion.

`Links/*` package sidecars now preserve malformed `manifest:size` provenance in
both rich `OdfReader` package metadata and compact `OpenDocumentPackage`
summaries. Linked-resource rows expose `declaredSizeRaw`,
`declaredSizeValid`, `declaredSizeInvalid`, and
`invalidDeclaredSizeCount`, with
`odf-linked-resource-package-invalid-declared-size` attached to the package
review item while the manifest row continues to report
`odf-manifest-invalid-declared-size`.

The slice keeps linked-resource cache bytes metadata-only:
`Links/*` entries remain out of document media exposure and WordPress handoff,
with `linked-resource-package-bytes-blocked` or directory byte policies
unchanged.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderLinkedResourcePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLinkedResourcePackageSidecarTest.php`
