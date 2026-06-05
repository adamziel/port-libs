# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T081948Z`

Base accepted HEAD: `a239ddec09b5ff50a0521621343442a4b44bc058`

## Behavior Added

- Tightened `[Content_Types].xml` XML parsing for OPC content-type records.
- XML `Default Extension` records now reject leading-dot extensions such as
  `.xml`; the programmatic builder still accepts `.xml` and serializes it as
  `xml` for generated package metadata.
- XML `Override PartName` records now require an absolute package URI beginning
  with `/` and reject empty path segments, literal dot segments, decoded dot
  segments, and trailing slashes before content-type preflight trusts the part.
- Extended the WordPress DOCX OPC preflight example so import review packets
  expose these strict content-type record guards.

## Source Truth

- OPC content-type `Override` records identify package parts with absolute
  part-name URIs, while `Default` records carry simple extension names rather
  than dotted filesystem suffixes:
  https://c-rex.net/samples/ooxml/e1/Part2/OOXML_P2_Open_Packaging_Conventions_Content_topic_ID0EJPAG.html
- Pandoc DOCX loading depends on OPC package discovery: package relationships
  locate the office-document part and content types decide how package parts
  are interpreted. This slice stays in native PHP package semantics and does
  not claim upstream Haskell runner parity.

## Red Check

- `php -r 'require "tools/bootstrap.php"; ... OpcContentTypes::fromXml(...) ...'`
  - Result before implementation: `missing-slash accepted` and
    `dot-extension accepted`.

## Verification

- `php -l lanes/pandoc/src/OpcContentTypes.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 570 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9135 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 40 to 41 PASS cases.
- Focused OPC assertions moved from 561 to 570, adding 9 assertions.
- Lane `phpPass` moved from `770` to `771`.
- Manifest mapped native checks moved from `1229` to `1230`.
- Manifest `mappedOpcXmlRelationshipContentTypeCases` moved from `11` to `12`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`OpcContentTypes`, `OpcPackagePath`, `ZipPackage`, and DOCX OPC preflight
example paths.

This slice did not invoke Pandoc, Cabal, Haskell runners, Word, LibreOffice,
`zip`, `unzip`, external office tools, external template engines, TeX/PDF
engines, browser renderers, online sanitizers, or online services.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
lookup, MIME content-type validation, relationship XML parsing, relationship
Id validation, target preflight, package consistency preflight, external target
policy, digital signature and embedded package preflight, and reachable
relationship closure traversal.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep relationship selector policy, digital signature relationship transform
semantics, embedded package/OLE higher-level import policy, and DocxReader UI
treatment of package-consistency diagnostics as separate bounded slices.
