## JSON/Native Textual Space Constructor Slice

Issue: `plib-6wjw8`

Implemented a bounded JSON/native AST constructor completeness slice for textual Pandoc native input. `NativeReader` now maps Haskell native `Space` tokens to shared `space` AST nodes instead of collapsing them into text nodes, while plain-text summaries still include spaces. This aligns textual native parsing with the Pandoc JSON reader and preserves the constructor through `PandocJsonWriter`, `NativeWriter`, Markdown round trips, and WordPress metadata handoff without invoking Pandoc.

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderEscapeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderEscapeTest.php` -> 1 file, 11 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderEscapeTest.php lanes/pandoc/tests/NativeWriterDefinitionTermConstructorTest.php` -> 2 files, 24 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` -> native Space and WordPress review/citation cases pass; 34 known plain/doctemplate baseline failures remain
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php` -> 1 file, 297 assertions, 8 known baseline failures outside this slice

Ledger:

- `lane-status.json` `phpPass`: `468 -> 469`
