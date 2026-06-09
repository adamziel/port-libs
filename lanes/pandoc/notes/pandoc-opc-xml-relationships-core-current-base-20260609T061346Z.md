# Pandoc OPC XML Relationships Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T061346Z`
Base accepted HEAD: `ad25c5c67f0859a34d555620436625e00d668451`

## Behavior

- Added `OpcRelationshipGraph::packageConsistencySummary()` as a compact importer-facing summary over existing OPC package consistency preflight rows.
- The summary preserves the raw `preflightPackageConsistency()` sections and aggregates validity flags, row counts, invalid package part names, invalid override part names, invalid relationship target keys, invalid relationship policy types, package-wide issue counts, and issue counts by section.
- Updated the WordPress DOCX OPC preflight example to expose the same summary in the full `packageConsistency` packet and the compact `wordpressImport.packageConsistencySummary` review packet.

## Source Truth

This is bounded OPC package semantics work for DOCX/EPUB/ODF-style package import review. It reuses existing native content-type, relationship target, relationship part, and known relationship type policy preflights rather than adding a new package walker.

## Evidence

- Baseline focused OPC test before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3459 assertions, 0 failures`.
- Red-first focused OPC test after adding the new assertion:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3459 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::packageConsistencySummary()`.
- Final focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3482 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

## Delta

- Focused PHP PASS cases: `2428 -> 2429`.
- Focused OPC assertions: `3459 -> 3482`, adding `23`.
- Manifest mapped checks: `2817 -> 2818`.
- OPC relationship graph support cases: `13 -> 14`.
- OPC relationship graph assertions: `210 -> 233`.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, focused OPC tests, and the WordPress DOCX OPC preflight example. Full XMLDSig cryptographic validation and upstream Pandoc/Haskell runner parity remain out of scope for this lane.

## Non-Overlap

This does not repeat accepted OPC content-type parsing, target integrity preflight, package part reference inventory, package part relationship coverage, relationship source closure coverage, relationship role target policy summaries, relationship part load summaries, signature relationship transform selector overlap, or reserved `_rels` directory diagnostics. It only adds a compact consistency summary over those existing preflight sections.

## Follow-Up

Wire package consistency summaries into DOCX/EPUB/ODF reader import reports, or choose a stricter non-overlapping signature reference provenance gap.
