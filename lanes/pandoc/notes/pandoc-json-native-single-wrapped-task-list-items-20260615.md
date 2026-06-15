# JSON/native single-wrapped task-list item sidecars

Slice: `pandoc-json-native-single-wrapped-task-list-items`

This bounded JSON/native AST constructor-completeness slice keeps Pandoc task-list checkbox provenance visible when a `BulletList` item arrives as a single-wrapped block-list payload. `PandocJsonReader` and `NativeReader` now unwrap one list-item payload layer before reading `taskChecked`, matching the block parsing path that already accepted the wrapped item.

The focused case verifies both JSON and native readers expose `taskList` and per-item `taskChecked` attrs, preserve unchanged single-wrapped list-item payloads through `PandocJsonWriter` and `NativeWriter`, and regenerate edited task items with canonical checkbox sidecars instead of stale review sidecars.

No Pandoc binary, JSON filter runner, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test is invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed 1 file, 5394 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 46 files, 88305 assertions, 0 failures.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- Conflict-marker scan

## Accounting

- `phpPass`: `3723 -> 3724`
- `phpFail`: `0`
- `mappedJsonNativeConstructorCompletenessCases`: `55 -> 56` in lane status; `51 -> 52` in the upstream manifest
- `jsonNativeConstructorCompletenessAssertions`: `1408 -> 1440` in lane status; `1273 -> 1305` in the upstream manifest
- `mappedJsonNativeSingleWrappedTaskListItemCases`: `1`
- `jsonNativeSingleWrappedTaskListItemAssertions`: `32`
