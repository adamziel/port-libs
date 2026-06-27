# Shared OPC Selected Content-Type Buckets

## Slice

`OpcRelationshipGraph::preflightSelectedContentTypes()` now reports `contentTypeSummaryCount` and `contentTypeSummaries` for caller-provided OPC part lists.

Each summary groups selected parts by resolved content type and preserves:

- selected, existing, missing, exact-match, equivalent-match, duplicate, valid, and invalid counts;
- content-type source counts and selected-part match-kind counts;
- selected part names, stored package part names, normalized part names, issues, and issue counts.

The preflight still does not read selected package part payload bytes. Missing selections can still be represented in the resolved content-type bucket when `[Content_Types].xml` supplies a default or override for the requested part name.

## Validation

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Focused result: `1 test files, 4710 assertions, 0 failures`.

## Accounting

- `phpPass`: 461 -> 462.
- Focused behavior case added: `summarizes selected OPC content type buckets before reader handoff`.
