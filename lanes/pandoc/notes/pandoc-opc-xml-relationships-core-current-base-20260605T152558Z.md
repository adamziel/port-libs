# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T152558Z`
Base accepted HEAD: `1bad6e3dbf9e8a6855582232164ffeb31f9e1f02`

## Behavior Added

- `OpcRelationshipGraph` now builds a package part-name equivalence map after the existing duplicate-equivalence preflight rejects ambiguous packages.
- Internal relationship targets returned by root lookups, target summaries, target preflight, reachable closure traversal, selector materialization, and relationship-source lookup now resolve to the stored package part when relationship casing differs.
- Relationship part, package part, and content-type override preflights use the same ASCII case-insensitive source/part existence checks.
- `wordpress-docx-opc-preflight.php` now exposes a valid case-equivalent DOCX fixture where lowercase relationship targets resolve to stored `Word/Document.XML` and `Word/Styles.XML` package parts.

## Source Truth

OPC part-name equivalence is ASCII case-insensitive, and packages must not contain multiple equivalent part names. This slice ports the valid single-equivalent lookup path; it preserves the existing duplicate-equivalent package-part rejection.

## Verification

- Baseline focused: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 847 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` failed with `1 test files, 848 assertions, 1 failures` before implementation because `firstTargetOfType()` returned `/word/document.xml` instead of `/Word/Document.XML`.
- Green focused: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 863 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/OpcRelationshipGraph.php`, `lanes/pandoc/tests/OpenPackagingConventionsTest.php`, and `lanes/pandoc/examples/wordpress-docx-opc-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused OPC coverage: `52 -> 53` PASS cases.
- Focused OPC assertions: `847 -> 863`.
- Lane `phpPass`: `970 -> 971`.
- Manifest mapped inventory: `1425 -> 1426`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `OpcRelationshipGraph`, `OpcRelationships`, `OpcContentTypes`, `ZipPackage`, `OpcPackagePath`, and WordPress DOCX OPC preflight example paths.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external converter, or online service was executed.

## Non-Overlap

This does not repeat content-types parsing, relationship Id validation, target percent decoding, external target policy, package-part consistency, digital-signature relationship transforms, signature content-type query preflight, `mc:ProcessContent`, or the existing case-equivalent collision guard. It adds the valid single-equivalent relationship target/source resolution path.

## Follow-Up

Keep cryptographic signature validation, full XML canonicalization and digest checks, Markup Compatibility `PreserveElements`/`PreserveAttributes`, encrypted package policy, and higher-level DOCX UI treatment as separate bounded slices.
