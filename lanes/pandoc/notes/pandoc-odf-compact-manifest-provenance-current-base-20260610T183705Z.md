# pandoc-odf-compact-manifest-provenance-current-base-20260610T183705Z

Slice: `plib-yxla3` / ODF/ODT OpenDocument package ingestion.

This slice extends the compact native PHP `OpenDocumentPackage` helper so its
manifest handoff preserves package-level provenance that was previously only
available in the richer `OdfReader` path:

- root `manifest:manifest/@manifest:version`;
- per-entry `manifest:preferred-view-mode`;
- inert `manifest:encryption-data` metadata for encrypted package resources;
- compact summary `encryptedCount` and `encryptedParts`.

The focused regression keeps the root file-entry version absent while asserting
that the manifest-root version is still preserved, and verifies checksum,
algorithm, key-derivation, and start-key-generation metadata on an encrypted
image resource. This remains package-review metadata only; the slice does not
decrypt, expose encrypted cleartext, alter ZIP parsing, or change ODT content
conversion.

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 93 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 61006 assertions, 0 failures

No Pandoc, office suite, zip/unzip command, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.
