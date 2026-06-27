# Pandoc JSON/native target constructor edit slice

Slice: `plib-gq5cz`
Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for tagged `Target`
helpers on link and image nodes. `PandocJsonWriter` now preserves valid `Target`
constructor wrappers when a link URL or image title changes, while regenerating
the inner URL/title tuple and dropping stale tuple sidecars. The same behavior
is used by `NativeWriter` JSON output.

Validation:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`

Result:

- New regression `regenerates edited tagged target constructors without stale tuple sidecars`: PASS
- Full focused file remains blocked by 12 unrelated baseline failures.

Accounting:

- `lane-status.json` `phpPass`: `454 -> 455`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2303 -> 2304`
- `mappedJsonNativeTargetConstructorEditCases`: `1`

No Pandoc binary, Haskell/Cabal runner, browser renderer, office suite, external
validator, online service, live provider test, or live-service provider test was
used.
