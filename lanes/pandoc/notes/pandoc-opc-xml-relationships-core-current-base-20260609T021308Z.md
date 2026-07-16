# pandoc-opc-xml-relationships-core-current-base-20260609T021308Z

## Scope

Implemented one bounded OPC XML digital-signature relationship support slice on accepted base `ae05f994f04ccc78db62e7bd6dd42669f76246b1`.

`OpcRelationshipGraph::preflightDigitalSignatureSignedInfoReferences()` now treats `SignedInfo` `Reference URI="#..."` values as XMLDSig same-document references. The returned rows expose:

- `sameDocumentReference`
- `sameDocumentFragment`
- `sameDocumentTargetMatched`
- `sameDocumentTargetMatchCount`
- `sameDocumentTargetMatchedElementNames`

Unique `#Object` and `#Manifest` targets are accepted. Empty, missing, and duplicate-ID fragments are explicit diagnostics through `invalid-signed-info-same-document-reference`, `unmatched-signed-info-same-document-reference`, and `ambiguous-signed-info-same-document-reference`.

The WordPress DOCX OPC preflight example now includes a signed `#manifestPackageParts` same-document reference and carries the same metadata into its `wordpressImport.digitalSignatureSignedInfoReferences` review summary.

## Evidence

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `No syntax errors detected in lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 2982 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - `opc docx preflight self-test ok`

Focused test delta: +1 PASS case and +39 assertions over the previous OPC focused evidence (`2943` to `2982` assertions).

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` updated `2124 -> 2125`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: mapped denominator updated `2551 -> 2552`; `mappedOpcRelationshipGraphSupportCases` updated `13 -> 14`; `opcRelationshipGraphAssertions` updated `210 -> 249`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP XML parsing, OPC relationship graph, and same-document Id indexing already used by signature object policy checks. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`/`unzip`, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted OPC content-type inventory, relationship ID/target preflight, relationship transform selector parsing, relationship transform payload fingerprinting, RelationshipReference/RelationshipsGroupReference policy, reference `ContentType` query validation, object/manifest reference package-part digest checks, digital-signature role source policies, or encrypted/embedded package policies.

Next useful follow-up: thread the new same-document SignedInfo diagnostics into the higher-level DOCX import report so reviewers can see missing or ambiguous signature XML fragments alongside package-part manifest checks.
