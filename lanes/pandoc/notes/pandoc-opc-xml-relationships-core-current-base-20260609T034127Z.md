# Pandoc OPC XML Relationships Current-Base Slice

Session: `port-dev-pandoc-opc-relationships-20260609T034127Z`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260609T034127Z`
Base accepted HEAD: `6de1d5b33718b9d2dccdce7e31246dedd9031bb9`

## Implementation

This slice tightens native OPC digital-signature relationship role preflight:

- `OpcRelationshipGraph::preflightDigitalSignatureRelationshipRoles()` now
  authorizes signature relationship sources only when the package-root
  digital-signature origin target is internal, exists, passes normal target
  preflight, and has the OPC digital-signature-origin content type.
- Signature relationships below an origin part with the wrong content type now
  report `digital-signature-signature-source-not-origin` instead of being
  source-allowed by the mere presence of a root origin relationship.
- The WordPress DOCX OPC preflight example now includes an integrity guard for
  this invalid-origin-source case.

## Evidence

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 3069 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.

The focused OPC test file gained 1 PHP PASS case and 19 focused assertions.
Root harness was not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
focused OPC tests, and WordPress DOCX OPC preflight smoke. No Pandoc,
Cabal/Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external
converter, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap / Follow-Up

This does not repeat accepted OPC slices for content-type parsing, relationship
part loading, relationship ID validation, embedded packages, reserved `_rels`
parts, fixed `[Content_Types].xml` guards, relationship transforms, signature
metadata extraction, or signature SignedInfo reference classification. A
follow-up should choose a separate OPC relationship gap such as transform digest
validation metadata, source-closure import policy, or higher-level DOCX handoff
of the existing OPC diagnostics.
