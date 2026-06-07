# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260607T141531Z`

Base accepted HEAD: `9fa2532d1407cdfcf7979d602b49aba1b4031366`

## Behavior Added

- Tightened native `[Content_Types].xml` Default extension parsing so simple
  extension names reject ASCII whitespace and control bytes.
- Kept the programmatic leading-dot convenience path: `addDefault('.xml', ...)`
  still serializes as `Extension="xml"`.
- Preserved case-retaining default extension storage and case-insensitive
  extension fallback, including mixed-case defaults such as `Jpeg`.
- Extended the WordPress DOCX OPC preflight example so strict XML-shape review
  packets expose the whitespace-bearing Default extension guard.

## Source Truth

- OPC Default records carry extension names, not package paths or dotted
  filesystem suffixes. This slice keeps the existing delimiter and leading-dot
  guards and closes the remaining raw whitespace/control-byte gap for the
  native PHP content-types parser.
- Pandoc DOCX/OpenXML conversion depends on OPC content-type defaults before
  part readers decide how package entries are interpreted. This stays in
  bounded PHP package semantics and does not claim Haskell runner parity.

## Red Check

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  after adding only the focused regression:
  - Result: failed as expected with `1 test files, 1685 assertions,
    1 failures`.
  - Failure: `Expected exception InvalidArgumentException was not thrown` for
    the new whitespace-bearing Default extension case.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 1691 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Additional syntax, JSON, and diff checks are recorded in the final handoff for
this worker.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 80 to 81 PASS cases.
- Focused OPC assertions moved from 1681 to 1691, adding 10 assertions.
- Lane `phpPass` moved from `1512` to `1513`.
- Manifest `benchmarkDenominator.mapped` moved from `1932` to `1933`.
- Manifest `mappedOpcXmlRelationshipContentTypeCases` moved from `11` to `12`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`OpcContentTypes`, existing package-path-aware lookup behavior, the focused OPC
test harness, and the WordPress DOCX OPC preflight example.

This slice did not invoke Pandoc, Cabal, Haskell runners, Word, LibreOffice,
`zip`, `unzip`, external office/XML tools, external template engines, TeX/PDF
engines, browser renderers, online sanitizers, online services, live provider
tests, or live-service provider tests.

## Non-Overlap

This does not repeat the accepted leading-dot Default extension guard,
Override PartName Pack URI validation, content-type MIME validation,
relationship XML shape validation, TargetMode diagnostics, raw whitespace
relationship target diagnostics, relationship part source/load decisions,
content-type inventory grouping, package-signature `ContentType` query
preflight, relationship closure traversal, or digital-signature package
preflight. It owns only Default extension raw whitespace/control-byte grammar.

## Follow-Up

A useful next OPC slice is DOCX reader integration of the accepted preflight
summaries or a bounded package-signature selector edge case that is not already
covered by the relationship-transform inventory.
