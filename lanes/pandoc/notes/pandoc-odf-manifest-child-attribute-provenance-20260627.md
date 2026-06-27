# ODF Manifest Child Attribute Provenance

Slice: `plib-k58qf`, ODF/ODT OpenDocument package ingestion.

OpenDocumentPackage and OdfReader now preserve bounded attribute and namespace
provenance for direct `manifest:file-entry` child elements. The existing child
element review records still classify `manifest:encryption-data` as structural,
and custom extension children now carry their own attribute names, custom
attribute maps, namespace declaration maps, and identity inputs through compact
package review, rich reader package provenance, package inventory, and
metadata-only package identity hashes.

This remains native PHP package metadata handling. It does not invoke Pandoc,
office suites, zip/unzip, `ZipArchive`, external validators, network services,
or XML signature/encryption validators, and it does not expose blocked package
payload bytes.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OdfReaderManifestChildElementAttributeProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderManifestChildElementAttributeProvenanceTest.php`: 1 file, 20 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderManifestChildElementAttributeProvenanceTest.php`: 2 files, 1,912 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: the touched child-element provenance case passes; the file still has 22 pre-existing broad writer/format expectation failures unrelated to this package metadata slice.

Accounting:

- Focused ODF/ODT mapped case: `+1`
- Focused assertion evidence: `+20` standalone reader assertions, plus stronger
  compact-package child-element assertions in `OpenDocumentPackageTest.php`.
