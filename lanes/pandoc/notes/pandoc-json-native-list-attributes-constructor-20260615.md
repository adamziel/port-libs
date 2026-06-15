# Pandoc JSON/native ListAttributes Constructor

Slice: `plib-1gelb`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for ordered-list attribute
helpers. `PandocJsonReader` and `NativeReader` now accept tagged
`ListAttributes` helper constructors for `OrderedList` attributes, including
single-wrapped payloads. `PandocJsonWriter` and `NativeWriter` preserve
unchanged tagged payloads and keep the `ListAttributes` wrapper plus list style
and delimiter helper sidecars when rebuilt ordered lists change their start
number.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4500 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 86501 assertions, 0 failures`

Accounting:

- rebased current main: `13dd1900f9`
- `phpPass`: `3668 -> 3669`
- `phpFail`: `0`
- `upstream.mapped`: `3703 -> 3704`
- `mappedJsonNativeConstructorCompletenessCases`: `35 -> 36`
- `jsonNativeConstructorCompletenessAssertions`: `639 -> 689`
- `mappedJsonNativeListAttributesConstructorCases`: `1`
- `jsonNativeListAttributesConstructorAssertions`: `50`
