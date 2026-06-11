# Pandoc Native Metadata Helpers Current-Base Slice

## Scope

- Bead: `plib-r29sa`, Pandoc JSON/native AST constructor completeness core blocker slice `20260611T111743Z`.
- Base: current `origin/main` `54225f7969847a535d1d02c509a3c4662c8bc13c`, fetched before the final post-rebase gate.
- Upstream inventory anchor remains the accepted static Pandoc inventory at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Behavior

`NativeReader` now derives shared metadata helpers from standard native constructors:

- `title` `MetaInlines` -> `titleInlines`
- `author` `MetaList` of `MetaInlines` -> `authorInlines`
- `date` `MetaInlines` -> `dateInlines`

The raw `Meta*` constructor payloads remain in `document->attr('meta')`, and `NativeWriter` continues to skip helper keys when emitting native JSON. This preserves exact metadata constructor round trips while giving downstream shared AST consumers the same standard helper surface already available through JSON metadata handoff.

## Evidence

Red probe before the patch: a native JSON document with `title`, `author`, and `date` constructors exposed only `title,author,date` keys from `NativeReader`, so shared helper consumers could not inspect standard metadata inlines without reparsing native constructors.

Verification on `54225f7969847a535d1d02c509a3c4662c8bc13c`:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`: 1 test file, 324 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 63393 assertions, 0 failures

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
