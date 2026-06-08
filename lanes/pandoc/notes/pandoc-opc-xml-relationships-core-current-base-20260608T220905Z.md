# OPC Relationships Current-Base WordprocessingML Singleton Policy

Slice: `pandoc-opc-xml-relationships-core-current-base-20260608T220905Z`
Base accepted HEAD: `5ca5ed5c01549ddcb5727c8343ae1666cecfe98d`

## Behavior

`OpcRelationshipGraph::relationshipTypeInventory()` now classifies fixed WordprocessingML support relationship types as source-scoped singleton policy rows. Duplicate styles/settings relationships from the same source are surfaced as policy issues such as `multiple-styles-relationships-for-source`, while repeatable relationship types such as hyperlinks remain outside policy inventory.

The policy map covers the fixed support roles already used by the native DOCX/OPC preflight path: styles, numbering, footnotes, endnotes, comments, settings, theme, font-table, web-settings, custom XML properties, comments-extended, and glossary-document.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2664 assertions, 0 failures`.
- Red-first: the same command failed with `1 test files, 2665 assertions, 1 failures` because the styles relationship type had `knownRole === null`.
- Final: the same command passed with `1 test files, 2692 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses native PHP `OpcRelationshipGraph` relationship type inventory and lane-local OPC fixtures/tests. No Pandoc, Word, LibreOffice, `zip`/`unzip`, Cabal/Haskell runner, external XML validator, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat recent OPC slices for content-type inventory, Pack URI validation, markup compatibility alternate content, custom XML properties payloads, encrypted package relationships, embedded package relationship closure, thumbnails, or signature relationship-transform reference content-type checks.

## Follow-Up

A next non-overlapping OPC relationship slice could add source-content-type policy summaries for fixed WordprocessingML roles or DrawingML role inventory if importer diagnostics need those policy rows.
