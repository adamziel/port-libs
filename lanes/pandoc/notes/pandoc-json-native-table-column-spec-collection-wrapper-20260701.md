# Pandoc JSON/native table column-spec collection wrappers

Slice: `plib-zrg4o`
Base: current main `8ad16f37d`

Implemented one bounded JSON/native AST constructor-completeness case for
single-wrapped multi-item table column-spec collections. `PandocJsonReader`
now treats a table `colSpecs` payload shaped as one wrapped list of multiple
column spec tuples as the declared column-spec collection instead of
misreading the first tuple as a table alignment constructor.

`PandocJsonWriter` now records and reuses the collection wrapper via
`columnSpecsNative`, while preserving per-spec alignment/width sidecars through
`columnSpecNatives`, `alignmentNatives`, and `columnWidthNatives`. Rebuilt table
wrappers keep the wrapped collection shape when the generated specs still
match, and edited widths regenerate current `ColWidth` payloads without stale
source sidecars.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites,
TeX/browser engines, zip/unzip, Jupyter, Node tooling, external validators,
online services, or live provider tests were invoked.

Validation:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php`
  - `1 test files, 84 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php lanes/pandoc/tests/PandocJsonRawTexInlineConstructorTest.php lanes/pandoc/tests/NativeShortCaptionConstructorTest.php lanes/pandoc/tests/NativeDefinitionTermConstructorTest.php lanes/pandoc/tests/JsonReaderFormatConstructorTest.php`
  - `6 test files, 198 assertions, 0 failures`

Accounting:

- `JSON / native AST`: `87 -> 88` local mapped cases.
- Direct-format parity remains active; this is a bounded constructor
  completeness slice only.
