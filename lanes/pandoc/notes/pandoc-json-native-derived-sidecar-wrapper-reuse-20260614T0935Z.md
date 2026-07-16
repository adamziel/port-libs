# Pandoc JSON/native Derived Sidecar Wrapper Reuse

Slice: `pandoc-json-native-derived-sidecar-wrapper-reuse-20260614T0935Z`
Bead: `plib-yxrvx`

## Scope

This bounded JSON/native AST constructor-completeness slice closes a native
payload reuse gap in `PandocJsonWriter` and `NativeWriter`.

When a shared AST node carried a valid wrapper `native` payload but did not
also carry every derived child helper sidecar, the writers compared those
helper-only attrs against a freshly parsed native node and regenerated the
wrapper. That dropped inert review sidecars even when semantic content matched.

## Behavior

Native reuse comparison now ignores derived Pandoc constructor/provenance attrs
such as `formatNative`, `targetNative`, `attrNative`, enum helper natives,
caption helper natives, table helper natives, citation natives, and
`nativeInlineParts`. Semantic attrs and child structure still participate in
the comparison, so edited content regenerates canonical constructors and drops
stale wrapper sidecars.

The focused regression covers a paragraph wrapper with RawInline `Format`,
Attr, and target tuple sidecars through both JSON and native writers, plus an
edited raw payload guard.

## Mapping Delta

- `phpPass`: `3503 -> 3504`
- `phpFail`: `0`
- `mappedJsonNativeDerivedSidecarPayloadCases`: `1`
- `jsonNativeDerivedSidecarPayloadAssertions`: `16`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: 1 test file, 3274 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result after rebasing onto `99e3393705`: 46 test files, 82493 assertions, 0 failures.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
