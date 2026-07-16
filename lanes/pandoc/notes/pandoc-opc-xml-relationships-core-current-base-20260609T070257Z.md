## pandoc-opc-xml-relationships-core-current-base-20260609T070257Z

Accepted base: `53cc273b044292e061f08ae6f6fdabc37210dcb0`

Behavior added:
- `OpcRelationshipGraph` now flags XMLDSig enveloped-signature transforms when they appear on a SignedInfo Reference that also uses the OPC RelationshipTransform against a relationship part.
- The raw relationship-transform preflight reports `relationship-transform-with-enveloped-signature-transform`.
- The SignedInfo reference metadata preflight reports `signed-info-relationship-transform-with-enveloped-signature-transform` for the same structural policy issue.
- The WordPress DOCX OPC preflight example exposes the guard in both full signature output and the compact `wordpressImport` projection.

Focused evidence:
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 3504 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 3522 assertions, 0 failures`.
- Delta: `+1` focused PHP PASS case and `+18` focused assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.

Dependency closure:
- No new native PHP support component is needed.
- This slice reuses `OpcRelationshipGraph`, existing OPC relationship XML parsing, XMLDSig SignedInfo reference metadata extraction, `OpcContentTypes`, `OpcRelationships`, and the DOCX OPC preflight example.
- Cryptographic XMLDSig validation remains out of scope; this is a bounded structural package preflight guard.

Exclusions:
- Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, `zip`/`unzip`, `ZipArchive`, tar, gzip, lz4, TeX/PDF engines, Typst, browser renderers, XMLDSig validators, external XML tools, online services, live provider tests, or live-service provider tests.
- Root harness not run - isolated micro-slice.

Next non-overlapping OPC follow-up:
- Target signature Reference digest-method policy, signed relationship type allowlists, or relationship part canonicalization edge cases. Do not repeat relationship transform order, selector shape, missing relationship part, reference URI kind, unsafe reference URI, package-part coverage, or this enveloped-signature transform guard.
