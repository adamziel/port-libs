# Pandoc JSON/native NativeWriter routing sidecars

Date: 2026-06-27
Bead: plib-ltavw
Area: Pandoc JSON/native AST constructor completeness

## Scope

This slice fixes two NativeWriter output-routing gaps where AST content could
only be represented losslessly by Pandoc JSON/native constructors:

- Direct pre-tagged `Meta*` metadata values, including wrapper sidecars on
  `MetaString`, `MetaMap`, and `MetaBlocks`.
- Generated `Note` sidecar labels from native Markdown footnotes.

NativeWriter now selects JSON-native output when document metadata contains
tagged Pandoc meta constructors or when a valid note label sidecar is present,
so the existing PandocJsonWriter path preserves those constructor payloads.

## Validation

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`

Result:

- `preserves direct pre-tagged metadata payloads through json and native writers until edited`: PASS
- `preserves markdown note labels through json and native note sidecars`: PASS
- Full focused file remains baseline-red with 6,000 assertions and 9 unrelated failures outside this slice.

No upstream Pandoc, Haskell/Cabal runner, browser renderer, office suite,
external validator, online service, live provider test, or live-service provider
test was used.
