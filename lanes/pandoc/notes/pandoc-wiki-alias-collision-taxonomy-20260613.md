# Pandoc wiki alias collision taxonomy

Bounded native PHP format-registry coverage advanced by one wiki alias collision
taxonomy slice on current main `89949a774` after the rejected child branch was
folded forward into the small-formats chunk.

`PandocFormatRegistry::wikiAliasCollisionDiagnostics()` and
`wikiAliasCollisionReviewPacket()` now expose:

- the `wiki` token-suffix collision across wiki-family reader/writer tokens
- the `.wiki` fixture-extension collision between MediaWiki and Vimwiki
- stable unsupported reader/writer reason payloads
- empty native reader/writer implementation records
- `externalToolFree=true`
- `directReaderParitySupported=false`
- `directWriterParitySupported=false`

Existing normalized wiki extension inference is unchanged: `dokuwiki => dokuwiki`
and `wiki => mediawiki`.

Verification:

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - `1` file, `423` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `534` files, `142357` assertions, `8912` failures
  - failures were outside the registry slice, concentrated in existing Markdown/HTML reader-writer surge expectations
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

No Pandoc binary, wiki converter, browser renderer, Node tooling, online service,
live provider test, or external validator was invoked.
