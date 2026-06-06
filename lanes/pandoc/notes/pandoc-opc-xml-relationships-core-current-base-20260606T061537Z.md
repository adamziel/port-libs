# Pandoc OPC Relationship TargetMode Preflight

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T061537Z`
Base: `98d37dedec48e231d559abd333dd1d6b05575268`

## Behavior

`OpcRelationshipGraph::preflightRelationshipPartsInPackage()` now keeps strict relationship parsing intact while classifying one more load-skip reason for import review. A well-formed `.rels` part whose `Relationship` has an unsupported `TargetMode` value or casing, such as `TargetMode="external"`, still fails strict `OpcRelationships::fromXml()` parsing and remains unloaded with `loadReason = malformed-relationship-xml`, but the preflight row also carries `invalid-relationship-target-mode`.

This gives DOCX/OPC import preflight a specific, inspectable issue without accepting non-OPC casing or weakening `OpcRelationship`.

## Evidence

- Baseline focused test before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 1107 assertions, 0 failures`
- Red-first focused run after adding the expected TargetMode diagnostic:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Failed on missing `invalid-relationship-target-mode` after `1093` assertions.
- Green focused run after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 1115 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - `opc docx preflight self-test ok`

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, `OpcRelationshipGraph`, `OpcRelationships`, `OpcRelationship`, and `XmlHtmlDom`. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, or live provider test was executed.

## Non-Overlap

This does not overlap recent OPC relationship work for content-type inventory grouping, relationship-transform reference ContentType queries, role content-type matching, relationship target traversal, target URI policy preflight, NCName relationship id validation, relationship-part source/orphan load decisions, signature relationship transforms, or package closure traversal. It is limited to a preflight diagnostic for strict `TargetMode` casing/value failures.

## Follow-Up

Keep broader relationship-target policy expansion, XML signature transform edge cases, package consistency inventories, and hydrated upstream Haskell runner comparison as separate bounded slices.
