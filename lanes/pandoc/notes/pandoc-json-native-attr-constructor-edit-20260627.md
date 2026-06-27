# Pandoc JSON/native Attr constructor edit slice

Slice: `plib-5jd0i`
Area: Pandoc JSON/native AST constructor completeness.

Implemented a bounded constructor-completeness case for tagged `Attr` helpers.
`PandocJsonWriter` now preserves valid `Attr` constructor wrappers when node or
table-cell attributes are edited, regenerating the inner id/class/key-value
tuple while preserving wrapper-level provenance and dropping stale tuple-level
sidecars. `NativeWriter` uses the same JSON writer path for native JSON output.

Validation:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`

Result:

- Edited tagged `Attr` constructor regressions: PASS
- Full focused file remains blocked by 12 unrelated baseline failures.

Accounting:

- `lane-status.json` `phpPass`: `457 -> 458`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2304 -> 2305`
- `mappedJsonNativeAttrConstructorEditCases`: `1`

No Pandoc binary, Haskell/Cabal runner, browser renderer, office suite, external
validator, online service, live provider test, or live-service provider test was
used.
