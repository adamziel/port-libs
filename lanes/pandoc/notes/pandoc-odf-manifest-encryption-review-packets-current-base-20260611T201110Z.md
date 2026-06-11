# ODF Manifest Encryption Review Packets

Bead: `plib-fskan`
Base: `02755fb79`

## Change

- `OpenDocumentPackage::summarize()` now carries parsed manifest encryption metadata into `mediaParts`.
- Manifest review rows and encrypted-item rollups now include the same inert `encryption` packet.
- Package inventory rows now expose `manifestEncryption` alongside the existing encrypted-byte blocking fields.
- No encrypted package bytes are exposed; this is metadata-only package provenance.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` passed: 1 test file, 394 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66077 assertions, 0 failures.

## Status Delta

- `lane-status.json` `phpPass`: `3120 -> 3121`
- Added `mappedOdfManifestEncryptionReviewPacketCases: 1`
- Added `odfManifestEncryptionReviewPacketAssertions: 11`
