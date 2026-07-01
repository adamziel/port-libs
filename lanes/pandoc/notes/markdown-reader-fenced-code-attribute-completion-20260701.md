# Markdown reader fenced code attribute completion

Slice: `plib-ou8xe`

This slice maps the upstream `test/command/indented-fences.md` static fixture
from pandoc commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83` into a focused
native PHP MarkdownReader test and closes a bounded fenced-code attribute gap.

The reader now uses the shared Markdown attribute parser for fenced code info
strings in `{...}` form. Quoted attribute values with spaces and escaped quotes
now survive as AST attributes, NativeWriter output, MarkdownWriter regeneration,
and WordPress code block handoff.

Validation:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderFencedCodeAttributeCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderFencedCodeAttributeCompletionTest.php` passed with 1 file, 21 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderFencedCodeInfoStringCompletionTest.php` passed as part of the focused reader run.
- Full lane was attempted with `php tools/run-tests.php lanes/pandoc/tests/*.php`; it remains baseline-red with 535 files, 142315 assertions, and 8912 failures unrelated to this slice.

No external Pandoc converter, office suite, TeX/browser engine, Node tooling,
external validator, or live provider was invoked.
