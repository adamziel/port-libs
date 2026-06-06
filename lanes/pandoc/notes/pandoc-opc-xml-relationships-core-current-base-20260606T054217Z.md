# OPC Relationship Orphan Load Decisions

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T054217Z`
Base: `e4ea169e4e976809e607e8fc8164a335a8929b16`

## Behavior

- `OpcRelationshipGraph::fromPackage()` no longer loads non-root relationship parts as graph sources when their owning source part is missing from the package.
- `OpcRelationshipGraph::preflightRelationshipPartsInPackage()` now exposes `loadAction` and `loadReason` for each relationship part.
- `OpcRelationshipGraph::preflightPackageParts()` now carries `relationshipPartLoadAction` and `relationshipPartLoadReason` for relationship package parts.
- The WordPress DOCX OPC preflight example surfaces those load-decision fields in its review packet.

This keeps orphan `.rels` parts review-visible for package diagnostics while preventing their outbound relationships from becoming reachable conversion graph edges.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1074 assertions, 0 failures`.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1107 assertions, 0 failures`.
- Syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php` -> no syntax errors.
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php` -> no syntax errors.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` -> clean.

## Status Delta

- `phpPass`: `1218` -> `1219`.
- Manifest mapped checks: `1662` -> `1663`.
- OPC relationship graph support cases: `11` -> `12`.
- Focused assertion delta: `+33`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, `OpcPackagePath`, `XmlHtmlDom`, and lane-local manifest/status machinery.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, or live provider test was executed.

## Non-Overlap

This does not repeat prior OPC relationship content-type inventory, package-signature `ContentType` query preflight, case-equivalent path lookup, NCName relationship Id validation, target preflight, closure traversal, markup-compatibility processing, relationship type inventory, or relationship-transform reference URI guards.

Follow-up remains separate: XML canonicalization, digest/signature validation, encrypted package policy, broader relationship-transform parity, and deeper DOCX/EPUB/ODF reader integration.
