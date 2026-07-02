# ODF manifest namespace declaration URI rollup

Hook: `plib-tjxkt`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

ODF/ODT package ingestion now carries metadata-only namespace declaration URI
rollups for manifest root/file-entry scopes through compact
`OpenDocumentPackage` manifest review and package identity, plus rich
`OdfReader` package provenance, package identity, and document manifest
identity. The rollup records scope counts, unique namespace URIs, URI occurrence
counts, declaration names by URI, per-URI summaries, and per-scope maps without
exposing package payload bytes.

Direct-format parity remains active in `UPSTREAM_TEST_MANIFEST.json`; this slice
adds one mapped ODF package-ingestion case with 32 focused assertions and does
not invoke Pandoc, office suites, zip/unzip, TeX/PDF engines, browsers, Node, or
external validators.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfManifestNamespaceDeclarationUriRollupTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestNamespaceDeclarationUriRollupTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestNamespaceDeclarationUriRollupTest.php lanes/pandoc/tests/OdfManifestMediaTypeSummaryCompactParityTest.php lanes/pandoc/tests/OdfManifestPackageCoverageProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Odf*.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
