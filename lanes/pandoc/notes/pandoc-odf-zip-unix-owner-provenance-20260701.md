# pandoc-odf-zip-unix-owner-provenance-20260701

Slice: `plib-ug5up`, ODF/ODT OpenDocument package ingestion.

This slice carries Info-ZIP Unix UID/GID extra-field provenance from the shared
`ZipPackage::unixOwnerPreflight()` into ODF package review surfaces:

- compact `OpenDocumentPackage` package inventory and metadata-only package
  identity;
- rich `OdfReader` package provenance, document manifest attributes, and
  metadata-only package identity;
- per-entry central/local owner records, presence flags, match flag, and issue
  codes for matched, mismatched, and local-only owner metadata.

The ODF byte-exposure policy is unchanged. Declared media parts remain exposable
only through existing package-byte rules, and undeclared entries with owner
metadata remain `undeclared-package-entry-no-bytes`. The owner records expose
only parsed numeric metadata; package payload bytes and raw extra-field payloads
are not exposed.

Focused fixture coverage was added in
`lanes/pandoc/tests/OdfReaderZipUnixOwnerProvenanceTest.php`.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderZipUnixOwnerProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderZipUnixOwnerProvenanceTest.php`
  passed with 1 file, 54 assertions, 0 failures.
- Adjacent ODF package gate passed:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderZipUnixOwnerProvenanceTest.php lanes/pandoc/tests/OdfReaderZipExtraFieldProvenanceTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
  with 6 files, 8,121 assertions, 0 failures.

No external Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, Node,
validators, or network services were invoked. Direct-format parity accounting
remains unchanged; this is metadata-only ODF/ODT package-ingestion coverage.
