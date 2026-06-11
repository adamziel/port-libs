# JSON/native metadata inline constructor parts

2026-06-11 UTC slice `plib-v5bb4` closes a bounded Pandoc JSON/native AST constructor-completeness gap in metadata emission.

Pandoc native JSON body text already preserved coalesced `Str`/`Space` constructor parts through `nativeInlineParts`, but Pandoc JSON metadata inline emission called `writeInline()` directly. That flattened coalesced metadata text into a single `Str` and dropped original constructor boundaries.

`PandocJsonWriter` now routes metadata inline AstNode values, inline AstNode lists, and typed `MetaInlines` children through `writeInlines()`, matching document-body inline emission and preserving valid `nativeInlineParts`.

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed 1 file, 800 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files, 63824 assertions, 0 failures.

No direct-format parity accounting changed in this slice.
