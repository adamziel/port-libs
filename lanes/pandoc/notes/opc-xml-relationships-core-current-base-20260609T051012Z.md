# OPC XML Relationships Current-Base Slice 2026-06-09T051012Z

## Scope

Implemented a bounded native PHP OPC relationship role target policy inventory. `OpcRelationshipGraph::preflightRelationshipRoleTargets()` now aggregates existing per-role preflights for package-root office documents, document properties, digital signatures, encrypted packages, embedded packages/objects, thumbnails, and WordprocessingML document relationships into one package-wide importer review shape.

The summary reports role target counts, valid/invalid counts, role counts, issue counts, invalid row details, and a source-filtered variant for one relationship source. The WordPress DOCX OPC preflight example now exposes this policy summary under `wordpressImport.relationshipRoleTargetPolicy`.

## Focused Evidence

Baseline before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 3262 assertions, 0 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 3317 assertions, 0 failures
```

The focused test `summarizes package-wide OPC relationship role target policies for importer review` adds 55 assertions and one PHP PASS line over package-root roles, invalid encrypted package content type, WordprocessingML role target content types, internal hyperlink target policy, embedded package/object rows, and source-content-type mismatch on a non-document source.

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
```

passed locally after adding the role target policy summary checks.

## Non-Overlap

This slice avoids accepted OPC rows for content-type resolution provenance, relationship target integrity, reachable closure traversal, relationship transform metadata, signature transform reference guards, relationship type policy inventory, relationship source inventory, package part references, and relationship-part load summaries. It only adds a package-wide role target policy aggregator over existing native preflight methods.

## Dependency Closure

No new support component is needed. The slice reuses native PHP OPC package, content-type, relationship target, digital signature, encrypted package, embedded package, thumbnail, and WordprocessingML relationship role preflight code. Full upstream Pandoc runner parity remains a separate upstream-runner task requiring a hydrated Pandoc checkout and Haskell test executables.

## Next

A non-overlapping OPC follow-up could wire this role target inventory into DOCX reader import reports, add source/singleton policy summary rows for importer triage, or tighten package part relationship transform provenance.
