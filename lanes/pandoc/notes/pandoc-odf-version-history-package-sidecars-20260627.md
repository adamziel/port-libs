# ODF Version History Package Sidecars

Slice: `pandoc-odf-version-history-package-sidecars`

This slice adds bounded native PHP package-ingestion support for OpenDocument
`Versions/` package history parts.

The compact `OpenDocumentPackage` and rich `OdfReader` paths now classify
declared and undeclared `Versions/` entries as metadata-only `packageVersions`.
Version-list XML, nested historical content XML, image-like preview resources,
embedded OpenDocument package bytes, missing entries, encrypted entries, and
undeclared version parts are all surfaced through review metadata without
exposing version payload bytes as document media.

Important byte policy:

- `Versions/` entries use the `version-package` inventory role.
- Non-directory version history bytes are blocked under
  `version-package-bytes-blocked` unless a stronger existing policy applies,
  such as `encrypted-resource-bytes-blocked`.
- Image-like version previews and historical `.odt` package members stay out of
  `media` / `mediaParts` and WordPress handoff.

Focused validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderVersionPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderVersionPackageSidecarTest.php`
  - 1 file, 99 assertions, 0 failures
- Adjacent ODF package gate:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderVersionPackageSidecarTest.php lanes/pandoc/tests/OdfReaderDatabasePackageSidecarTest.php lanes/pandoc/tests/OdfReaderMetaInfSidecarTest.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 5 files, 2,196 assertions, 0 failures

No external Pandoc, office suite, unzip/zip, browser, TeX, or validator process
is invoked by the implementation or tests.
