# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T205352Z`
Base accepted HEAD: `707b60a141f4e8a970f90fe5df3b1c2d5991fbaa`

## Behavior

Added bounded native PHP OPC preflight coverage for unsafe XML signature
`RelationshipTransform` `Reference URI` package paths. `OpcRelationshipGraph`
now keeps the generic `invalid-reference-uri` issue and adds specific Pack URI
diagnostics for malformed percent escapes, unsafe percent-encoded separators or
NUL bytes, encoded dot segments, raw URI whitespace/control bytes,
trailing-dot package path segments, and package-root traversal.

The WordPress DOCX OPC preflight example now exposes the same unsafe signature
reference diagnostics in `signatureUnsafeReferenceGuards`.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1283 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1380 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.
- PHP lint passed for `lanes/pandoc/src/OpcRelationshipGraph.php`, `lanes/pandoc/tests/OpenPackagingConventionsTest.php`, and `lanes/pandoc/examples/wordpress-docx-opc-preflight.php`.
- JSON parse checks passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

Status delta: `phpPass` `1400 -> 1401`; mapped denominator `1813 -> 1814`; OPC graph support `12 -> 13`; focused OPC assertions `1283 -> 1380`.

## Dependency Closure

No new support component is needed. This reuses native PHP OPC package-path
resolution, relationship graph preflight, XML signature relationship transform
parsing, and the existing WordPress DOCX OPC preflight example.

Not run by design: Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip,
XMLDSig validators, external XML tools, online services, live provider tests,
and live-service provider tests.

## Next

Continue with non-overlapping OPC package-signature work such as digital
signature object/certificate relationship metadata, package-signature
diagnostics, or DOCX reader integration for relationship preflight results.
