# OPC XML Relationships Core - Document Property Relationships

Slice: `pandoc-opc-xml-relationships-core-current-base-20260607T075859Z`

Base accepted HEAD: `912c56d812f68fca8f6ea91b90c49265da9a9a1d`

## Source Truth

This slice maps the bounded OPC package-root document property relationship roles already used by the native DOCX lane:

- core properties: `http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties` with `application/vnd.openxmlformats-package.core-properties+xml`
- extended properties: `http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties` with `application/vnd.openxmlformats-officedocument.extended-properties+xml`
- custom properties: `http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties` with `application/vnd.openxmlformats-officedocument.custom-properties+xml`

The implemented behavior is package graph preflight only. It does not parse property XML payloads, shell out to Pandoc, or invoke Office/ZIP/XMLDSig tools.

## Implementation

- Added `OpcRelationshipGraph::preflightDocumentProperties()` for package-root core, extended, and custom property relationship roles.
- Added graph constants for extended/custom property relationship types and expected content types.
- Preserved `preflightCoreProperties()` for existing callers while exposing a richer role map for multi-property package review.
- Updated `wordpress-docx-opc-preflight.php` to include `/docProps/app.xml` and `/docProps/custom.xml`, expose `documentPropertiesPreflight`, and surface `wordpressImport.documentPropertyParts`.
- Updated the upstream manifest mapped denominator and OPC graph counters for one mapped native support case.

## Focused Evidence

Baseline before the slice:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1574 assertions, 0 failures
```

Final verification:

```text
php -l lanes/pandoc/src/OpcRelationshipGraph.php
No syntax errors detected in lanes/pandoc/src/OpcRelationshipGraph.php

php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php
No syntax errors detected in lanes/pandoc/tests/OpenPackagingConventionsTest.php

php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php
No syntax errors detected in lanes/pandoc/examples/wordpress-docx-opc-preflight.php

php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1638 assertions, 0 failures

php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
opc docx preflight self-test ok

git diff --check -- lanes/pandoc
passed with no output
```

Delta: +1 PHP PASS case, +64 focused assertions, mapped denominator `1890 -> 1891`, OPC graph support cases `13 -> 14`, OPC graph assertions `210 -> 274`.

## Non-Overlap

This does not repeat the earlier content-type inventory, Pack URI part-name validation, core-only properties, thumbnail, digital signature, embedded package/object, relationship transform, or content-types item source slices. It is a distinct package metadata role preflight for document properties.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcPackagePath`, and `OpcRelationshipGraph` primitives plus the existing WordPress DOCX OPC preflight example.

Remaining out of scope: full upstream Pandoc/Haskell runner parity, property XML payload parsing, DOCX reader consumption of the new role map, XMLDSig validation, and external office-tool parity.
