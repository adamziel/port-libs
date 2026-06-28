# ODF Encoded Sidecar Paths

`plib-84rk2` keeps ODF/ODT package sidecar classification safe when manifest
`full-path` values use URI-encoded package references. Rich `OdfReader` now
rejects malformed percent escapes before decoding, matching compact
`OpenDocumentPackage` validation.

The focused regression covers an encoded attachment sidecar path with query and
fragment provenance. Both readers decode it to the same package part, retain the
raw manifest reference metadata, block byte exposure under
`attachment-package-bytes-blocked`, and keep the sidecar out of document media
handoff.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderEncodedPackagePathTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderEncodedPackagePathTest.php`
  passed: `1` file, `42` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderEncodedPackagePathTest.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed: `3` files, `2,161` assertions, `0` failures
