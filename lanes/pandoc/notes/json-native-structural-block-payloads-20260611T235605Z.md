# Pandoc JSON/native structural block payloads

Slice: `plib-0soxx` on current main `e87e631746`.

## Scope

- Preserved unchanged current structural block native payloads through `PandocJsonWriter` for `Header`, `BlockQuote`, `OrderedList`, `BulletList`, `DefinitionList`, `LineBlock`, and `Div`.
- Added a recursive current-shape guard so wrapper payload reuse rejects legacy two-entry `Link`/`Image` target shapes and legacy five-entry `Table` payloads.
- Kept edited structural blocks regenerating from shared AST fields so stale inert payload provenance is not reused after semantic edits.

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` - 1 file, 1171 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 67379 assertions, 0 failures

No Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
