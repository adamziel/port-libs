# OPC ZIP Manifest Content-Type Byte Buckets

Hook: `plib-v30qt`, Pandoc shared ZIP/OPC package core blocker slice.

## Slice

`OpcRelationshipGraph::preflightZipEntryManifest()` now reports package-entry byte
buckets by resolved OPC content type and content-type source before XML graph
construction.

The new `byteCountsByContentType` map groups resolved package parts by MIME type,
while `byteCountsByContentTypeSource` separates default, override, missing, and
unavailable content-type provenance. `[Content_Types].xml` remains tracked by
role and handoff-kind buckets rather than being counted as a resolved package
part content type.

This keeps DOCX/EPUB/ODF importer review queues aware of byte exposure by
declared package semantics before package readers hand off bytes, without
invoking Pandoc, office suites, zip/unzip, ZipArchive, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed: 1 file, 4287 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed after final rebase onto
  `origin/main` 9ee7a923: 44 files, 73816 assertions, 0 failures.
