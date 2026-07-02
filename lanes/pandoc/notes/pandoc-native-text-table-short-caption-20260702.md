# Pandoc Native Text Table Short Caption

Slice: `plib-zt62`

This regression locks the current Pandoc text-native table caption shape where a
table caption may carry `Just (ShortCaption [...])`, while retaining the older
accepted `Just [...]` inline-list form. `NativeReader` already accepts both
forms on the current base, and `NativeWriter` / `PandocJsonWriter` normalize the
legacy form back to the current `ShortCaption` constructor for table captions.

Mapped parity accounting:

- `mappedNativeTextTableCaptionConstructorCases`: `1`
- `nativeTextTableCaptionConstructorAssertions`: `37`

Focused evidence:

- `php -l lanes/pandoc/tests/NativeReaderTextTableShortCaptionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextTableShortCaptionTest.php`
  - 1 file, 37 assertions, 0 failures.

Scope notes:

No Pandoc binary, Haskell runner, office suite, TeX/browser engine, Typst,
Node tooling, zip/unzip, external validator, or live service was invoked.
