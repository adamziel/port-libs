# Pandoc OPC XML Relationships Current-Base Slice

Session: `port-dev-pandoc-opc-relationships-20260608T074653Z`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T074653Z`
Base accepted HEAD: `abd1af5843ccdf0a6730b63402c30abf96a3e9f7`

## Behavior

Added bounded native OPC relationship-role preflight for package digital signatures. `OpcRelationshipGraph::preflightDigitalSignatureRelationshipRoles()` now distinguishes package-root `digital-signature/origin` relationships from misplaced origin roles, records origin parts that may source `digital-signature/signature` relationships, and reports signature relationships that are sourced from ordinary package parts before DOCX import review.

The WordPress DOCX OPC preflight smoke now includes the digital-signature role summary and exposes role issues in the WordPress import diagnostics.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` notes existed for this lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2067 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2105 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.
- PHP lint passed for changed PHP files.
- JSON validation passed for lane status and manifest files.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

Focused delta: `+1` PHP PASS case and `+38` focused assertions in `OpenPackagingConventionsTest.php`.

## Non-Overlap

This does not repeat accepted OPC content-type inventory, Pack URI part-name validation, reserved `_rels` path validation, relationship target closure traversal, relationship id validation, or package-signature relationship-transform content-type query preflight. The slice only covers package-level digital-signature relationship role placement.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, and WordPress DOCX OPC preflight example paths. Full upstream runner parity still requires a hydrated pinned Pandoc checkout plus an explicitly reviewed non-mutating Cabal plan; no Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was executed.

## Next

Continue OPC XML relationships with a non-overlapping package-semantics gap such as relationship mode policy, signature-object manifest coverage, or package content-type/relationship cross-checks.
