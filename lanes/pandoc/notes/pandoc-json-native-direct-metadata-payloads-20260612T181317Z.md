# Pandoc JSON/native direct metadata payloads

Bead: `plib-p7yk6`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` now preserves directly supplied current metadata
constructor payloads when the normalized shared AST metadata value still
matches the payload:

- `MetaString` values keep inert sidecars through `PandocJsonWriter` and
  `NativeWriter`;
- nested `MetaMap` values keep map-level and child sidecars;
- `MetaBlocks` values keep block payload sidecars.

Edited metadata values still regenerate through the canonical writer path, so
stale direct payload sidecars are dropped once the shared AST value changes.
Legacy `MetaMap` `unMeta` wrappers remain canonicalized by the existing guard.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

## Accounting

- `lane-status.json` `phpPass`: `3255 -> 3256`.
- `phpFail`: `0`.
- Added `mappedJsonNativeDirectMetadataPayloadCases: 1`.
- Added `jsonNativeDirectMetadataPayloadAssertions: 17`.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1724 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 72743 assertions, 0 failures`
