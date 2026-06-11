# JSON/native styled inline payload current-base slice

Bead: `plib-abkk3`

Base: `9e53a22c9b`

Scope:
- Keep the Pandoc JSON/native AST path native-PHP only.
- Preserve current styled inline constructor native payloads through JSON and native writer handoff.
- Avoid invoking Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Implemented:
- `NativeWriter` now checks reusable inline native payloads with `PandocJsonReader` before falling back to `NativeReader`.
- This keeps JSON-reader AST payloads reusable for styled inline constructors whose native-reader comparison would add coalesced text provenance.
- The focused fixture covers `Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`, `SmallCaps`, `Quoted`, `Cite`, `Note`, and `Span` exact payload parity through both writers, plus stale-payload regeneration after an edit.

Verification:
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed: 1 test file, 1158 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 67153 assertions, 0 failures.
