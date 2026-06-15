# Pandoc JSON/native ordered-list helper variants

Slice: `plib-evhcm`

Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for ordered-list helper
constructors. The focused JSON/native AST test now verifies every
`ListNumberStyle` constructor (`DefaultStyle`, `Decimal`, `Example`,
`LowerRoman`, `UpperRoman`, `LowerAlpha`, `UpperAlpha`) crossed with every
`ListNumberDelim` constructor (`DefaultDelim`, `Period`, `OneParen`,
`TwoParens`). The case forces regenerated `OrderedList` wrappers through both
`PandocJsonWriter` and `NativeWriter` while preserving the source
`listStyleNative` and `listDelimiterNative` sidecars.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.

Verification:

- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 4227 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 85759 assertions, 0 failures`

Accounting:

- rebased current main: `94fa044c8`
- `phpPass`: `3641 -> 3642`
- `phpFail`: `0`
- `upstream.mapped`: `3676 -> 3677`
- `mappedJsonNativeConstructorCompletenessCases`: `30 -> 31`
- `jsonNativeConstructorCompletenessAssertions`: `450 -> 480`
- `mappedJsonNativeOrderedListHelperVariantCases`: `1`
- `jsonNativeOrderedListHelperVariantAssertions`: `30`
