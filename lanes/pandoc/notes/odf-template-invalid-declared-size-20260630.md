# ODF Template Package Invalid Declared Size

Hook: `plib-k63e9`

Slice: native ODF/ODT package ingestion parity for `Templates/` package sidecars.

Change:
- `OdfReader` now reports template package entries with malformed `manifest:size` values using `odf-template-package-invalid-declared-size`.
- `OpenDocumentPackage` carries the same compact summary fields and issue code.
- Template package items now expose `declaredSizeRaw`, `declaredSizeValid`, `declaredSizeInvalid`, and the summary exposes `invalidDeclaredSizeCount`.

Validation:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php` (1 file, 137 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` (1 file, 2146 assertions, 0 failures)
