# Pandoc OPC Relationship Source Alias Current-Base Slice

## Scope

- Slice: shared OPC relationship graph package-core blocker.
- Target: `OpcRelationshipGraph::relationshipTypeInventory()` source-part filtering.
- Constraint: native PHP only; no Pandoc, office suites, zip/unzip, external validators, or live services.

## Implementation

- Normalized explicit `relationshipTypeInventory($sourcePartName)` filters through the same stored relationship-source alias path used by graph lookup helpers.
- Added an `OpenPackagingConventionsTest` regression that asks for `/WORD/DOCUMENT.XML` and verifies the inventory reports the stored `/word/document.xml` source and relationship IDs.

## Evidence

- Baseline focused test before the change: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed 1 file / 3718 assertions / 0 failures.
- Regression failed before the implementation because `sources` reported `/WORD/DOCUMENT.XML`.
- Syntax checks passed:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- Focused verification after the fix: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed 1 file / 3741 assertions / 0 failures.
- Full post-rebase lane verification: `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 59885 assertions / 0 failures.

## Accounting

- `phpPass`: 2956 -> 2957.
- Mapped OPC relationship graph support cases: 13 -> 14.
- OPC relationship graph assertions: 210 -> 214.
- Focused `OpenPackagingConventionsTest` assertions: 3737 -> 3741.
