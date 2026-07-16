# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T041020Z`
Base accepted HEAD: `7817f8048965628c77e25703024cc64f52dc65bb`

## Behavior

- Added strict record-shape validation for OPC `[Content_Types].xml`
  `Default` and `Override` records.
- Added strict record-shape validation for OPC `.rels` `Relationship` records.
- These records now reject unexpected non-namespace attributes, child elements,
  and non-whitespace text/CDATA while preserving whitespace-only bodies.
- Updated the WordPress DOCX OPC preflight example so its self-test proves the
  strict XML guard before import relationship traversal.

## Source Truth

- OPC content type records are fixed-attribute XML records:
  `Default` uses `Extension` and `ContentType`; `Override` uses `PartName` and
  `ContentType`.
- OPC relationship records are fixed-attribute XML records: `Relationship` uses
  `Id`, `Type`, `Target`, and optional `TargetMode`.
- This slice keeps the behavior bounded to native PHP package XML semantics. It
  does not run Pandoc, Cabal, Word, LibreOffice, zip/unzip, external template
  engines, browser renderers, TeX/PDF engines, online sanitizers, or online
  services.

## Evidence

- Red-first focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 428 assertions, 2 failures`.
  - Failure: the content-types and relationships parsers accepted unexpected
    attributes or child content.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 433 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php | rg -c '^PASS '`
  - Result: `33`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcContentTypes.php`
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

- Focused OPC tests moved from 31 to 33 PASS cases.
- Focused OPC assertions moved from 424 to 433, adding 9 assertions.
- Lane status moved from `phpPass` 607 to 609.
- Manifest mapped checks moved from 1,081 to 1,083 with a new
  `mappedOpcStrictXmlRecordShapeCases` bucket.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and `ZipPackage` primitives.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
MIME grammar validation, Pack URI override normalization, relationship XML
namespace parsing, XML NCName Id validation, relationship target
percent-decoding, target integrity preflight, relationship-part source
validation, external target policy, package-part preflight, digital-signature
relationship preflight, embedded package/object relationship preflight,
relationship Type URI policy diagnostics, root office-document preflight, and
reachable relationship closure traversal.

## Follow-Up

Keep markup-compatibility extension policy, external relative-reference rewrite
policy, encrypted package policy, nested embedded package graph expansion,
cryptographic signature verification, and higher-level DOCX UI treatment of
strict XML diagnostics as separate bounded slices.
