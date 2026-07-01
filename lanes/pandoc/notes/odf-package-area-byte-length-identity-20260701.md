# ODF package area byte-length identity coverage

Date: 2026-07-01
Slice: `plib-yn5k5`

Added focused ODF/ODT package-ingestion coverage for package-area byte and
compressed-byte buckets across compact and rich package identity surfaces.

The fixture asserts that `packageAreaByteLengths` and
`packageAreaCompressedByteLengths` remain identical across:

- compact `OpenDocumentPackage` inventory
- compact package identity
- rich `OdfReader` package provenance
- rich package identity
- document package provenance
- document package identity

It uses an in-memory ODT package with root, `META-INF/`, `Pictures/`,
`Object 1/`, `Configurations2/`, `Scripts/`, and undeclared `Notes/` entries.
All entries are stored so expected byte and compressed-byte maps are explicit
and deterministic without package payload exposure.

This does not repeat the accepted package-area summary object test, path-depth
role bucket test, ZIP package-manifest expansion-ratio bucket tests, CRC32
aggregate test, or ZIP source-record directory/root tests. It only locks the
top-level byte maps into the identity/provenance parity contract.

Validation:

- `php -l lanes/pandoc/tests/OdfPackageAreaByteLengthIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageAreaByteLengthIdentityTest.php`
  - 1 file, 56 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfPackagePathDepthRoleBucketsTest.php lanes/pandoc/tests/OdfPackageAreaByteLengthIdentityTest.php`
  - 3 files, 188 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageAreaByteLengthIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
  - 3 files, 2,395 assertions, 0 failures

No Pandoc binary, office suite, TeX/browser engine, Typst, Node, `zip`/`unzip`,
Jupyter, live service, or external validator was invoked. Direct-format parity
remains active in lane status and `UPSTREAM_TEST_MANIFEST.json`.
