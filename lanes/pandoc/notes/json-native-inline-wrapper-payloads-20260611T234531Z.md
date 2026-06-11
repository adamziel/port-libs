# JSON/native inline wrapper payloads

Slice: `plib-wa2zk` on current main `8086676050`.

This slice covers one bounded Pandoc JSON/native AST constructor completeness gap: source-tagged inline wrapper constructors must survive JSON and native writer output while the inline wrapper is unchanged, but must regenerate once semantic inline children are edited. The covered constructors are `Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`, `SmallCaps`, `Quoted`, `Note`, and `Span`.

Mapped accounting:

- `mappedJsonNativeInlineWrapperPayloadCases`: 1
- `jsonNativeInlineWrapperPayloadAssertions`: 10
- `phpPass`: 3145 -> 3146
- Upstream mapped denominator: 3221 -> 3222

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` -> 1 test file, 1169 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 67336 assertions, 0 failures

No Pandoc binary, JSON filters, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
