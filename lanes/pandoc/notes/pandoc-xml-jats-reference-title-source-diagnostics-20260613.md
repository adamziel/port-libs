# Pandoc XML JATS/BITS Reference Title/Source Diagnostics

Date: 2026-06-14
Base: `92671c5f12` (`origin/main`, after DocBook media role/caption diagnostics)
Task: `plib-215dg`

## Scope

- Added bounded JATS/BITS reference `article-title`, `chapter-title`, and `source` summaries to `XmlHtmlDom::summarizeJatsFrontMatter()`.
- Added source-type summaries, duplicate title/source signals, missing title/source diagnostics, and `bibr` citation target linkage.
- Preserved `directReaderParity=false` and kept raw citation payload text blocked; the packet exposes safe metadata fields and hashes/counts, not full mixed-citation prose.
- Did not invoke Pandoc, XML validators, browsers, Node tooling, online services, live providers, or external validators.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` - 1 file, 3566 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 46 files, 80656 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
