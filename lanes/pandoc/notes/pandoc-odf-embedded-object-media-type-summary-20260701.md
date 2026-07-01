# ODF/ODT Embedded Object Media Type Summary - 2026-07-01

Slice: `plib-4plme`, ODF/ODT OpenDocument package ingestion core blocker.

`OpenDocumentPackage` and `OdfReader` now summarize manifest media-type
provenance for contained parts inside embedded OpenDocument object packages.
Embedded object package bytes remain blocked, while metadata-only review
payloads expose bucketed media-type accounting for declared and undeclared
contained parts.

The `packageObjects` / `embeddedObjectPackages` review packets include:

- per-contained-part manifest media type, media-type base, parameter metadata,
  manifest index, source full path, encryption flag, byte-exposure policy, and
  diagnostics for manifest-declared contained parts;
- per-object and package-level `containedMediaTypeSummary` buckets with counts
  for media-type bases, contained parts, declared parts, undeclared parts,
  missing media-type parts, byte lengths, compressed byte lengths, and sorted
  part names;
- per-object `issueCount` and package-level `issueCodeCounts` for the embedded
  object review packet, aligned with adjacent package metadata summaries;
- `OdfReader` metadata handoff through `metadata.odfPackageEmbeddedObjects`;
- the existing contained role and media-family summaries remain intact.

Focused validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  passed with 1 file, 2,306 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with 1 file, 5,342 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderEmbeddedDatabaseObjectPackageTest.php`
  passed with 1 file, 44 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  passed with 1 file, 59 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentReaderTest.php`
  passed with 1 file, 91 assertions, 0 failures.
- `git diff --check origin/main..HEAD`

No Pandoc binary, office suite, TeX/browser engine, Node tooling, `zip`/`unzip`
command, external validator, online service, live provider, or embedded object
package byte exposure was invoked.
