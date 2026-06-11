# ODF Package Provenance Encryption Slice

Bead: `plib-tjxkt`

Current base: `d504ad4468`

Change:
- `OdfReader` packageProvenance inventory now carries manifest encryption metadata for declared package parts.
- Package inventory rows expose `manifestEncryption`, `manifestEncryptionRecordCount`, and `manifestEncryptionIssueCodes` while retaining version and preferred-view-mode provenance.
- Manifest file-entry order rows now include version, preferred-view-mode, encryption record counts, and encryption issue codes.
- Added a focused repeated `manifest:encryption-data` fixture proving encrypted media bytes remain blocked while reviewer metadata stays visible.

Verification:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` (1 file, 4076 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 67217 assertions, 0 failures)

No Pandoc, office suite, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
