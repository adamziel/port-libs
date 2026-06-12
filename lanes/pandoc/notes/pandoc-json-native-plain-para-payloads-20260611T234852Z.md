# JSON/native Plain and Para payload preservation

- Bead: `plib-9xn7o`
- Base: current `origin/main` `34a94da3c9`
- Scope: Pandoc JSON/native AST constructor completeness.

This slice lets the Pandoc JSON writer reuse source-tagged current `Plain` and
`Para` block constructor payloads when rereading the payload produces the same
shared AST. Reuse is guarded by current inline-shape checks, so legacy two-slot
`Link`/`Image` payloads still regenerate to the current Pandoc JSON shape.

Native writer reuse now also tries the JSON reader for current `Plain`/`Para`
payloads, matching the existing current `Header` path while preserving legacy
native-reader behavior.

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 test file, 1227 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67794 assertions, 0 failures
