# pandoc-opc-xml-relationships-core-current-base-20260608T131300Z

Date: 2026-06-08 UTC
Base accepted HEAD: d6ec1fb5ef671b6ea22e454e765ca0d7b78582a5
Lane: pandoc

## Scope

Implemented one bounded OPC relationship package-semantics slice: package-wide
consistency now includes known relationship-type role policy checks from the
native OPC relationship inventory. This catches structurally valid relationship
targets whose role placement/cardinality is invalid, such as duplicate package
officeDocument relationships, core-properties relationships from part sources,
and multiple thumbnail relationships from the same source.

This is additive to the accepted relationship type inventory slice: that slice
reported role policy metadata only; this slice makes those policy rows affect
`OpcRelationshipGraph::preflightPackageConsistency()`.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` handoff note existed for this
  lane before work started.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2187 assertions, 0 failures`.
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  failed with `1 test files, 2188 assertions, 1 failures` before the graph fix.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2208 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed.

## Status Delta

- Added one mapped native OPC package-consistency case.
- Focused OPC assertion count moved from `2187` to `2208` (`+21`).
- `lane-status.json` `phpPass` moved from `1652` to `1653`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moved from `2072` to `2073`.
- OPC relationship graph support cases moved from `13` to `14`; mapped support
  cases moved from `13` to `14`; graph support assertions moved from `210` to
  `231`.

## Dependency Closure

No new native PHP support component is needed. The implementation reuses
`ZipPackage` fixtures, `OpcRelationshipGraph::relationshipTypeInventory()`,
existing OPC relationship role policy definitions, the WordPress OPC preflight
example, and the native TestRunner. No Pandoc, Cabal solver/build/test command,
Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML
tool, online service, live provider test, or live-service provider test was
executed.

## Next

A non-overlapping OPC follow-up could validate role-to-content-type consistency
inside package consistency or push these package policy summaries into specific
DOCX/EPUB/ODF reader handoffs. Keep external converters and online validators
out of scope for this lane.
