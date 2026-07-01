# ODF/ODT Embedded Object Media Type Summary - 2026-07-01

Slice: `plib-4plme`, ODF/ODT OpenDocument package ingestion core blocker.

`OpenDocumentPackage` and `OdfReader` now summarize media-type provenance for
contained parts inside embedded OpenDocument object packages. The package
review payload keeps embedded object bytes blocked while exposing metadata-only
media-type buckets for declared and undeclared contained parts.

The `packageObjects` / `embeddedObjectPackages` review packets now include:

- per-contained-part manifest media type, media type base, parameter metadata,
  manifest index, source full path, encryption flag, byte-exposure policy, and
  diagnostics when declared in `META-INF/manifest.xml`;
- `containedMediaTypeSummary` at each embedded object root and package aggregate
  level, with counts for buckets, parts, declared parts, undeclared parts,
  missing media-type parts, byte lengths, and sorted parts by media type base;
- rich `OdfReader` document metadata handoff through
  `metadata.odfPackageEmbeddedObjects`;
- the existing contained role and media-family summaries remain intact.

Focused validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with 1 file, 2,301 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with 1 file, 5,337 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderEmbeddedDatabaseObjectPackageTest.php`
  passed with 1 file, 44 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  passed with 1 file, 59 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentReaderTest.php`
  passed with 1 file, 91 assertions, 0 failures.

No Pandoc binary, office suite, TeX/browser engine, Node tooling, zip/unzip
command, external validator, online service, live provider, or package byte
exposure was invoked.
