# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T013546Z`
Base accepted HEAD: `8501d438d0b0971a6a00bb6327402c508aeb24d9`

## Behavior

- Added bounded OPC digital signature relationship preflight in native PHP.
- `OpcRelationshipGraph::preflightDigitalSignatures()` now locates package-root
  digital-signature origin relationships, checks the origin part content type,
  follows the origin part relationship set, and preflights XML signature target
  parts.
- The helper reports missing parts, wrong digital-signature content types,
  external origin/signature targets, multiple origin relationships, and missing
  origin relationship parts without attempting cryptographic verification.
- Updated the WordPress DOCX OPC preflight example so review packets expose
  `/_xmlsignatures/origin.sigs`, the origin relationship part, and signature XML
  parts before an import queue treats a signed DOCX as an ordinary unsigned
  package.

## Source Truth

- OPC digital signatures are represented as package relationships:
  `http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin`
  from the package root and
  `http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature`
  from the signature-origin part.
- The bounded content-type checks use the OPC digital-signature media types
  `application/vnd.openxmlformats-package.digital-signature-origin` and
  `application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml`.
- This stays in package/relationship semantics needed by DOCX/OpenXML import
  preflight. It does not validate XMLDSig transforms, canonicalization,
  certificates, timestamps, or trust chains.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before new tests: `1 test files, 293 assertions, 0 failures`.
- Red-first focused OPC test after adding the new cases:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: 2 failures on missing
    `OpcRelationshipGraph::preflightDigitalSignatures()`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 322 assertions, 0 failures`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Full lane-focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5289 assertions, 0 failures`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Delta

- Focused OPC tests moved from 25 to 27 PASS cases.
- Focused OPC assertions moved from 293 to 322, adding 29 assertions.
- Lane status moved from 505 to 507 PHP PASS lines.
- Manifest mapped checks moved from 980 to 982 with a new
  `opcDigitalSignaturePreflightCases` bucket.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, and
`OpcPackagePath` primitives. No Pandoc, Word, LibreOffice, zip/unzip,
cryptographic validator, Haskell runner, browser renderer, or online service was
invoked.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
lookup, relationship XML parsing, XML NCName Id validation, URI target decoding,
target integrity preflight, relationship-part source validation, external target
policy, package-part preflight, and reachable relationship closure traversal.

## Follow-Up

Keep cryptographic signature verification, certificate-chain validation,
signature transform/canonicalization parsing, encrypted package policy, embedded
package policy, and full upstream Haskell runner dependency planning as separate
bounded slices.
