# pandoc-opc-xml-relationships-core-current-base-20260608T183726Z

## Behavior

TargetMode="External" OPC relationship targets that use a network-path URI reference, for example `//cdn.example.test/review/source.html`, are no longer treated as fully absolute safe external links by relationship graph preflight. `OpcRelationship::externalTargetPreflight()` still classifies them as `network-path-reference` with no unsafe scheme, but `OpcRelationshipGraph` now requires an explicit base scheme policy before import review can treat them as resolved external sources.

The package preflight row now carries:

- `externalTargetRequiresBaseUri: true`
- `externalTargetRewriteBasePart: null`
- `externalTargetRewriteReason: external-target-network-path-reference`
- `issues: [external-target-network-path-base-uri]`

## Evidence

Baseline focused command before edits:

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- Result: `1 test files, 2319 assertions, 0 failures`

Focused verification after edits:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- Result: `1 test files, 2330 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
- Result: `opc docx preflight self-test ok`

## Dependency Closure

No new native PHP support component is needed. This slice reuses the existing `OpcRelationship`, `OpcRelationshipGraph`, `OpcRelationships`, `OpcContentTypes`, and `ZipPackage` support-library paths and their in-memory package fixtures.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This is distinct from the accepted package-root external relative/fragment target base-URI slice and the Pack URI part-name validation slice. It covers scheme-relative external targets (`//host/path`) specifically, not relative references, same-document fragments, content-type overrides, internal targets, or signature Reference URI path guards.

## Next

A follow-up OPC relationships slice can target a non-overlapping relationship type policy gap, additional package-signature relationship transform diagnostics, or content-type inventory handoff behavior.
