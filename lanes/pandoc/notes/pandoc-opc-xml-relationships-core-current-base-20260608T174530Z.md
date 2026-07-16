## pandoc-opc-xml-relationships-core-current-base-20260608T174530Z

Accepted base: `9965dd418ac9194ca9784a6dc4cecce9c13d164f`

Behavior added:
- `OpcRelationshipGraph::preflightDigitalSignatureSignedInfoReferences()` now reports `canonicalizationTransformAlgorithms` for XMLDSig `ds:SignedInfo/ds:Reference` transforms.
- SignedInfo relationship-part references now report `relationshipTransformFollowedByCanonicalization` as `true`, `false`, or `null` when no RelationshipTransform exists.
- OPC relationship-part references whose RelationshipTransform is not immediately followed by an XML canonicalization transform are flagged with `signed-info-relationship-transform-not-followed-by-canonicalization`.
- The WordPress DOCX OPC preflight example now includes this canonicalization metadata in the compact `wordpressImport.digitalSignatureSignedInfoReferences` review projection.

Focused evidence:
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2319 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2294 assertions, 1 failures` while `canonicalizationTransformAlgorithms` was absent.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2331 assertions, 0 failures`.
- Delta: `+12` focused assertions inside an existing PHP PASS case; `phpPass` remains unchanged.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`.

Dependency closure:
- No new native PHP support component is needed.
- This slice reuses `XmlHtmlDom`, `ZipPackage`, `OpcPackagePath`, `OpcContentTypes`, `OpcRelationships`, and existing OPC XML signature transform helpers.
- Full XMLDSig cryptographic verification and canonicalized digest recomputation remain out of scope.

Exclusions:
- Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online services, live provider tests, or live-service provider tests.
- Root harness not run - isolated micro-slice.

Next non-overlapping OPC follow-up:
- Signature transform canonicalization payload normalization, content-type override collision policy, or embedded package relationship closure.
