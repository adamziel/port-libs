# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T092425Z`

Base accepted HEAD: `8c0c51d2ebad141388ff9b3a063a701d0dede248`

## Behavior Added

- Added bounded native OPC relationship transform payload materialization on
  `OpcRelationshipGraph`.
- `materializeRelationshipTransform()` reuses accepted SourceId/SourceType
  selector validation, preserves raw relationship `Target` values from the
  `.rels` XML, filters by matching relationship `Id` or `Type`, sorts selected
  relationship records by `Id`, and emits explicit `TargetMode` attributes.
- The returned audit record exposes selector validity, selected target
  validity, selected relationship ids, transform algorithm URI, relationship
  part name, selected review rows, and canonicalization-ready relationship XML.
- The WordPress DOCX OPC preflight example now exposes the relationship
  transform payload for selected media, embedded package, and reviewer-link
  relationships without running signature or office tooling.

## Source Truth

- OPC relationship transforms filter a relationships part by `SourceId` and
  `SourceType` selectors before XML canonicalization:
  https://c-rex.net/samples/ooxml/e1/Part2/OOXML_P2_Open_Packaging_Conventions_RelationshipsGroupRe_topic_ID0E6UGK.html
- The relationship transform preparation step keeps matching relationships,
  sorts relationship elements by `Id`, and makes the default `TargetMode`
  explicit before downstream canonicalization. This slice ports that bounded
  relationship XML payload step only.
- This does not parse full XML Signature parts, run canonical XML byte parity,
  validate cryptographic signatures, expand embedded packages, or implement
  encrypted package policy.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, `zip`, `unzip`, XML signature engine, external converter,
  browser renderer, online sanitizer, or online service was executed.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 606 assertions, 0 failures`.
- Red-first focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 606 assertions, 1 failure`.
  - Failure: `Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::materializeRelationshipTransform()`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 630 assertions, 0 failures`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9714 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 42 to 43 PASS cases.
- Focused OPC assertions moved from 606 to 630, adding 24 assertions.
- Lane `phpPass` moved from `803` to `804`.
- Manifest mapped native checks moved from `1262` to `1263`.
- Added `mappedOpcRelationshipTransformCases = 1` and
  `opcRelationshipTransformAssertions = 24`.

## Dependency Closure

No new support component is needed. This reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, MIME
content-type validation, Pack URI override and relationship part
normalization, relationship XML namespace/shape parsing, XML NCName Id
validation, same-source target handling, target integrity preflight,
relationship-part source validation, external target policy, package-part and
package-consistency preflight, digital-signature origin/signature preflight,
embedded package/object preflight, relationship Type URI diagnostics, root
office-document preflight, markup-compatibility extension policy, reachable
relationship closure traversal, and SourceId/SourceType selector preflight.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep full XML Signature transform parsing from signature parts, XML C14N
byte-for-byte verification, cryptographic signature validation, encrypted
package policy, nested embedded-package graph expansion, and higher-level DOCX
UI treatment of relationship-transform diagnostics as separate bounded slices.
