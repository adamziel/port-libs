# ODF Central Directory Source Record Provenance

Slice: `plib-sizgp`

## Behavior

- Compact `OpenDocumentPackage` package inventory now carries central-directory source-record offsets, byte spans, end offsets, and SHA-256 hashes per ZIP package part.
- Rich `OdfReader` package provenance carries the same metadata-only source-record fields in `packageProvenance.parts`.
- ODF package identity payloads include the central-directory source-record fields so metadata-only identity changes when central directory records change.
- The new provenance stays metadata-only: package payload bytes are not exposed, and existing byte-exposure policies remain unchanged.

## Evidence

- PHP lint passed for `OpenDocumentPackage.php`, `OdfReader.php`, `OpenDocumentPackageTest.php`, and `OdfReaderPackageIdentityTest.php`.
- Focused gate: `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php lanes/pandoc/tests/OdfManifestSidecarOrderFlagsTest.php lanes/pandoc/tests/OdfReaderDocumentPartRootAttributesTest.php lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php` -> `7 test files, 2153 assertions, 0 failures`.
- No Pandoc, office suite, TeX engine, browser, unzip/zip command, Node, external validator, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice is limited to ODF/ODT ZIP central-directory source-record provenance in compact and rich package-ingestion metadata. It does not change ODT content parsing, style rendering, writer output, ZIP parsing rules, OPC/DOCX/EPUB package readers, or byte exposure for package payloads.
