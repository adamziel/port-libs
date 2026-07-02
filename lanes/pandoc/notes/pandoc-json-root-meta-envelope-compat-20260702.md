# Pandoc JSON Root Meta Envelope Compatibility

Slice: `pandoc-json-root-meta-envelope-compat-20260702`

## Status Delta

- `PandocJsonWriter` now accepts document-level metadata stored as a direct
  top-level `Meta` envelope and emits canonical document metadata fields rather
  than leaking the root constructor wrapper.
- Direct root `MetaMap` handling now accepts single-wrapped maps and legacy
  `unMeta` root maps while preserving child `Meta*` constructors for native JSON
  handoff.
- Existing nested tagged metadata handling remains unchanged; this slice only
  normalizes root document metadata envelopes.

## Focused Evidence

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Focused PHP smoke check for direct `Meta`, single-wrapped `MetaMap`, and
  legacy `unMeta` root `MetaMap` metadata.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  includes the new passing case but remains red on six unrelated existing
  WordPress/CSL expectations in the same file.

## Accounting

- Added `mappedPandocJsonRootMetaEnvelopeCases: 1`.
- Added `pandocJsonRootMetaEnvelopeAssertions: 15`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2883 -> 2884`.

## Scope And Exclusions

This is a bounded native PHP JSON metadata compatibility slice. It does not
invoke Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, office
suites, TeX/browser engines, zip/unzip, Jupyter, Node tooling, external
validators, online services, live provider tests, or live-service provider
tests.
