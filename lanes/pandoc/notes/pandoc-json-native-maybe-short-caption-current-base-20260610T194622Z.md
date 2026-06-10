# Pandoc JSON/native Maybe short-caption constructor slice

Bead: `plib-a22q1`
Slice time: `2026-06-10T194622Z`
Rebase base: `48391f93e30f1e2f0cc356813ab1a78020735541`

## Scope

`PandocJsonReader` now unwraps Pandoc `Just` / `Nothing` helper constructors before parsing `ShortCaption` payloads for shared Figure/Table caption metadata. This aligns JSON packet ingestion with the existing native-reader Maybe handling and keeps constructor-complete Pandoc JSON packets from falling back or failing on otherwise supported caption nodes.

The slice is limited to `lanes/pandoc` and does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, external validators, online services, zip/unzip, office suites, TeX/PDF engines, Node, Jupyter, or live provider tests.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file / 418 assertions / 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files / 61608 assertions / 0 failures
