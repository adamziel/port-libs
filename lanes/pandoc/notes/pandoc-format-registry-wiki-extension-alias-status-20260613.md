# Pandoc Wiki Extension Alias Status Slice

Mapped one bounded `PandocFormatRegistry` status-only taxonomy slice for
remaining wiki-family input extension aliases.

## Source Truth

- Upstream inventory remains pinned to `jgm/pandoc` commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `PandocFormatRegistry::wikiExtensionInference()` remains unchanged and only
  reports upstream file inference for `.dokuwiki` and `.wiki`.
- `PandocFormatRegistry::wikiInputExtensionStatusAliases()` records status-only
  aliases for `.creole`, `.jira`, `.mediawiki`, `.tikiwiki`, `.twiki`, and
  `.vimwiki` without changing upstream extension inference.

## Native PHP Status

The alias packet returns explicit unsupported wiki reader verdicts with stable
serialized `wiki-reader-not-ported` reason payloads, empty native implementation
records, `externalToolFree=true`, and `directReaderParitySupported=false`.
This is registry evidence only; it does not register a native PHP wiki reader or
writer and does not invoke a parser.

No Pandoc executable, wiki converter, browser renderer, Node tooling, online
service, live provider, external validator, or external converter was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - 1 test file, 1784 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 78271 assertions, 0 failures
