# DOCX ZIP Package Manifest Size Profile

DOCX package provenance now carries the ZIP package manifest size profile through
both `zipPackage.packageManifest*` and `summary.zipPackageManifest*` fields:

- aggregate expansion ratio
- largest entry
- zero-byte entry counts and entries
- unknown expansion-ratio counts and entries

These are metadata-only values projected from `ZipPackage::packageManifestPreflight()`;
no ZIP bytes are exposed.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `git diff --check origin/main -- lanes/pandoc`
