# JSON/native table subconstructor envelopes

Slice: `plib-p7yk6` on current main `d504ad4468`.

This slice covers one bounded Pandoc JSON/native AST constructor completeness gap: source-tagged `TableHead`, `TableBody`, `TableFoot`, `Row`, and `Cell` envelopes must survive when a table is regenerated after an AST edit. The JSON and native writers now preserve those envelopes from the ingested native payload while still regenerating edited table attrs and preserving nested helper payloads such as `RowHeadColumns` and `RowSpan`.

Mapped accounting:

- `mappedJsonNativeTableSubconstructorEnvelopeCases`: 1
- `jsonNativeTableSubconstructorEnvelopeAssertions`: 48
- `phpPass`: 3143 -> 3144
- Upstream mapped denominator: 3220 -> 3221

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 test file, 1208 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67244 assertions, 0 failures

No Pandoc binary, JSON filters, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
