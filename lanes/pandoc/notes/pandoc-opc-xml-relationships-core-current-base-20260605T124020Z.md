# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T124020Z`

Base accepted HEAD: `ab10b47deab67d77a4019d8ab12eee9ff8089952`

## Behavior Added

- Extended bounded native `OpcRelationshipGraph` package-signature preflight
  for OPC XML Signature `RelationshipTransform` references:
  - extracts `ContentType` query hints from signature `Reference` URI values;
  - preserves raw relationship-part URI resolution while reporting the target
    relationship part's content type;
  - decodes percent escapes with `rawurldecode` so `+xml` media suffixes remain
    literal plus signs instead of query-form spaces;
  - reports match state between the declared `ContentType` query hint and the
    relationship part content type;
  - flags mismatched, duplicate, empty, and malformed `ContentType` query
    hints before selected relationships are trusted by review code;
  - preserves accepted behavior for signature references without a
    `ContentType` query hint.
- Extended the WordPress DOCX OPC preflight example so package-signature
  relationship-transform rows and guards expose the new content-type fields.

## Source Truth

- OPC package-signature relationship transforms can reference relationship
  parts by URI and can carry a `ContentType` query hint for the referenced
  part. This slice maps that bounded package semantics into native PHP
  preflight rows for DOCX/OpenXML review packets.
- This is a relationship and content-type preflight only. It does not implement
  byte-for-byte XML Signature canonicalization, digest calculation, certificate
  trust, encrypted-package policy, or a general XMLDSig validator.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, XMLDSig validator, external office tool, browser
  renderer, online sanitizer, or online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 751 assertions, 0 failures`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 785 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- JSON status/manifest validity:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both lane JSON files decoded successfully.
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Focused OPC tests moved from 47 to 48 PASS cases and from 751 to 785
assertions. Lane `phpPass` moves from 898 to 899 and mapped native inventory
moves from 1356 to 1357.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and `XmlHtmlDom` support rows. Full upstream runner parity
remains gated on hydrating the Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and producing a Cabal
solver/build plan for `test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted OPC coverage for content-types parsing,
relationship Id validation, relationship target integrity, reachable closure
traversal, embedded package placeholders, DOCX body imports, ZIP extra-field
preflight, or digital-signature origin discovery. It owns only bounded
package-signature relationship-transform `Reference` URI `ContentType` query
preflight and WordPress review-packet exposure.

## Follow-Up

Keep byte-for-byte XML Signature canonicalization and digest validation,
certificate-chain policy, encrypted package policy, nested embedded-package
graph expansion, and higher-level DOCX UI treatment of signature diagnostics
as separate bounded slices.
