## OPC Missing Signature Relationship Part Reference

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260605T215341Z`
Base: `2327c26a69235d4b32b986d7e360e0be32c213e0`

Implemented one bounded OPC package-signature preflight behavior: XML
Signature `ds:Reference` URIs that point at relationship parts now report
whether the referenced `.rels` part exists in the package. When the URI is a
valid relationship-part name but the package omits that part,
`OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now returns
`referenceRelationshipPartExists => false`, adds
`reference-relationship-part-missing-in-package`, and still preserves the
existing content-type query and selector diagnostics.

Source-truth scope:

- OPC package-signature `RelationshipTransform` references are relationship
  package semantics, owned by this lane slice.
- This patch reuses existing native PHP `ZipPackage`, `OpcContentTypes`,
  `OpcRelationships`, and `OpcRelationshipGraph` support.
- No Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML
  tool, online service, or live provider test was executed.

Focused evidence:

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  failed before implementation with `1 test files, 928 assertions, 1 failures`
  because `referenceRelationshipPartExists` was absent.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 940 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.

Status delta:

- `lane-status.json` `phpPass` moved from `1088` to `1089`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moved from `1540` to
  `1541`.
- Added one mapped native OPC package-signature preflight case with 15 direct
  focused assertions.

Non-overlap:

- Avoided accepted OPC content-type inventory, relationship Id validation,
  external target policy, package consistency, office-document root,
  signature-origin/signature discovery, embedded object/package, selector
  materialization, content-type query, selector shape, singular group
  reference, and reachable closure behavior.

Dependency closure:

- No new support component is needed. Full XML Signature canonicalization,
  digest verification, signature policy, and broader upstream Pandoc runner
  parity remain separate bounded follow-up work.
