# Pandoc Wiki Unsupported Format Summary Registry Slice

Current main already includes the bounded direct-format registry accounting
case for Pandoc wiki unsupported reader/writer surfaces. This note records the
current-base verification for the superseded worker slice without adding a
second counter bump.

## Native PHP surface

- `PandocFormatRegistry::wikiUnsupportedFormatSummary()` now exposes wiki
  unsupported surface buckets for review packets.
- Unsupported input-output wiki tokens:
  `dokuwiki`, `jira`, `mediawiki`.
- Unsupported input-only wiki tokens:
  `creole`, `tikiwiki`, `twiki`, `vimwiki`.
- Unsupported output-only wiki tokens:
  `xwiki`, `zimwiki`.
- `unsupportedWikiInputFormats()` and `unsupportedWikiOutputFormats()` expose the
  direct no-native-reader/writer inventories used by the review packet.

## Scope boundary

The slice is registry metadata only. Direct wiki reader/writer parity remains
explicitly unsupported, and no native PHP wiki reader or writer implementation
is registered.

No Pandoc executable, wiki renderer, Cabal/Haskell runner, TeX/PDF engine,
browser renderer, external validator, or online service is invoked.

## Verification

```bash
php -l lanes/pandoc/src/PandocFormatRegistry.php
php -l lanes/pandoc/tests/PandocFormatRegistryTest.php
php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused current-base result:

- `PandocFormatRegistryTest`: 1 file, 650 assertions, 0 failures

Full current-base result:

- `lanes/pandoc/tests`: 42 files, 58127 assertions, 0 failures

## Lane metric

- `phpPass`: remains `2889`
- `phpFail`: `0`
- `suiteProgress`: remains `792`
- The wiki unsupported-format summary case is already represented in current
  main; this rebase does not increment mapped counters again.
