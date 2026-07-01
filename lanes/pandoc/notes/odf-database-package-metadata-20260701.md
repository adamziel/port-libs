# ODF database package metadata

2026-07-01 plib-8wteg hardens rich OdfReader metadata for top-level
`Database/` package sidecars without exposing database payload bytes as
document media.

The focused regression covers the existing `packageDatabases` mirror through
document attributes, import reports, and `odfPackageDatabases` metadata, and
adds coverage for `.log`/text-like database sidecars plus media-report
exclusion. The report captures declared, undeclared, missing, encrypted, and
invalid-media-type database parts, plus database kind buckets, byte-exposure
policy, stored byte lengths, CRC metadata, and metadata-only review policy.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderPackageDatabaseMetadataTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageDatabaseMetadataTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageDatabaseMetadataTest.php lanes/pandoc/tests/OdfReaderEmbeddedDatabaseObjectPackageTest.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OdfReaderPackageScriptMetadataTest.php`
