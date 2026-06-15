# Pandoc JSON/native NativeWriter edited structural inline regeneration

Slice: `plib-a87es`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for edited structural
inline regeneration. The focused JSON/native AST test now verifies source
`Emph` sidecars remain preserved while unchanged, then confirms both
`PandocJsonWriter` and `NativeWriter` regenerate an edited `Emph` constructor
and drop stale `reviewQueue` provenance.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4386 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86229 assertions, 0 failures`

Accounting:

- rebased current main: `cef70f4c6`
- `phpPass`: `3657 -> 3658`
- `phpFail`: `0`
- `upstream.mapped`: `3694 -> 3695`
- `mappedJsonNativeConstructorCompletenessCases`: `33 -> 34`
- `jsonNativeConstructorCompletenessAssertions`: `572 -> 575`
- `mappedJsonNativeNativeWriterEditedStructuralInlineCases`: `1`
- `jsonNativeNativeWriterEditedStructuralInlineAssertions`: `3`
