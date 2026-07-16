# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T191108Z`
Base accepted HEAD: `1fee675cfc053b65d6824b32dd8851f66511d8c2`

## Behavior

- `OpcRelationshipGraph::preflightTargetsForSource()` now keeps the accepted
  `invalid-target` sentinel and appends bounded reason codes for invalid
  internal relationship targets.
- Internal relationship targets that look like absolute URIs, network-path
  references, package-root traversal, malformed percent escapes, or unsafe
  percent-encoded slash/backslash/NUL path bytes remain non-traversable, but
  WordPress import review packets can now show the exact reason.
- Reachable relationship closure inherits the same detailed issue list and
  still refuses to traverse invalid targets.
- The WordPress DOCX OPC preflight smoke now includes a tiny isolated package
  fixture proving bad internal targets surface the same diagnostics without
  destabilizing the main review packet.

## Source Truth

OPC internal relationships identify package parts through URI-reference path
targets resolved relative to the relationship source part. External targets are
expressed with `TargetMode="External"`. This slice stays bounded to package
preflight: invalid internal targets are reported as graph diagnostics instead of
being treated as external resources or package parts.

This does not implement XML Signature digest validation, canonicalization,
encrypted package policy, remote-resource policy, DOCX body parsing, or Pandoc
runner parity.

## Verification

- Baseline focused OPC run before adding the expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 915 assertions, 0 failures`.
- Red-first focused OPC run after adding the internal-target diagnostics
  expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 914 assertions, 3 failures`.
  - Failure: invalid internal targets only reported `invalid-target`.
- Focused OPC run after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 925 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from `56` to `57` PASS cases.
- Focused OPC assertions moved from `915` to `925`, adding `10` assertions.
- Lane `phpPass` moved from `1045` to `1046`.
- Manifest mapped native inventory moved from `1498` to `1499`.
- OPC target preflight counters moved from `6` to `7` cases and from `29` to
  `39` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcContentTypes`, `OpcRelationships`, `OpcPackagePath`,
`OpcRelationshipGraph`, the WordPress DOCX OPC preflight example, and the
focused PHP test harness.

No Pandoc, Word, LibreOffice, zip/unzip, ZipArchive, XMLDSig validator, Cabal
solver/build/test command, Haskell runner, external XML tool, online sanitizer,
or online service was executed.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
MIME grammar validation, Pack URI override normalization, relationship XML
namespace parsing, XML NCName Id validation, target percent-decoding, target
integrity preflight, relationship-part source validation, external target
policy and rewrite diagnostics, relationship Type URI policy diagnostics,
root office-document preflight, relationship transform materialization,
digital-signature relationship-transform selector/content-type query
preflight, content-type inventory grouping, source-equivalent package
relationship loading, and reachable closure traversal.

## Follow-Up

Keep encrypted package policy, cryptographic XML Signature C14N/digest
validation, relationship target allow/deny UI policy, embedded package graph
expansion, and higher-level DOCX UI treatment of relationship diagnostics as
separate bounded slices. Full Pandoc runner parity remains gated on hydrating
the upstream Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and
building the Haskell Tasty runner closure.
