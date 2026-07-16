## XLSX image anchor transform metadata - 2026-07-01

- Added bounded picture-level drawing provenance for image anchors: blip reference kind/compression state, crop rectangle, shape transform EMUs/pixels, flips, rotation, and preset geometry.
- Kept image bytes blocked except for the existing bounded metadata read used for dimensions and SHA-256.
- Covered with the existing in-memory XLSX style/comment/image fixture in `XlsxReaderTest.php`.

Verification:

- `php -l lanes/pandoc/src/XlsxReader.php`
- `php -l lanes/pandoc/tests/XlsxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XlsxReaderTest.php`
