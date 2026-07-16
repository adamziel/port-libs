# Shared ZIP/OPC Invalid Part-Name Preflight

Implemented one bounded shared ZIP/OPC package primitive in native PHP.

`OpcRelationshipGraph::preflightPackagePartNames()` now inventories ZIP entries
as OPC package parts before relationship graph construction. Directory entries
remain non-package-part placeholders, valid part names receive canonical OPC
part names, and invalid ZIP-safe names are reported with structured issues such
as:

- `part-name-query-or-fragment`
- `part-name-trailing-dot-segment`
- `invalid-opc-part-name`

`OpcRelationshipGraph::fromPackage()` now blocks graph construction when such
invalid package part names are present, producing an explicit invalid-part-name
error before content-type or relationship loading continues. This prevents DOCX
and other OPC consumers from silently treating query/fragment-looking ZIP entry
names or trailing-dot path segments as package parts.

## Scope

This is a shared package primitive only. It does not change DOCX body parsing,
EPUB/ODF package readers, relationship target resolution, content-type grammar,
ZIP decompression, or archive extraction policy. It does not invoke Pandoc,
office suites, zip/unzip, browser renderers, external validators, online
services, or live provider tests.

## Metric

- `lane-status.json` `phpPass`: `2955 -> 2956`
- `phpFail`: `0`
- Mapped direct-format parity denominator: unchanged; this is shared OPC package
  preflight coverage, not a new reader/writer format registration.

## Verification

```bash
php -l lanes/pandoc/src/OpcRelationshipGraph.php
php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused result:

```text
1 test files, 3737 assertions, 0 failures
```

Full lane result:

```text
44 test files, 59881 assertions, 0 failures
```
