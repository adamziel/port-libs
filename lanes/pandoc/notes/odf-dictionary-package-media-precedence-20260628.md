# ODF dictionary package media precedence

Slice: `plib-7iltj` / ODF/ODT OpenDocument package ingestion.

Compact `OpenDocumentPackage` media-resource review now treats image-like
`Dictionaries/*` package members as dictionary package sidecars before document
media. This aligns the compact path with the existing rich `OdfReader`
classification: dictionary preview images remain metadata-only under
`dictionary-package-bytes-blocked`, stay out of `mediaParts`, and are reported
through `manifestReview.mediaResources.packageRolePrecedenceItems` with the
`dictionary-package` role.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
- Red-first focused run before the fix failed with `Expected: 1 / Actual: 0`
  for compact `packageRolePrecedenceCount`.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php`
  - 1 file, 118 assertions, 0 failures
- Post-rebase `php tools/run-tests.php lanes/pandoc/tests/OdfReaderDictionaryPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 2 files, 2,081 assertions, 0 failures
