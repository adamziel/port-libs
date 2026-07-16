# Shared ZIP/OPC Entry Manifest Byte Buckets - 2026-06-10

## Scope

- Bead: `plib-crde`
- Area: shared ZIP/OPC package primitives
- Change: `OpcRelationshipGraph::preflightZipEntryManifest()` now reports compressed and uncompressed payload totals, file/directory byte separation, byte buckets by manifest role and handoff kind, and largest payload entry provenance before XML package handoff.
- Guardrail: no Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators, online services, live provider tests, or live-service provider tests were run.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 3834 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 60775 assertions, 0 failures`
