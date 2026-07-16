# OPC XML Relationships Current-Base Slice 2026-06-09T052746Z

## Scope

Implemented a bounded native PHP OPC relationship role policy summary for importer reports. `OpcRelationshipGraph::relationshipRolePolicySummary()` now derives package-wide or source-filtered known-role rows from the accepted relationship type policy inventory and reports package-scoped roles, source-scoped singleton roles, unscoped roles, singleton counts, valid/invalid policy counts, issue buckets, target parts, and content types.

The WordPress DOCX OPC preflight example now exposes the compact importer view under `wordpressImport.relationshipRolePolicySummary` while keeping the full role rows available at top-level `relationshipRolePolicySummary`.

## Focused Evidence

Red-first check before the production method:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
FAIL summarizes OPC known relationship role policy buckets for importer reports
Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::relationshipRolePolicySummary()
1 test files, 3317 assertions, 1 failures
```

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 3374 assertions, 0 failures
```

The focused test `summarizes OPC known relationship role policy buckets for importer reports` adds 57 assertions and one PHP PASS line over package-wide and source-filtered role policy summaries, including duplicate package-root officeDocument policy issues, source-scoped styles singleton issues, unscoped altChunk/chart roles, target part lists, and content type buckets.

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
opc docx preflight self-test ok
```

## Non-Overlap

This slice avoids accepted OPC rows for content-type resolution provenance, target integrity preflight, reachable closure traversal, relationship transform metadata, relationship-part load summaries, relationship type policy inventory, relationship source inventory, package part references, and the package-wide role target policy inventory from `20260609T051012Z`. It only adds an importer-facing source/singleton policy summary over existing known relationship role policy rows.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, OPC relationship graph parsing, content-type resolution, and the existing relationship type policy definitions for package-root singletons, source singletons, unscoped WordprocessingML imports, and DrawingML roles. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, `zip`/`unzip`, `tar`, `gzip`, `lz4`, external converter, external validator, online service, live provider test, or live-service provider test was run.

## Next

A non-overlapping OPC follow-up could wire the accepted role-target and role-policy summaries into DOCX reader import reports, add stricter package part relationship transform provenance, or expand source-closure diagnostics for importer triage.
