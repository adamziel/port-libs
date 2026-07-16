# OPC Relationships Current-Base Package-Root External Targets

- Session: `port-dev-pandoc-opc-relationships-20260608T103458Z`
- Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T103458Z`
- Base accepted HEAD: `1931c96c286e44f278624dd3e62f6ff3b6cb363b`

## Behavior

This slice tightens native OPC relationship target preflight for package-root
relationship parts. External relative and fragment targets in `_rels/.rels`
still report that a base URI is required, but `OpcRelationshipGraph` no longer
reports `/` as an implicit source-part rewrite base. Those package-root targets
now carry `external-target-package-root-base-uri`, remain allowed external
targets, and are marked invalid until the caller supplies a package-level base
URI policy.

The WordPress DOCX OPC preflight example now exposes this guard for package-root
external hyperlink relationships so import review packets do not silently treat
the package root as a document part.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2111 assertions, 0 failures`.
- Red-first: the same focused test failed after adding the package-root external-target fixture with `1 test files, 2116 assertions, 1 failures` because `externalTargetRewriteBasePart` was `/` instead of `null`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2129 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.

Focused delta: `+1` PHP PASS case and `+18` focused assertions. Manifest
mapped denominator moves `2035 -> 2036`; lane `phpPass` moves `1616 -> 1617`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
ZIP package, OPC content-types, OPC relationships, and relationship graph
preflight components. No Pandoc, Word, LibreOffice, zip/unzip, external XML
tools, online services, live provider tests, or live-service provider tests
were executed.

## Non-Overlap

This owns only package-root external relative/fragment target base-URI
preflight. It intentionally does not change content-type inventory, Pack URI
part-name validation, signature relationship transforms, relationship Type URI
policy, internal target closure traversal, or part-level external target
rewrite context.

## Follow-Up

Next OPC work should target a non-overlapping package semantics gap such as
relationship type role policy, relationship target closure use in DOCX media
handoff, or additional content-type/relationship consistency diagnostics.
