# ODF Package XML Comment Provenance - 2026-07-01

Scope: ODF/ODT package ingestion only.

`OpenDocumentPackage` and `OdfReader` now carry metadata-only XML comment
provenance for XML package parts through compact package inventory and rich
reader package provenance. The review packet records per-part and aggregate
comment counts, byte lengths, parent paths/depths, CRC32, and SHA-256 while
omitting raw comment text and preserving existing XML processing-instruction and
CDATA sidecar metadata.

Focused validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageXmlCommentProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageXmlCommentProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageXmlCommentProvenanceTest.php lanes/pandoc/tests/OdfPackageXmlProcessingInstructionProvenanceTest.php lanes/pandoc/tests/OdfPackageXmlCdataSectionProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

No Pandoc executable, office suite, zip/unzip CLI, browser renderer, Node
tooling, external validator, or live service is required for this slice.
