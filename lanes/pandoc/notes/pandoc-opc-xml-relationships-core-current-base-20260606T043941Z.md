# OPC Relationship Role Content-Type Matching

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T043941Z`
Base: `331b9a22bb944391ffabb774676dce498dc58a08`

## Summary

Implemented bounded OPC role content-type media-type matching in `OpcRelationshipGraph`. Known package role checks now compare media types case-insensitively for relationship parts, WordprocessingML office-document roots, core properties, digital-signature origins/signature parts, embedded package/object relationships, and package-signature RelationshipTransform `ContentType` query preflight.

The original producer `ContentType` strings remain preserved in content-type lookup, relationship target summaries, inventories, and WordPress preflight reports.

## Focused Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1051 assertions, 0 failures
```

Red-first after adding the mixed-case role-content-type package test:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1054 assertions, 1 failures
```

The failure was the expected relationship graph role check: mixed-case `Application/Vnd.Openxmlformats-Package.Relationships+Xml` was not accepted as the relationship-part role content type, so relationship parts were not loaded.

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1074 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
opc docx preflight self-test ok
```

PHP lint:

```text
php -l lanes/pandoc/src/OpcRelationshipGraph.php
php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php
php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php
```

All three reported no syntax errors.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, and `OpcRelationshipGraph` support. No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, or live provider test was executed.

## Non-Overlap

This does not repeat the accepted content-type inventory grouping, signature `ContentType` query preflight, case-equivalent path lookup, relationship Id validation, target preflight, closure traversal, markup-compatibility processing, or missing-signature-origin guard slices. It only narrows OPC package-role content-type comparison semantics for case-insensitive MIME media types.

## Next

Keep cryptographic signature validation, XML canonicalization, relationship-transform digest validation, and broader DOCX reader integration as separate bounded slices.
