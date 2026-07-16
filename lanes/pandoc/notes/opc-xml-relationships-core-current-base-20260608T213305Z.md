# OPC XML Relationships Current-Base Encrypted Package Policy

Slice: `pandoc-opc-xml-relationships-core-current-base-20260608T213305Z`
Base accepted HEAD: `17b111d85a0bb4b5cb849a471da21f0b1ab9bf09`

## Source Truth

- Bounded OPC package semantics: the package relationship type `http://schemas.openxmlformats.org/package/2006/relationships/encrypted-package` identifies an encrypted package payload and is package-root scoped.
- Existing lane support reused `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph::preflightTargetsForSource()`, package consistency summaries, and the WordPress DOCX OPC preflight example.
- No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was run.

## Implementation Delta

- Added `OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE` and native encrypted package content-type policy.
- Added `OpcRelationshipGraph::preflightEncryptedPackages()` to report source, target, expected content type, source-scope, external-target, existence, and inherited target/type diagnostics.
- Registered the encrypted-package relationship type as a known package-root singleton policy in relationship type inventory and package consistency output.
- Added one focused OPC test covering a valid root encrypted package, an invalid external encrypted package target, an invalid nested encrypted package source/content type, filtered-source preflight, relationship type inventory, and package consistency policy issues.
- Extended the WordPress DOCX OPC preflight example with a valid `/EncryptedPackage` part and reviewer-visible `wordpressImport.encryptedPackages` summary.

## Verification Evidence

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2633 assertions, 0 failures`.
- Red-first after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` failed with `1 test files, 2633 assertions, 1 failures` at missing `OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2664 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/OpcRelationshipGraph.php && php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php && php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php` reported no syntax errors.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` passed with `pandoc json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed with no output.

## Dependency Closure

No new support component is needed. This slice reuses the bounded native OPC XML/package graph primitives already in the lane. Full upstream Pandoc/OpenXML runner parity remains outside this isolated support-library slice.

## Non-Overlap

This does not repeat TargetMode casing diagnostics, Pack URI part-name validation, custom XML properties payload metadata, signature relationship-transform content-type queries, digital signature object metadata, embedded package graph traversal, or nested relationship payload-segment guards. A useful next OPC slice would consume encrypted package metadata in DOCX reader handoff flows or extend signature digest policy checks without repeating this relationship role policy.
