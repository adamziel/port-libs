# Shared ZIP/OPC Raw Central Directory Manifest Slice

Date: 2026-06-11
Bead: plib-56l44

This slice adds an OPC package-part manifest preflight that reads directly from the ZIP central directory before local-header validation or `ZipPackage` construction succeeds. It is intended for DOCX/EPUB/ODF review queues that still need part-role, relationship-source, orphan-relationship, byte-bucket, and central-directory provenance when strict ZIP instantiation is blocked by a local-header mismatch.

Implementation:
- Added `OpcRelationshipGraph::preflightZipCentralDirectoryManifest(string $bytes)`.
- Reused `ZipPackage::centralDirectorySizePreflight()` rather than adding another ZIP parser path.
- Preserved OPC role and handoff buckets for content-types, relationships, XML parts, media, embedded-package candidates, directories, invalid parts, and blocked entries.
- Kept central-directory validity separate from OPC manifest validity so ZIP-level issues and OPC graph issues remain independently reviewable.

Verification:
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed: 1 test file, 4095 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 63996 assertions, 0 failures.

Accounting:
- Adds 1 mapped OPC raw central-directory manifest case.
- Adds 37 focused assertions.
- Moves Pandoc lane `phpPass` from 3072 to 3073 on current main `51a89684e`.
- Moves mapped denominator from 3194 to 3195.
