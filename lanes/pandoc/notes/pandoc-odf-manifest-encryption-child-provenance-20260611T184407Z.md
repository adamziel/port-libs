# pandoc-odf-manifest-encryption-child-provenance-20260611T184407Z

Slice: `plib-95psx` ODF/ODT OpenDocument package ingestion core blocker.

`OdfReader` and `OpenDocumentPackage` now preserve ODF manifest encryption
child multiplicity as inert package-review metadata. Encrypted manifest entries
retain the existing first `algorithm`, `keyDerivation`, and
`startKeyGeneration` compatibility fields, and now also expose repeated child
lists, child counts, unknown extension child summaries, and issue codes for
duplicate encryption children. Encrypted package bytes remain blocked.

This does not invoke Pandoc, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

Verification on current main `9b98274d9`:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 file, 3983 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 339 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 65388 assertions, 0 failures

Metric:

- `phpPass`: `3100 -> 3101`
- `phpFail`: `0`
- `mappedOdfManifestEncryptionChildCases`: `1`
- `odfManifestEncryptionChildAssertions`: `29`
