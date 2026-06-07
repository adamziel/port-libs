# OPC Relationships Preserve Declarations

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T233824Z`

Base accepted HEAD: `eb7d11e9bcd6594ca75065e9ce45b3589c10aa36`

## Behavior

This slice adds bounded OPC Markup Compatibility support for `mc:PreserveElements` and `mc:PreserveAttributes` on the package content-types and relationships XML roots.

The native PHP preflight now accepts preserve declarations only when every listed QName targets an extension namespace that is also declared in `mc:Ignorable`. It rejects malformed QNames, undeclared prefixes, non-ignorable prefixes, and references to core package namespaces. Wildcard preserve entries such as `review:*` are accepted for ignorable extension namespaces.

This preserves Office producer package XML that uses review/provenance extension markup while keeping core OPC content-type and relationship records strict.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 1461 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 1475 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case, `+14` assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`.
- PHP syntax checks passed for `OpcMarkupCompatibility.php`, `OpcContentTypes.php`, `OpcRelationships.php`, `OpenPackagingConventionsTest.php`, and `wordpress-docx-opc-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `OpcMarkupCompatibility`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, and the existing DOCX OPC preflight example. No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This is distinct from already accepted OPC work for content-type inventory, relationship target preflight, Pack URI part-name validation, relationship Id validation, relationship graph closure traversal, package-signature relationship-transform content-type queries, signature transform reference planning, external target policy, and relationship record-shape diagnostics.

## Follow-up

Full preservation/round-trip editing of ignored extension XML and cryptographic XMLDSig validation remain out of scope. A useful next OPC slice is bounded DOCX reader integration of the accepted relationship preflight results or package-signature reference selector edge cases.
