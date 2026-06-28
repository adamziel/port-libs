# Pandoc ODF Signature Certificate Package Sidecars - 2026-06-28

Area: Pandoc ODF/ODT OpenDocument package ingestion

## Summary

- ODF/ODT package ingestion now classifies `META-INF/certificates/*` package members as metadata-only signature certificate sidecars.
- Certificate blobs are excluded from document media and generic `META-INF` sidecar buckets while preserving declared, missing, encrypted, invalid media-type, and undeclared review issues.
- Rich `OdfReader` XML signature parsing remains limited to `*signatures.xml`; certificate blobs are not parsed as XML signatures.
- Compact `OpenDocumentPackage` and rich `OdfReader` package-signature summaries now expose certificate-specific `kind` and expected media-type metadata.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderSignatureCertificatePackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderSignatureCertificatePackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderSignatureCertificatePackageTest.php lanes/pandoc/tests/OdfReaderSignatureKeyInfoProvenanceTest.php lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php lanes/pandoc/tests/OdfReaderMetaInfSidecarTest.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`

Focused adjacent validation passed 6 files, 2,175 assertions, 0 failures.

No external signing tools, certificate validators, office suites, Pandoc runner, `zip`/`unzip`, browser engines, online services, or live provider tests were invoked.
