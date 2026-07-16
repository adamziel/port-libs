# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T074728Z`
Base accepted HEAD: `04872dbb3131d5a034d1e365b9c27ae699e2563e`

## Behavior

- Added Pack URI source-name handling for OPC relationship part names.
- `OpcRelationships::relationshipPartNameForSource()` now percent-encodes
  source part path segments before inserting the `_rels` segment and appending
  `.rels`.
- `OpcRelationships::sourcePartNameForRelationshipPart()` now decodes percent
  escapes in the source part path, so a relationship part such as
  `/word/_rels/review%20source.xml.rels` loads under the decoded source part
  `/word/review source.xml`.
- ZIP-backed OPC graph loading, package-part preflight, and reachable closure
  traversal now preserve relationships for source parts with escaped spaces or
  UTF-8 bytes.
- The WordPress DOCX OPC preflight example now includes an encoded relationship
  part for `review source.xml` and proves its nested media relationship remains
  reachable for import review.

## Source Truth

- OPC part names are Pack URI paths. Relationship part names are derived from
  the source part URI by inserting `_rels` after the source directory and
  appending `.rels` to the encoded source part file name.
- This slice stays bounded to native PHP OPC content-types and relationships
  package semantics. It does not implement encrypted package policy, nested
  embedded package expansion, cryptographic signature verification, or full
  Markup Compatibility `ProcessContent` behavior.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, external converter, browser renderer, online
  sanitizer, or online service was executed.

## Evidence

- Red-first focused OPC check after adding tests:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 532 assertions, 2 failures`.
  - Failures: raw-space relationship part name output and missing encoded
    `.rels` lookup for the decoded source part.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 561 assertions, 0 failures`.
  - PASS lines: `40`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8848 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationships.php`
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

## Delta

- Focused OPC tests moved from 39 to 40 PASS cases.
- Focused OPC assertions moved from 540 to 561, adding 21 assertions.
- Lane status moved from `phpPass` 755 to 756.
- Manifest mapped checks moved from 1214 to 1215 with one additional mapped
  OPC relationship graph support case.

## Dependency Closure

No new support component is needed. This reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, MIME content
type grammar validation, Pack URI override normalization, relationship XML
namespace parsing, XML NCName Id validation, relationship target
percent-decoding, same-source target handling, target integrity preflight,
relationship-part source validation, external target scheme and rewrite
policy, package-part preflight, digital-signature relationship preflight,
embedded package/object relationship preflight, relationship Type URI
diagnostics, root office-document preflight, strict XML shape validation, OPC
Markup Compatibility ignorable extension handling, content-type-gated
relationship part loading, reachable relationship closure traversal, and
package-wide consistency preflight.

## Follow-Up

Keep encrypted package policy, nested embedded package graph expansion,
cryptographic signature verification, full Markup Compatibility
`ProcessContent`/`PreserveElements` handling, and higher-level DOCX UI
treatment of OPC diagnostics as separate bounded slices.
