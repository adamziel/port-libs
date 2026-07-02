# ODF Linked Resource Invalid Declared Size Provenance

Slice: `plib-mh4oi` on 2026-07-01.

ODF/ODT linked resource package sidecars now preserve malformed `manifest:size` provenance across rich `OdfReader` packageLinkedResources and compact `OpenDocumentPackage` packageLinkedResources:

- Per-item `declaredSizeRaw`, `declaredSizeValid`, `declaredSizeInvalid`, and `declaredSizeMismatch` are retained for `Links/` package entries.
- Summary-level `invalidDeclaredSizeCount` is reported for linked resource sidecars.
- Malformed linked resource sizes add `odf-linked-resource-package-invalid-declared-size` while keeping `Links/` bytes metadata-only and blocked from document media and WordPress output.

Focused validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderLinkedResourcePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLinkedResourcePackageSidecarTest.php` -> `1 test file, 178 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLinkedResourcePackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` -> `3 test files, 8012 assertions, 0 failures`

No Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node, `zip`/`unzip`, external validator, or live service was invoked.
