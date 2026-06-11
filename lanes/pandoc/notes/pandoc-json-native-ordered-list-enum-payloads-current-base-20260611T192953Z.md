# Pandoc JSON/native ordered-list enum payload slice 2026-06-11T192953Z

Scope: `plib-1crr0`, JSON/native AST constructor completeness.

This slice keeps ordered-list style and delimiter helper constructors
constructor-complete when Pandoc JSON/native packets use string enum payloads
instead of tagged enum objects. `PandocJsonReader` and `NativeReader` already
accepted both shapes and recorded `listStyleNative` / `listDelimiterNative`;
`PandocJsonWriter` and `NativeWriter` now reuse those recorded native payloads
when they still match the normalized shared-AST style and delimiter.

If the shared AST style or delimiter changes, writers continue to emit canonical
tagged enum constructors. That keeps edited documents deterministic while
preserving reader-captured constructor shape for unedited review packets.

Verification on current main `30462ed7c`:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed 1 test file, 1058 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 44 test files, 65564 assertions, 0 failures.

Status:

- Adds one `PandocJsonNativeAstTest` PASS case and 22 focused assertions.
- Moves `phpPass` `3104 -> 3105`; `phpFail` remains `0`.
- Does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser
  renderers, external validators, online services, live provider tests, or
  live-service provider tests.
