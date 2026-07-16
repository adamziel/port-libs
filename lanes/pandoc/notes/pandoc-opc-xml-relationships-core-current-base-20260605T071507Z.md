# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T071507Z`
Base accepted HEAD: `a99415aeed2feb39fb6e42a4ec05fd4b05a42134`

## Behavior

- Added package-wide OPC consistency preflight on `OpcRelationshipGraph`.
- New `preflightContentTypeOverrides()` reports content-type override
  declarations whose target parts are missing, and it carries relationship-part
  source metadata for overrides that point at `.rels` parts.
- New `preflightAllRelationshipTargets()` enumerates every loaded relationship
  source, including package-root relationships such as core properties and
  digital signature origin references that are not part of an office-document
  reachable-only walk.
- New `preflightPackageConsistency()` combines package-part, content-type
  override, and all-loaded-relationship target validity for WordPress DOCX
  review packets.
- The WordPress DOCX OPC preflight example now exposes this consistency audit
  and proves stale content-type overrides do not become phantom media imports.

## Source Truth

- Pandoc DOCX import relies on OPC package roots, `[Content_Types].xml`, and
  relationship parts to locate the office document, media, metadata,
  signatures, and related package resources.
- This slice stays bounded to native PHP OPC package semantics. It does not
  implement cryptographic signature verification, encrypted package policy,
  nested embedded package expansion, DOCX body conversion, or upstream Haskell
  runner parity.
- The local upstream Pandoc checkout/Cabal project files remain unavailable in
  this isolated worktree/cache, so no Pandoc, Cabal solver/build/test command,
  Haskell runner, Word, LibreOffice, zip/unzip, external converter, browser
  renderer, online sanitizer, or online service was executed.

## Evidence

- Baseline focused OPC test before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 510 assertions, 0 failures`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 540 assertions, 0 failures`.
  - PASS lines: `39`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8539 assertions, 0 failures`.
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

## Delta

- Focused OPC tests moved from 38 to 39 PASS cases.
- Focused OPC assertions moved from 510 to 540, adding 30 assertions.
- Lane status moved from `phpPass` 737 to 738.
- Manifest mapped checks moved from 1,196 to 1,197 with
  `mappedOpcPackageConsistencyCases`.

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
relationship part loading, and reachable relationship closure traversal.

## Follow-Up

Keep encrypted package policy, nested embedded package graph expansion,
cryptographic signature verification, and higher-level DOCX UI treatment of
package-consistency diagnostics as separate bounded slices.
