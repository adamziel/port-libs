# Pandoc JSON/native Figure Child Block Payloads

2026-06-13 UTC slice for `plib-n5hmi`.

`PandocJsonWriter` now emits non-inline `Figure` children through the guarded
block writer path. This matches `NativeWriter` behavior and keeps unchanged
child block native payloads reusable when only the containing `Figure` wrapper
is regenerated.

The focused regression edits a source `Figure` caption while leaving a child
`Div` block unchanged. JSON and native writer output both drop stale
`Figure` wrapper sidecars, regenerate the edited caption, and preserve the
unchanged child `Div` native payload including review sidecars.

Evidence counters:

- `phpPass`: `3330 -> 3331`
- `phpFail`: `0`
- `mappedJsonNativeFigureChildBlockPayloadCases`: `1`
- `jsonNativeFigureChildBlockPayloadAssertions`: `26`
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: `3289 -> 3290`

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`: `1` file, `2159` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`: `45` files, `74792` assertions, `0` failures

No Pandoc binary, JSON filters, Haskell/Cabal runner, browser renderer, Node
tooling, online service, live provider test, or external validator was invoked.
