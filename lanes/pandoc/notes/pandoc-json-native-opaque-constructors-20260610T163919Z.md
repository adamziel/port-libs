# Pandoc JSON/Native Opaque Constructors

Slice: `pandoc-json-native-opaque-constructors-20260610T163919Z`

This core-blocker slice closes the Pandoc JSON/native constructor fallback gap:
the native reader already preserved unrecognized tagged constructors as opaque
native AST placeholders, while the Pandoc JSON reader rejected them.

Implemented:

- `PandocJsonReader` maps unrecognized tagged block constructors to
  `native_block` nodes with `constructor` and exact `native` payload attrs.
- `PandocJsonReader` maps unrecognized tagged inline constructors to
  `native_inline` nodes with `constructor` and exact `native` payload attrs.
- `PandocJsonWriter` re-emits stored native payloads before shared-AST block
  and inline constructor matching, preserving exact JSON round trips.
- `PandocJsonNativeAstTest` covers an opaque top-level block plus an opaque
  inline inside a paragraph.

Post-rebase verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed 1 file / 395 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 44 files / 60659 assertions / 0 failures.

No Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/browser
engines, zip/unzip, external validators, online services, live provider tests,
or live-service provider tests were invoked.
