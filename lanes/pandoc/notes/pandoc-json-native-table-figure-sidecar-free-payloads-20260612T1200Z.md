# Pandoc JSON/native Table/Figure sidecar-free payloads

Bead: `plib-iki6d`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` now allows sidecar-free current-shape `Table` and `Figure`
native payloads through the existing reader-normalized reuse guard. This keeps
exact source constructor payloads visible when a supported Table/Figure AST is
unchanged, including payloads whose body/cell blocks would otherwise canonicalize
from source `Para` wrappers into generated `Plain` wrappers.

Semantic edits still force regeneration. The focused regression edits a table
cell and figure caption after import and verifies both JSON and native writers
emit the edited content instead of stale source payloads.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `phpPass`: `3203 -> 3204`
- `phpFail`: `0`
- `mappedJsonNativeTableFigureSidecarFreePayloadCases`: `+1`
- `jsonNativeTableFigureSidecarFreePayloadAssertions`: `20`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1577 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 70932 assertions, 0 failures`
