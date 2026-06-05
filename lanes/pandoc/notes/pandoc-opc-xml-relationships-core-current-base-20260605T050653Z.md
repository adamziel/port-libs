# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T050653Z`
Base accepted HEAD: `bd28920b7f3ed02f501965b633a3e53666fd2f67`

## Behavior

- Added bounded OPC Markup Compatibility handling for package metadata XML.
- `OpcMarkupCompatibility` now resolves root-level `mc:Ignorable` prefixes to
  declared extension namespaces while rejecting undeclared prefixes and core
  OPC/MC namespaces.
- `OpcContentTypes` and `OpcRelationships` now ignore only extension
  attributes/elements whose namespace is declared ignorable on the package XML
  root.
- Undeclared extension attributes/elements and unsupported MC attributes such
  as `mc:ProcessContent` / `mc:PreserveElements` remain strict parse errors.
- The WordPress DOCX OPC preflight example now carries declared reviewer
  extension metadata and exposes guards for accepted ignorable metadata plus
  rejected undeclared/unsupported extension markup.

## Source Truth

- OPC package XML can carry markup-compatibility extension markup, but native
  DOCX/OPC import must not silently accept undeclared producer metadata before
  relationship traversal.
- This slice is bounded to content-types and relationships XML package
  semantics. It does not implement full MC processing, DOCX body parsing,
  cryptographic signature verification, or nested embedded-package expansion.
- No Pandoc, Cabal solver/build/test command, Haskell runner, texmath,
  citeproc, BibTeX, Biber, Word, LibreOffice, office tooling, `zip`, `unzip`,
  external template engine, TeX/PDF engine, browser renderer, Typst, online
  sanitizer, or online service was executed.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 443 assertions, 0 failures`.
- Red-first focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 443 assertions, 1 failures`.
  - Failure: `mc:Ignorable` on the content-types root was rejected as an
    unsupported attribute.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 460 assertions, 0 failures`.
  - PASS lines: `35`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Changed PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcMarkupCompatibility.php`
  - `php -l lanes/pandoc/src/OpcContentTypes.php`
  - `php -l lanes/pandoc/src/OpcRelationships.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7332 assertions, 0 failures`.

Root harness not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 34 to 35 PASS cases.
- Focused OPC assertions moved from 443 to 460, adding 17 assertions.
- Lane status moved from `phpPass` 640 to 641.
- Manifest mapped checks moved from 1115 to 1116 with
  `mappedOpcMarkupCompatibilityIgnorableCases`.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives, with a small lane-local
`OpcMarkupCompatibility` helper.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
MIME grammar validation, Pack URI override normalization, relationship XML
namespace parsing, XML NCName Id validation, relationship target
percent-decoding, target integrity preflight, relationship-part source
validation, external target policy, package-part preflight, digital-signature
relationship preflight, embedded package/object relationship preflight,
relationship Type URI policy diagnostics, root office-document preflight,
strict child-record/root shape validation, and reachable relationship closure
traversal.

## Follow-Up

Keep full MC `ProcessContent`, `PreserveElements`, and `PreserveAttributes`
semantics, external relative-reference rewrite policy, encrypted package
policy, nested embedded package graph expansion, cryptographic signature
verification, and higher-level DOCX UI treatment of OPC diagnostics as
separate bounded slices.
