# JSON/native empty table collection wrappers

Slice: `plib-1nzhb`

This bounded JSON/native constructor-completeness slice accepts empty
single-wrapped table helper collections such as `[[]]` for table column specs,
table bodies, and table head/foot row lists.

`PandocJsonReader` now unwraps those empty collection wrappers only on table
helper collection boundaries where an empty inner list cannot be a valid tagged
item. It records the original wrapper sidecar on the table or section so rebuilt
tables preserve the empty wrapper through `PandocJsonWriter` and `NativeWriter`
while stale table/head/foot helper sidecar keys are dropped after rebuild.

The slice deliberately leaves list-item and line-block `[[]]` ambiguity alone:
those shapes can mean one empty list item or one empty line.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php`
  - `1 test files, 72 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php lanes/pandoc/tests/NativeReaderTextTableHelperProvenanceTest.php lanes/pandoc/tests/JsonReaderHelperConstructorCompatibilityTest.php`
  - `5 test files, 271 assertions, 0 failures`
- Broader `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  reached the adjacent table-helper cases and passed them, but remains
  baseline-red with `1 test files, 6053 assertions, 6 failures` in unrelated
  WordPress/raw-alias/CSL assertions.

No external Pandoc binary, office suite, TeX/browser engine, Typst, Jupyter,
Node, zip/unzip, external validator, online service, or live provider was
invoked.
