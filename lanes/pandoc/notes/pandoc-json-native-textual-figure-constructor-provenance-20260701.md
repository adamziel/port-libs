# Pandoc JSON/native textual figure constructor provenance

`plib-dhqwi` preserves constructor-complete provenance for current textual
Native `Figure` blocks. `NativeReader` now records the native figure attr
tuple, current `Caption`/`ShortCaption` helper payloads, and native body block
payload before exposing the existing shared `figure` AST shape.

The capture is opt-in for `Figure` so textual `Table` caption behavior stays
unchanged in this slice. Figures that use legacy short-caption payloads or
otherwise cannot rebuild a complete current `Figure` tuple keep the semantic
caption/body attrs without retaining partial provenance sidecars.

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php lanes/pandoc/tests/NativeShortCaptionConstructorTest.php lanes/pandoc/tests/JsonReaderWriterTest.php`
  passed: 3 files, 204 assertions, 0 failures.

No Pandoc binary, JSON filters, Cabal/Haskell runners, browser renderers, office
suites, TeX/PDF engines, Typst engines, Node tooling, zip/unzip tools, online
services, or external validators were invoked.
