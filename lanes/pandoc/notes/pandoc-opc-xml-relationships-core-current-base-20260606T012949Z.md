# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T012949Z`

Base accepted HEAD: `d6b4b18da7eea175fa2910b233c1d191e05e49c8`

## Behavior Added

- Added bounded package-signature preflight handling for case-equivalent
  relationship-transform `Reference URI` relationship parts.
- `OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now
  reports the stored package-equivalent `.rels` part name for signature
  relationship-transform references, while preserving the original
  `referenceUri` for audit.
- Duplicate relationship transforms are now detected by relationship-part
  equivalence, so `/word/_rels/document.xml.rels` and
  `/Word/_rels/Document.XML.rels` are treated as the same selected relationship
  part in DOCX/OPC signature review packets.
- The WordPress DOCX OPC preflight smoke now exposes this duplicate
  case-equivalent signature-transform guard.

## Source Truth

- Existing accepted OPC package behavior in this lane already rejects package
  part names that collide by ASCII case and resolves relationship targets to
  stored package-equivalent parts.
- This slice applies that same bounded package-equivalence rule to XML
  Signature relationship-transform references. It does not attempt XML
  canonicalization, digest validation, cryptographic signature verification, or
  upstream Haskell runner parity.

## Red Check

- Baseline focused OPC test:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 951 assertions, 0 failures`.
- Red-first focused OPC test after adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  failed with `1 test files, 954 assertions, 1 failures`.
  Failure: the signature transform reported
  `/word/_rels/document.xml.rels` instead of the stored
  `/Word/_rels/Document.XML.rels`.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 969 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests add 1 PASS case.
- Focused OPC assertions moved from `951` to `969`, adding 18 assertions.
- Lane `phpPass` moved from `1142` to `1143`.
- Manifest mapped native checks moved from `1594` to `1595`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, `XmlHtmlDom`, and the WordPress DOCX OPC preflight smoke.

This slice did not invoke Pandoc, Cabal, Haskell runners, Word, LibreOffice,
zip/unzip, XMLDSig validators, external XML tools, online services, or live
provider tests.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
parsing, relationship XML parsing, XML NCName-style Id validation,
relationship target preflight, case-equivalent package target resolution,
package-signature relationship-transform selector materialization,
relationship-transform `ContentType` query preflight, missing/fragment
reference diagnostics, content-type inventory grouping, and reachable closure
traversal. It does not touch Markdown/HTML reader/writer, doctemplate, YAML
metadata, CSL/BibTeX, DOCX body parsing, ODT, EPUB3, PDF, math, legacy
DOC/CFB, archive compression, syntax highlighting, charset, or
upstream-runner dependency-audit surfaces.

## Follow-Up

Keep XML canonicalization byte-for-byte validation, cryptographic digest and
signature verification, encrypted package policy, and richer signature
reference URI policy as separate bounded OPC slices.
