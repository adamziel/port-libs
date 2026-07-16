# ODF package identity manifest aggregates

Implemented for `plib-ec2i5`.

- `OdfReader` now stores the reader manifest media-type summary inside
  package provenance and carries that summary through the metadata-only
  package identity payload returned in both import reports and document
  manifest attributes.
- Rich package identities now expose the same manifest media-type count,
  parameter, empty-media-type, and preferred-view-mode aggregate handoff
  fields as identity metadata, alongside the existing per-manifest-entry
  values.
- `OpenDocumentPackage` compact package identities now carry the dedicated
  manifest preferred-view-mode summary in addition to the existing compact
  manifest media-type summary.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfManifestMediaTypeSummaryCompactParityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestMediaTypeSummaryCompactParityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfManifestMediaTypeSummaryCompactParityTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfManifestEncryptionIdentityParityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`

The slice does not expose package bytes and did not invoke external Pandoc,
office, TeX, browser, ZIP, Jupyter, or live validation tools.
