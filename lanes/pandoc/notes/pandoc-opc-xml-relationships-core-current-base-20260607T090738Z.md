# OPC XML Relationships Core - Relationship Source Inventory

Slice: `pandoc-opc-xml-relationships-core-current-base-20260607T090738Z`

Base accepted HEAD: `45057471969b541c83b4a7de143f12f01b0ba6b9`

## Source Truth

This slice maps bounded OPC relationship graph review behavior needed before DOCX/OpenXML import: a package can contain multiple relationship sources, and a converter must audit each loaded source part separately rather than only grouping by relationship type or content type.

The implemented behavior is native package graph preflight only. It does not shell out to Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, or online services.

## Implementation

- Added `OpcRelationshipGraph::relationshipSourceInventory()` to emit one row per loaded relationship source.
- Each row records source existence/content type, relationship-part path/content type/load action/load reason/issues, relationship count, internal/external split, valid/invalid target counts, relationship types, target parts, content types, external targets, missing target parts, aggregate issues, and validity.
- Added focused OPC tests covering a clean package root source and a document source with missing internal target, unsafe external target, and malformed relationship type diagnostics.
- Updated `wordpress-docx-opc-preflight.php` to expose the full `relationshipSourceInventory` plus a concise `wordpressImport.relationshipSourceReview` packet for import review.
- Updated the upstream manifest mapped denominator and OPC graph/source-inventory counters for one mapped native support case.

## Focused Evidence

Baseline before the slice:

```text
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
1 test files, 1638 assertions, 0 failures
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
1 test files, 1681 assertions, 0 failures

php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
opc docx preflight self-test ok

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
passed with no output
```

Delta: +1 PHP PASS case, +43 focused assertions, mapped denominator `1899 -> 1900`, OPC graph support cases `13 -> 14`, OPC relationship source inventory cases `0 -> 1`.

## Non-Overlap

This does not repeat the earlier content-type inventory, relationship type inventory, package part reference inventory, Pack URI part-name validation, digital signature, embedded package/object, document property, thumbnail, or relationship-transform slices. It is a distinct package-wide rollup by relationship source.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcPackagePath`, and `OpcRelationshipGraph` target preflight primitives plus the existing focused pandoc test harness and WordPress DOCX OPC preflight example.

Remaining out of scope: full upstream Pandoc/Haskell runner parity, DOCX reader consumption of the new review rows, Word/LibreOffice validation, XMLDSig validation, zip/unzip tooling, external XML tools, online services, live provider tests, and live-service provider tests.
