## pandoc-opc-xml-relationships-core-current-base-20260607T162749Z

Base: `1d69a68f53ce21789449f52c6103c11f01fcd7a9`

Scope: native OPC content-types/relationships package semantics only. No Pandoc,
Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online
service, live provider test, Cabal command, or Haskell runner was executed.

Behavior added:

- `OpcRelationshipGraph` now flags
  `relationship-content-type-on-non-relationship-part` when the reserved OPC
  relationships media type
  `application/vnd.openxmlformats-package.relationships+xml` appears on an
  ordinary package part, content-type override, or internal relationship target
  that is not an actual relationship part.
- Package consistency, content-type inventory, and package part reference
  inventory now inherit that diagnostic for DOCX/OPC import preflight.
- The WordPress DOCX OPC preflight example includes a miniature guard package
  that proves default- and override-driven non-relationship parts with the
  reserved media type are rejected without perturbing the existing DOCX fixture
  traversal counts.

Focused evidence:

- Red probe before implementation: a `word/media/default-source.rels`
  relationship target with the reserved relationships content type was reported
  as `valid=true` with `issues=[]`.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed: `1 test files, 1746 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed: `opc docx preflight self-test ok`.
- `php -l` passed for:
  - `lanes/pandoc/src/OpcRelationshipGraph.php`
  - `lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `lanes/pandoc/examples/wordpress-docx-opc-preflight.php`

Status delta:

- Added 1 focused PHP PASS case with 55 assertions.
- Updated lane `phpPass` from `1530` to `1531`.
- Updated mapped static manifest count from `1949` to `1950`.

Dependency closure:

- No new support component is needed. This reuses native
  `OpcContentTypes`, `OpcRelationshipGraph`, package-part preflight,
  content-type inventory, package-reference inventory, and the existing
  WordPress DOCX OPC preflight smoke.

Non-overlap:

- This does not repeat prior OPC content-type inventory, signature reference
  content-type query, fixed `[Content_Types].xml` target/source guards, Pack URI
  part-name validation, relationship source aliasing, part-name case collision,
  invalid relationship-part content type, or orphan relationship-part load
  slices. It covers the inverse reserved-media-type misuse on non-relationship
  package parts and relationship targets.

Next task:

- Continue with a non-overlapping OPC package semantics gap, such as role
  content-type expectations on specific OpenXML relationship types, relationship
  closure diagnostics for unexpected source roles, or inventory metadata useful
  to DOCX/EPUB/ODT import review.
