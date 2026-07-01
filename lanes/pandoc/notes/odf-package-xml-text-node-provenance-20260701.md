# ODF Package XML Text-Node Provenance

Bead: `plib-0bcsw`

The ODF/ODT package ingestion path now records metadata-only XML text-node provenance in both compact `OpenDocumentPackage::summarize()` package inventory and rich `OdfReader` package provenance.

Recorded fields include per-part and package-level text-node counts, byte lengths, whitespace/non-whitespace buckets, line-break totals, parent-depth buckets, bounded parent paths, CRC32, and SHA-256. Raw XML text values are not exposed in package review metadata.

Direct-format parity accounting was added to `UPSTREAM_TEST_MANIFEST.json`:

- `mappedOdfPackageXmlTextNodeProvenanceCases`: 1
- `odfPackageXmlTextNodeProvenanceAssertions`: 82

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageXmlTextNodeProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageXmlTextNodeProvenanceTest.php` passed with 1 file, 82 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageXmlTextNodeProvenanceTest.php lanes/pandoc/tests/OdfPackageXmlCdataSectionProvenanceTest.php lanes/pandoc/tests/OdfPackageXmlCommentProvenanceTest.php lanes/pandoc/tests/OdfPackageXmlProcessingInstructionProvenanceTest.php lanes/pandoc/tests/OdfPackageXmlRootElementProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` passed with 7 files, 7,928 assertions, 0 failures.

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
