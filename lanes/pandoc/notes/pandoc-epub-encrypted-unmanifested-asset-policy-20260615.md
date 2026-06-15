# EPUB encrypted unmanifested asset policy

Slice: `epub-encrypted-unmanifested-asset-policy-20260615`

## Scope

EPUB3 package ingestion now carries OCF encryption entries into `EpubReader`
asset reporting for ZIP resources that are present in the package but absent
from the OPF manifest.

The reader now:

- infers media type and review role for encrypted non-manifest package parts
  from the package path;
- reports encrypted unmanifested resources as metadata-only package assets;
- preserves byte length and CRC package provenance while withholding SHA-256
  byte digests for encrypted unmanifested resources;
- carries `reviewPolicy`, `byteExposurePolicy`, and blocked attachment policy
  into `importReport.assets.unmanifestedItems`.

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderer, external
validator, online service, live provider test, or live-service provider test is
used.

## Accounting

- `phpPass`: `3685 -> 3686`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` upstream mapped: `3714 -> 3715`
- `mappedEpubEncryptedUnmanifestedAssetPolicyCases`: `0 -> 1`
- `epubEncryptedUnmanifestedAssetPolicyAssertions`: `+23`

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 4620 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87053 assertions, 0 failures`
