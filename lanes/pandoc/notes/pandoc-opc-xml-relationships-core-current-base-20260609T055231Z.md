# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T055231Z`
Base accepted HEAD: `dd0f2e4655041e19c48b43c11494e06b9ba1ff8d`

## Behavior

- Added `OpcRelationshipGraph::relationshipSourceClosureCoverageSummary()` as
  an importer-facing summary over the accepted source closure inventory.
- The summary groups expanded sources, outside sources, source depths, stop
  reasons, stop ids/targets by reason, invalid stop ids, missing target parts,
  relationship-part targets, unloaded target sources, external targets, and
  aggregate issues without changing raw closure traversal semantics.
- Added a non-DOCX spreadsheet-style OPC package fixture that exercises root
  office-document traversal, workbook/sheet source expansion, outside core
  properties relationships, missing targets, external targets, relationship
  part targets, unloaded target-source stops, and a workbook cycle.
- Updated the WordPress DOCX OPC preflight smoke to expose the new relationship
  closure coverage packet and assert its stable review buckets.

## Source Truth

- OPC conversion needs package-graph review over every relationship source,
  not just Word document packages. The accepted closure inventory already
  records source reachability and stop policy; this slice adds a bounded
  summary layer that document readers can consume without rewalking the graph.
- This slice preserves previously accepted target resolution, relationship
  source loading, package-part reference inventory, and reachable closure
  traversal behavior.
- This slice does not run Pandoc, Cabal, Haskell runners, Word, LibreOffice,
  zip/unzip, external template engines, browser renderers, TeX/PDF engines,
  online validators, or online services.

## Evidence

- Red-first focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 3410 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::relationshipSourceClosureCoverageSummary()`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3430 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Root harness not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 2403 to 2404 lane PASS cases.
- Focused OPC assertions moved from 3410 to 3430, adding 20 assertions.
- Manifest mapped checks moved from 2793 to 2794.
- `opcRelationshipClosureCases` and `mappedOpcRelationshipClosureCases` moved
  from 3 to 4; `opcRelationshipClosureAssertions` moved from 31 to 51.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`OpcRelationshipGraph`, `OpcRelationships`, `OpcContentTypes`, `ZipPackage`,
and WordPress DOCX OPC preflight example.

## Non-Overlap

This is additive on top of accepted package-part relationship coverage
summaries, package-wide role target policy inventory, source/singleton policy
summaries, relationship part load summaries, target preflight, relationship
transform review metadata, and reachable relationship closure traversal. It
does not change those raw inventories; it adds a source-closure coverage summary
for downstream readers and non-DOCX package review.

## Follow-Up

Wire the source closure coverage summary into DOCX/EPUB/ODF reader import
reports or add nested embedded package source-closure diagnostics as separate
bounded slices.
