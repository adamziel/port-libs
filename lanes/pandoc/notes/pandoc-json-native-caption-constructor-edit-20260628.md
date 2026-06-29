# Pandoc JSON/native Caption constructor edit slice

Slice: `plib-lglho`
Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for tagged table caption
helpers. `PandocJsonWriter` now preserves valid `Caption`, `Just`, and
`ShortCaption` constructor wrappers when a table caption is edited, while
regenerating the changed short and long caption payloads and dropping stale
sidecars from regenerated long-caption blocks. `NativeWriter` uses the same JSON
writer path for native JSON output.

Validation:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeCaptionConstructorEditTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeCaptionConstructorEditTest.php`

Result:

- `PandocJsonNativeCaptionConstructorEditTest.php`: 40 assertions, 0 failures

Accounting:

- `lane-status.json` `phpPass`: `471 -> 472`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2312 -> 2313`
- `mappedJsonNativeCaptionConstructorEditCases`: `1`

No Pandoc binary, Haskell/Cabal runner, browser renderer, office suite, external
validator, online service, live provider test, or live-service provider test was
used.
