# OPC XML Relationships Current Base - Alternative Format Import Policy

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T045414Z`  
Base accepted HEAD: `e3e201377d66d62da0039dedbb153200e0a6e366`

## Behavior

- `OpcRelationshipGraph` now classifies the WordprocessingML
  alternative-format import relationship type
  (`http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk`)
  as a known relationship type policy role named
  `alternative-format-import`.
- The policy remains intentionally unscoped: `sourceScope` is `any-source`,
  `singletonScope` is `null`, and multiple `aFChunk` relationships do not
  become a package policy violation. Content-type/source validation remains in
  the existing WordprocessingML relationship role preflight.
- `wordpress-docx-opc-preflight.php` now exposes
  `wordpressImport.alternativeFormatImportPolicy` for import review packets.

## Focused Evidence

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`  
  Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`  
  Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`  
  Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`  
  Result: `1 test files, 3236 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`  
  Result: `opc docx preflight self-test ok`.

## Mapping Delta

- Adds 1 PHP PASS case.
- Adds 25 focused OPC relationship graph assertions.
- Increments `benchmarkDenominator.mapped` to `2728`.
- Increments `mappedOpcRelationshipGraphSupportCases` to `14`.
- Increments `opcRelationshipGraphAssertions` to `235`.

## Non-Overlap

This slice does not repeat the recent OPC work on external target percent
preflight, relationship serialization guards, relationship part load summary,
office-document readiness, custom XML properties, encrypted packages, embedded
package graph closure, digital signature relationship transforms, or signature
object/selector policy. It only fills the package-wide relationship type policy
classification gap for `aFChunk`.

## Dependency Closure

No new support component is required. The patch reuses native PHP
`OpcRelationshipGraph` policy inventory, existing WordprocessingML relationship
role definitions, `ZipPackage` fixtures, the focused PHP test runner, and the
lane-local WordPress DOCX OPC preflight example. Full upstream Pandoc runner
parity remains a separate upstream-runner dependency task requiring hydrated
pinned upstream sources and Haskell test executables.
