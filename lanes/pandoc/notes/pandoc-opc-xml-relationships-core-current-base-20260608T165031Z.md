## pandoc-opc-xml-relationships-core-current-base-20260608T165031Z

Accepted base: `63e2debc141738e27afa8820a6493fd1cbe7d79e`

Behavior added:
- Added `OpcRelationshipGraph::preflightDigitalSignatureSignedInfoReferences()` for bounded OPC XML Signature `ds:SignedInfo/ds:Reference` inventory.
- Each SignedInfo reference now reports resolved target part, package existence, content type, relationship-part classification, `ContentType` query match state, transform algorithm list, relationship-transform count, canonicalization transform count, digest method, digest value base64 length, decoded digest bytes, validity, and issues.
- Relationship part references without the OPC RelationshipTransform are flagged, and RelationshipTransform usage against ordinary package parts is flagged.
- The WordPress DOCX OPC preflight example now exposes this digest/transform metadata in both full signature metadata and the compact `wordpressImport` review projection.

Focused evidence:
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2272 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2319 assertions, 0 failures`.
- Delta: `+1` focused PHP PASS case and `+47` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`.

Dependency closure:
- No new native PHP support component is needed.
- This slice reuses `OpcPackagePath` target resolution, `OpcRelationships` relationship-part semantics, `OpcContentTypes` lookup/matching, `XmlHtmlDom` parsing, and existing OPC signature transform helpers.
- Full XMLDSig canonicalization/signature validation remains out of scope.

Exclusions:
- Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online services, live provider tests, or live-service provider tests.
- Root harness not run - isolated micro-slice.

Next non-overlapping OPC follow-up:
- Signature transform canonicalization metadata, content-type override collision policy, or embedded package relationship closure.
