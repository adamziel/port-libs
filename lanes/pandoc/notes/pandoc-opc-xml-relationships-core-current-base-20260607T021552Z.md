# Pandoc OPC XML Relationships Core Current Base 20260607T021552Z

## Scope

Implemented a bounded OPC package-semantics guard for the fixed `[Content_Types].xml`
package item when it appears as a relationship source.

OPC relationship sidecars such as `/_rels/[Content_Types].xml.rels` now stay visible
in package preflight output, but they are marked invalid and skipped with the
`content-types-item-source` diagnostic instead of being loaded into the relationship
graph.

## Source Truth

- OPC reserves `[Content_Types].xml` as the package content-type item rather than a
  normal package part.
- Relationship graph loading should walk package-root and normal part relationship
  sources only.
- This slice is intentionally separate from the already accepted Pack URI validation,
  relationship target preflight, relationship Id validation, reachable closure,
  content-type inventory, package-signature content-type query, transform TargetMode,
  and Markup Compatibility preserve-declaration slices.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 1502 assertions, 0 failures`.
- Red-first probe: adding the fixed content-types item source case failed with
  `1 test files, 1503 assertions, 1 failures` because
  `OpcRelationships::relationshipPartNameForSource('/[Content_Types].xml')` did not
  throw.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 1533 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `ZipPackage`,
`OpcRelationships`, `OpcRelationshipGraph`, the existing focused OPC test harness,
and the WordPress DOCX OPC preflight example.

No Pandoc, Cabal, Haskell runner, Word, LibreOffice, `zip`/`unzip`, XMLDSig
validator, external XML tool, online service, live provider test, or live-service
provider test was executed.

## Next

Keep OPC follow-up bounded to non-overlapping content-types, relationships, Markup
Compatibility, digital-signature, or DOCX handoff gaps. Do not broaden this into
external Office/Pandoc runner execution from this lane.
