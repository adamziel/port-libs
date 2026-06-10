# Pandoc Shared ZIP/OPC Package Consistency Current Base

Date: 2026-06-10 UTC

Slice: `pandoc-shared-zip-opc-package-consistency-current-base-20260610T1955Z`

## Scope

Added compact relationship-part load accounting to
`OpcRelationshipGraph::packageConsistencySummary()` for importer gates. The
package-wide consistency packet now includes:

- relationship part validity;
- loaded, skipped, and invalid relationship part counts;
- invalid relationship part names;
- relationship part load-reason buckets;
- relationship part issue buckets and issue names.

The detailed `relationshipPartLoadSummary()` API is unchanged.

## Direct Parity Accounting

No direct reader or writer parity status changed. This remains a native PHP
shared ZIP/OPC package diagnostic slice for DOCX/EPUB/ODF-style package
handoff. It adds package-consistency review metadata without registering a new
converter, shelling out to Pandoc, or using external archive/package tools.

Focused assertion delta in `OpenPackagingConventionsTest.php`: `+2`.
Focused PASS-case count is unchanged because this extends an existing package
consistency test.

## Evidence

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3870 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebase on `origin/main`: `44 test files, 61101 assertions,
    0 failures`.

## Dependency Closure

No new support component is needed. This reuses `ZipPackage`,
`OpcRelationshipGraph::preflightPackageConsistency()`, and
`OpcRelationshipGraph::relationshipPartLoadSummary()`. Pandoc, Cabal/Haskell
runners, Word, LibreOffice, zip/unzip, browser renderers, external validators,
online services, live provider tests, and live-service provider tests were not
executed.

## Non-Overlap

This does not repeat relationship-part content-type provenance, malformed
relationship XML preflight, duplicate relationship source detection,
relationship target traversal, signed relationship transforms, digital
signature digest policy, package byte-bucket summaries, or ZIP archive
integrity checks. The new behavior is only the compact consistency summary
surface that carries existing relationship-part load diagnostics into importer
gate packets.
