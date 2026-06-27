# Pandoc JSON/native raw TeX inline text reader

Slice: `plib-wbijv`
Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for raw TeX inline nodes
created by the native text reader. `NativeReader` text input can produce
`raw_tex_inline` AST nodes for `RawInline (Format "tex") ...`; `PandocJsonWriter`
now treats that node shape as an inline RawInline constructor, including default
`latex` format and `tex` text fallback. The NativeWriter JSON path reuses the
same Pandoc JSON writer path, so native text input can move through both JSON
writer surfaces without shelling out.

Validation:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Direct PHP smoke over `NativeReader` text input to `PandocJsonWriter` RawInline output
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`

Result:

- New regression `serializes native text raw tex inline nodes through pandoc json writers`: PASS
- Direct smoke: `raw_tex_inline json smoke ok`
- Full focused file remains blocked by 13 pre-existing unrelated failures.

Accounting:

- `lane-status.json` `phpPass`: `445 -> 446`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2300 -> 2301`
- `mappedJsonNativeRawTexInlineTextCases`: `1`

No Pandoc binary, TeX engine, Haskell/Cabal runner, browser renderer, external
validator, online service, live provider test, or live-service provider test was
used.
