# Pandoc OPC XML Relationships Current-Base Slice

Session: `port-dev-pandoc-opc-relationships-20260609T035525Z`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260609T035525Z`
Base accepted HEAD: `4cca1c57da8720c140326c22572dbfb45205f318`

## Implementation

This slice extends the native OPC relationship-type inventory:

- `OpcRelationshipGraph::relationshipTypeInventory()` now reports DrawingML
  chart and diagram data/layout/quick-style/colors relationship types as known
  roles through the shared relationship-type policy inventory.
- These DrawingML roles intentionally remain `any-source` relationships with
  no singleton scope. A DOCX source can have multiple chart relationships
  without tripping generic OPC policy diagnostics.
- `preflightPackageConsistency()` now includes those DrawingML rows in
  `relationshipTypePolicies`, allowing importer review packets to identify
  chart and diagram relationship classes without relying on the narrower DOCX
  document relationship role preflight.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 3093 assertions, 0 failures`.
- Red-first: the new DrawingML inventory test failed before implementation with
  `Expected: 'chart'` / `Actual: NULL` and
  `1 test files, 3095 assertions, 1 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 3147 assertions, 0 failures`.
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  reported no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  reported no syntax errors.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.
- `jq empty lanes/pandoc/lane-status.json` passed.
- `git diff --check -- lanes/pandoc` passed.

The focused OPC test file gained 1 PHP PASS case and 54 focused assertions.
Root harness was not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
focused OPC tests, and WordPress DOCX OPC preflight smoke. No Pandoc,
Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap / Follow-Up

This does not repeat accepted OPC slices for content-type parsing,
relationship target preflight, external-target policy, relationship transform
selectors, signature manifest/SignedInfo references, XMLDSig role
authorization, embedded package traversal, source closure traversal, or
WordprocessingML fixed support relationship singleton rules. A follow-up should
choose a separate OPC relationship gap such as transform digest validation
metadata, source-closure import policy, or higher-level DOCX handoff of the
existing OPC diagnostics.
