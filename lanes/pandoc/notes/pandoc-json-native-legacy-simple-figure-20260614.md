# Pandoc JSON/native legacy SimpleFigure compatibility

Slice: `pandoc-json-native-legacy-simple-figure-20260614`
Bead: `plib-ek0iz`

## Scope

This bounded JSON/native AST constructor-completeness slice covers Pandoc's
legacy `SimpleFigure` compatibility pattern:

- `Para [Image attr caption (url, "fig:title")]` is now normalized by
  `PandocJsonReader` and `NativeReader` into a shared `figure` AST node.
- The image label remains the figure caption and caption inline payload.
- The shared image title strips the legacy `fig:` marker while retaining the
  original `targetNative` tuple for provenance.
- Unchanged legacy packets are preserved through existing JSON/native writer
  native-payload reuse; generated or edited shared figures continue to use
  modern `Figure` constructors.

## Evidence

- Red-first focused check:
  `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  failed because the JSON reader returned `paragraph` for the legacy figure
  packet.
- Syntax checks:
  `php -l lanes/pandoc/src/PandocJsonReader.php`
  `php -l lanes/pandoc/src/NativeReader.php`
  `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Focused green:
  `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed with `1` file, `3130` assertions, `0` failures.
- Full Pandoc PHP suite:
  `php tools/run-tests.php lanes/pandoc/tests`
  passed with `46` files, `81378` assertions, `0` failures.

## Mapping Delta

- `phpPass`: `3476 -> 3477`
- `phpFail`: `0`
- `mappedJsonNativeLegacySimpleFigureCases`: `1`
- `jsonNativeLegacySimpleFigureAssertions`: `24`

No Pandoc binary, JSON filters, Cabal/Haskell runner, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.
