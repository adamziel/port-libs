# Pandoc OPC XML Relationships Current-Base External Serialization Guard

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T043141Z`

Base accepted HEAD: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`

## Behavior

- `OpcRelationships::toXml()` now reuses the native external target preflight before serializing `TargetMode="External"` relationship records.
- Safe absolute and relative external URI references still serialize and round-trip through `.rels` XML.
- Unsafe external targets are rejected before XML emission for raw URI whitespace, malformed percent escapes, percent-encoded control bytes, and unsafe schemes such as `javascript:`.
- The WordPress DOCX OPC preflight example now includes `relationshipSerializationGuard.externalTargetRejections` so import review packets can surface write-time external-link policy without external validators.

## Evidence

- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3205 assertions, 0 failures`
- Red-first probe after adding expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3208 assertions, 1 failures`
  - Failure: raw-space external target serialized instead of being rejected.
- Final focused verification:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3211 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- Focused delta:
  - `phpPass`: `2305 -> 2306`
  - `benchmarkDenominator.mapped`: `2705 -> 2706`
  - `mappedOpcRelationshipSerializationCases`: `2`
  - `opcRelationshipSerializationAssertions`: `15`
  - New focused assertions: `+6`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `OpcRelationship::externalTargetPreflight()`, `OpcRelationships::toXml()`, focused OPC package tests, and the existing WordPress DOCX OPC preflight example. Full upstream Pandoc runner parity remains a separate upstream-runner dependency and was not attempted.

## Non-Overlap

This does not repeat accepted OPC content-type parsing, relationship target preflight, external target percent diagnostics, relationship-part load summaries, relationship-transform selector/content-type/digest metadata, package signature policy, embedded/encrypted package policy, reachable closure traversal, or internal relationship serialization guards. It only owns write-time external TargetMode rejection for unsafe `.rels` XML targets.

## Exclusions

No Pandoc, Cabal solver/build/test command, Haskell runner, tar, gzip, lz4, zip/unzip, ZipArchive, Word, LibreOffice, XMLDSig validator, TeX/PDF engine, Typst, browser renderer, external converter, external validator, online service, live provider test, or live-service provider test was run.

## Next

Next OPC relationship work should target a non-overlapping package-semantics gap such as higher-level DOCX consumption of existing OPC diagnostics, source-closure import policy, or a distinct XMLDSig reference-policy edge.
