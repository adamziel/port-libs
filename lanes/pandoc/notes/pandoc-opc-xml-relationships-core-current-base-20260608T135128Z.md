# pandoc-opc-xml-relationships-core-current-base-20260608T135128Z

Accepted base: `95ed9a719a03101e72b33de7de15d86db46d9a80`

## Behavior

Bounded OPC digital-signature preflight now inspects `ds:Object` `ds:Manifest`
references. `OpcRelationshipGraph::preflightDigitalSignatureMetadata()` reports
manifest counts plus each direct `ds:Reference` URI, resolved package target,
content type, digest algorithm/value metadata, decoded digest-byte length,
validity, and bounded issues for missing parts, external or fragment URIs,
missing digest methods, missing digest values, and invalid base64.

This is metadata/preflight only. It does not perform XMLDSig canonicalization,
cryptographic digest comparison, certificate-chain verification, or any external
validator execution.

## Evidence

- Rework note check: no `port-pandoc-*.needs-lane-rework.md` existed for this lane.
- Baseline focused run: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2187 assertions, 0 failures`.
- Red-first focused run after adding the manifest-reference expectations failed with `1 test files, 2189 assertions, 1 failures` because `manifestReferences` metadata was absent.
- Final focused run: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2226 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed.
- PHP lint passed for `lanes/pandoc/src/OpcRelationshipGraph.php`, `lanes/pandoc/tests/OpenPackagingConventionsTest.php`, and `lanes/pandoc/examples/wordpress-docx-opc-preflight.php`.
- `git diff --check -- lanes/pandoc` passed.

## Delta

- Adds one named PHP PASS case.
- Adds `+39` focused assertions to `OpenPackagingConventionsTest.php`.
- Raises lane `phpPass` from `1659` to `1660`.
- Raises `benchmarkDenominator.mapped` from `2079` to `2080`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses existing
`ZipPackage`, `OpcContentTypes`, `OpcPackagePath`, and DOM-based
`OpcRelationshipGraph` digital-signature preflight. Full Pandoc runner parity,
Word/LibreOffice rendering, zip/unzip, XMLDSig validators, external XML tools,
online services, and live provider tests remain out of scope.

## Follow-Up

Choose a non-overlapping OPC package-semantics gap next, such as signature
transform canonicalization metadata, content-type override collision review, or
embedded package relationship closure.
