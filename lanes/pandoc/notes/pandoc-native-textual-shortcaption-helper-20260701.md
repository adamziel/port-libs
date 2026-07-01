# Pandoc native textual ShortCaption helper

Slice: `plib-0sp55`

Area: Pandoc JSON/native AST constructor completeness.

`NativeReader` now accepts textual Pandoc native captions where `Just` wraps the
current `ShortCaption` helper constructor:

- `Caption (Just (ShortCaption [ ... ])) [ ... ]`
- existing `Caption (Just [ ... ]) [ ... ]` remains accepted.

The focused regression covers both `Figure` and `Table` caption parsing, verifies
shared AST short/long caption metadata, confirms `PandocJsonWriter` emits the
canonical `Just`/`ShortCaption` JSON helper shape, and keeps blocks-only textual
native round trips working.

No Pandoc binary, JSON filters, Cabal/Haskell runner, office suite, TeX/browser
engine, zip/unzip tool, Node tooling, external validator, online service, or
live provider test was invoked.

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php`
  - `1 test files, 461 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `2 test files, 6739 assertions, 0 failures`

Broader gate status:

- `php tools/run-tests.php lanes/pandoc/tests`
  - `300 test files, 117538 assertions, 9649 failures`
  - Visible failures include `YamlMetadataReviewTest.php`, which also fails when
    isolated with `1 test files, 2 assertions, 2 failures`.
  - The failing YAML review area is outside this slice; touched files are limited
    to `NativeReader.php`, `NativeReaderTest.php`, and this note.
