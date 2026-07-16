# OPC XML Relationships Current-Base Serialization Guards

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T042022Z`
Base accepted HEAD: `57f750cb0f2a8072346fa230252307d0b08d42b0`

## Behavior

- Tightened `OpcRelationships::toXml()` so programmatically built internal relationship targets must round-trip through the native OPC internal target resolver after path-byte URI escaping.
- Preserved existing writer behavior that percent-escapes raw internal path bytes such as spaces and UTF-8 names before serialization.
- Rejected non-round-trippable internal targets before XML emission, including encoded slash/backslash/NUL path bytes, percent-encoded dot segments, trailing-dot path segments, package-root traversal, URI authority targets, and package-root fragment/query-only targets.
- Extended the WordPress DOCX OPC preflight smoke with `relationshipSerializationGuard.internalTargetRejections` so importer review packets expose this write-time policy without external validators.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3147 assertions, 0 failures`
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3156 assertions, 0 failures`
  - Delta: `+1` focused PHP PASS line and `+9` focused assertions.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `OpcRelationships`, `OpcPackagePath::resolveInternalTarget()`, focused OPC package tests, and the existing WordPress DOCX OPC preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted OPC content-type inventory, relationship target preflight, external target percent diagnostics, relationship-part load summaries, relationship-transform selector/content-type/digest metadata, package signature policy, embedded package policy, encrypted package policy, or reachable closure traversal. It only owns XML serialization rejection for internal relationship targets that the existing native resolver cannot parse back into valid OPC package targets.

## Follow-Up

- A separate writer-policy slice could decide whether external relationship serialization should reject raw URI bytes or unsafe schemes instead of only surfacing them through preflight.
- Keep future OPC relationship slices away from this internal serialization guard unless they add a distinct canonicalization or external-target writer policy.
