# OPC Pack URI Part-Name Validation

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T152949Z`
Base: `3d6e6a3622decb12b82b423840061172715fe0f2`

## Summary

Implemented bounded OPC Pack URI part-name validation for content-type
overrides and internal relationship targets.

- `[Content_Types].xml` `Override PartName` values now reject raw whitespace
  and control bytes before percent-decoding.
- Content-type override part-name segments now reject decoded segments ending
  in a dot, including `%2E` at the end of a segment.
- `OpcPackagePath` rejects canonical package paths whose decoded segments end
  in a dot.
- Relationship target preflight now reports
  `internal-target-trailing-dot-segment` for invalid internal targets before
  package-part lookup, instead of collapsing the case into
  `missing-in-package`.

Encoded spaces such as `%20` remain valid and round-trip through content-type
XML serialization.

## Focused Evidence

Red-first after adding Pack URI part-name assertions:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1162 assertions, 4 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1183 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
opc docx preflight self-test ok
```

PHP lint:

```text
php -l lanes/pandoc/src/OpcPackagePath.php
php -l lanes/pandoc/src/OpcContentTypes.php
php -l lanes/pandoc/src/OpcRelationshipGraph.php
php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php
php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php
```

All five reported no syntax errors. Root harness was not run because this is an
isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcRelationship`, and `OpcPackagePath` support rows. No Pandoc, Cabal,
Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML
tool, online service, live provider test, or live-service provider test was
executed.

## Non-Overlap

This is additive on top of accepted OPC content-type inventory grouping,
Pack URI override normalization, relationship XML namespace parsing, XML
NCName Id validation, relationship target percent-decoding, raw-whitespace
target rejection, malformed percent escape rejection, target integrity
preflight, relationship-part source validation, package-signature relationship
transform preflight, package role content-type matching, and reachable
relationship closure traversal. It owns only raw whitespace/control-byte
`Override PartName` validation and trailing-dot package path segment rejection.

## Next

Keep cryptographic signature validation, XML canonicalization,
relationship-transform digest validation, richer source/target role validation,
and broader DOCX reader integration as separate bounded slices.
