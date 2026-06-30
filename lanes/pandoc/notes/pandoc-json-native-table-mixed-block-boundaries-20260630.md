# Pandoc JSON/native table mixed block boundaries

Hook: `plib-arz4a`, JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness slice for generated table
caption and cell payloads. `NativeWriter` now normalizes mixed inline/block
children through the same Plain/block/Plain boundary shape used by the Pandoc
JSON writer, so table captions and cells can carry leading/trailing inline runs
around nested blocks without treating inline `Space` nodes as block
constructors.

This is native PHP JSON/native AST support only. It does not invoke Pandoc,
Haskell/Cabal runners, office suites, TeX/browser engines, zip/unzip,
Jupyter, Node tooling, or external validators.

Accounting:

- `lane-status.json` `phpPass`: `466 -> 467`.
- `UPSTREAM_TEST_MANIFEST.json` `nativeWriterTableConstructorCases`: `9 -> 10`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedNativeWriterTableConstructorCases`: `9 -> 10`.
- Added `mappedJsonNativeMixedTableBlockBoundaryCases: 1`.

Validation:

- `php -l lanes/pandoc/src/NativeWriter.php`
- Selected `PandocJsonNativeAstTest.php` case `flushes mixed table caption and cell inline runs around nested blocks`: 26 assertions, 0 failures.

The full `PandocJsonNativeAstTest.php` file remains baseline-red outside this
slice: 1 file, 5,940 assertions, 11 existing failures. This closure covers the
focused mixed table boundary case only.
