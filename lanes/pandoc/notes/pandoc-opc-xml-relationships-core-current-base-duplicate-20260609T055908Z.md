## pandoc-opc-xml-relationships-core-current-base-duplicate-20260609T055908Z

Accepted base: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`

Behavior added:
- `OpcRelationshipGraph::preflightRelationshipSelector()` now reports
  `selectorOverlappingRelationshipIds` and `selectorOverlapCount` when a
  RelationshipTransform selector chooses the same relationship by both
  `SourceId` and `SourceType`.
- `materializeRelationshipTransform()` and
  `preflightSignatureRelationshipTransforms()` carry the same overlap
  provenance while preserving OPC RelationshipTransform union semantics: each
  selected relationship is serialized once in the materialized relationship
  XML.
- Added a WordPress DOCX OPC smoke for signature review packets that need to
  flag selector overlap without treating it as a duplicate output relationship.

Focused evidence:
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 3439 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS line and `+29` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-relationship-transform-overlap.php --self-test`
  passed with `wordpress-docx-opc-relationship-transform-overlap self-test passed`.
- Syntax checks passed for changed PHP files.

Dependency closure:
- No new native PHP support component is needed.
- This slice reuses `OpcRelationshipGraph`, `OpcRelationships`,
  `OpcContentTypes`, `ZipPackage`, focused OPC tests, and lane-local WordPress
  OPC examples.
- Full Pandoc upstream runner parity remains a separate upstream-runner
  dependency task.

Non-overlap:
- This does not repeat accepted OPC content-type parsing, duplicate
  relationship source preflight, duplicate selector element rejection,
  relationship target integrity, reachable closure traversal, relationship
  type policy inventory, role target policy summaries, package part coverage
  summaries, external target serialization guards, or PDF/DOCX reader
  conversion handoffs.
- The new behavior is limited to selector-overlap provenance for OPC
  relationship transforms and unique materialized relationship XML.

Exclusions:
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, `zip`/`unzip`, `ZipArchive`, XMLDSig validator, external XML
  tool, external converter, TeX/PDF engine, browser renderer, online service,
  live provider test, or live-service provider test was run.
- Root harness not run - isolated micro-slice.

Next:
- A non-overlapping OPC follow-up could wire accepted OPC diagnostics into DOCX
  reader import reports or add stricter signature reference provenance.
