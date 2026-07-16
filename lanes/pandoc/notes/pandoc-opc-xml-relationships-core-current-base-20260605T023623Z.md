# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T023623Z`

Base accepted HEAD: `e87de7e621bb93b4f91e1fe44fe7b8f611424339`

## Implementation

- Added bounded Pack URI normalization for `[Content_Types].xml` `Override`
  `PartName` values.
- `OpcContentTypes` now stores override part names as decoded logical package
  paths, so an override such as `/word/media/source%20diagram.svg` matches the
  decoded relationship target `/word/media/source diagram.svg`.
- Unsafe `PartName` encodings now fail early for malformed percent escapes,
  encoded `/`, encoded `\`, encoded NUL bytes, query strings, and fragments.
- `OpcContentTypes::toXml()` serializes override part names back to URI-safe
  Pack URI path form.
- Updated the WordPress DOCX OPC preflight example so an encoded SVG media
  relationship resolves to a decoded ZIP package part with its override MIME
  type.

## Source Truth

- Pandoc-style DOCX loading depends on OPC package semantics: relationships
  resolve Pack URI targets, and `[Content_Types].xml` maps logical part names
  to MIME types before DOCX readers import media or document parts.
- This slice keeps that behavior native PHP and bounded to content-types and
  relationship package semantics. It does not shell out to Pandoc, Word,
  LibreOffice, zip/unzip, or any external conversion service.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 343 assertions, 0 failures`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 361 assertions, 0 failures`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcPackagePath.php`
  - `php -l lanes/pandoc/src/OpcContentTypes.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Focused lane directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5856 assertions, 0 failures`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 28 to 29 PASS cases.
- Focused OPC assertions moved from 343 to 361, adding 18 assertions.
- Lane status moved from 545 to 546 PHP PASS cases.
- Manifest mapped checks moved from 1,023 to 1,024 with a new
  `mappedOpcContentTypePartUriCases` bucket.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`OpcPackagePath`, `OpcContentTypes`, `OpcRelationships`,
`OpcRelationshipGraph`, `ZipPackage`, and WordPress DOCX OPC preflight example.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
MIME grammar validation, relationship XML parsing, XML NCName Id validation,
relationship target percent-decoding, target integrity preflight, relationship
part source validation, external target policy, package-part preflight,
reachable relationship closure traversal, digital-signature relationship
preflight, and relationship Type URI policy diagnostics.

## Follow-Up

Keep full OPC Pack URI canonicalization policy for physical ZIP entry names,
embedded package relationship policy, encrypted package policy, external
relative-reference rewrite policy, cryptographic signature verification, and
higher-level DOCX UI treatment of OPC diagnostics as separate bounded slices.
