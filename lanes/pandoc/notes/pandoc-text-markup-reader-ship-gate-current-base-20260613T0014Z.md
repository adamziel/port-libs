# Pandoc text markup reader ship-gate slice

Bead: `plib-joe0x`

## Summary

This slice adds executable native PHP registry accounting for the 20
wiki/roff/man/text markup reader input tokens:

`asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `man`,
`mdoc`, `mediawiki`, `muse`, `opml`, `org`, `pod`, `rst`, `t2t`, `textile`,
`tikiwiki`, `twiki`, and `vimwiki`.

`PandocFormatRegistry::textMarkupReaderShipGate()` reports:

- upstream denominator: `20`
- local passing numerator: `0`
- unsupported count: `20`
- family buckets: `wiki=7`, `roff-manual=2`, `text-markup=11`
- direct reader parity: `false`

No native PHP reader implementation is registered by this slice. The family
remains explicitly unsupported and not shippable until at least one bounded
reader implementation exists.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed: 1 file, 1106 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 45 files, 74434 assertions, 0 failures.

No Pandoc binary, wiki renderer, roff renderer, Cabal/Haskell runner, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.
