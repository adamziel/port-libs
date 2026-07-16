# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260607T011417Z`
Base accepted HEAD: `05080f39db5ee2c2bd812547f2fb1754cdd82f98`
Lane: `pandoc`

## Behavior

- Added `OpcRelationshipGraph::preflightEmbeddedPackageGraphs()` for bounded
  nested OPC package inspection behind `officeDocument` embedded-package
  relationships.
- Valid internal embedded package targets are read from the parent package and
  parsed through the existing native `ZipPackage` and `OpcRelationshipGraph`
  primitives.
- The preflight reports nested package part count, relationship source names,
  and the nested office-document root relationship.
- External embedded package targets are reported as
  `external-embedded-package-not-expanded`; malformed embedded ZIP payloads are
  reported as `embedded-package-parse-error`; missing targets retain the
  accepted `missing-in-package` issue.
- The WordPress DOCX OPC preflight example now exposes
  `embeddedPackageGraphs` and `wordpressImport.nestedEmbeddedOfficeDocuments`
  for embedded workbook review packets.

## Evidence

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 1502 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `jq empty lanes/pandoc/lane-status.json && jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - Result: passed.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1425 -> 1426`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1841 -> 1842`.
- `mappedOpcRelationshipGraphSupportCases`: `13 -> 14`.
- `opcRelationshipGraphAssertions`: `210 -> 251`.
- Focused OPC test coverage added one PASS case and 41 assertions.

## Non-Overlap

This slice does not repeat accepted OPC content-type parsing, Pack URI
part-name validation, relationship Id validation, target integrity preflight,
external target policy, package-signature metadata, relationship-transform
selector/materialization handling, content-type inventory, reachable closure
traversal, or basic embedded package/object relationship content-type policy.
It adds the nested embedded-package graph expansion policy only.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`,
`OpcRelationships`, `OpcRelationshipGraph`, the lane-local focused PHP harness,
and the existing WordPress DOCX OPC preflight example. Pandoc, Cabal
solver/build/test commands, Haskell runners, Word, LibreOffice, `zip`,
`unzip`, `ZipArchive`, XMLDSig validators, external XML tools, online
services, live provider tests, and live-service provider tests were not run.

## Follow-Up

Keep encrypted-package policy, higher-level DOCX import-report integration for
nested embedded package graphs, relationship-transform digest/canonicalization
diagnostics, and cryptographic XML signature validation as separate bounded
slices.
