# Pandoc JSON/native metadata constructor validation

Slice: native JSON metadata constructor validation for `NativeReader`.

Changes:
- Rejects unsupported string-tagged native metadata constructors before preserving the raw metadata payload.
- Recursively validates nested `MetaMap` and `MetaList` metadata constructor payloads.
- Keeps constructorless JSON-compatible metadata support for plain scalar, list, and map values.

Accounting:
- `phpPass`: `16323 -> 16324`.
- `phpFail`: remains `0`.
- `mappedJsonNativeMalformedMetadataConstructorCases`: `1`.
- `jsonNativeMalformedMetadataConstructorAssertions`: `3`.

Verification after rebase onto current `origin/main`:
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed: `1 test files, 5889 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests` passed: `195 test files, 169821 assertions, 0 failures`.

No Pandoc binary, office suite, TeX/browser engine, unzip/zip command, Jupyter, Node tooling, or external validator was invoked.
