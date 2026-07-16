# Doctemplates Core Current Base - Lightweight Defaults

Slice: `pandoc-doctemplates-core-current-base-20260608T184636Z`

Accepted base: `307a601051e9f25717d7e310792b824a3d11215f`

## Source Truth

Pinned upstream Pandoc commit: `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

This slice ports the bounded default writer templates:

- `data/templates/default.opml`
- `data/templates/default.djot`
- `data/templates/default.textile`
- `data/templates/default.markua`
- `data/templates/default.haddock`
- `data/templates/default.tei`
- `data/templates/default.xwiki`
- `data/templates/default.zimwiki`

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, roff renderer, TeX/PDF engine, Word, LibreOffice, zip/unzip, online service, live provider test, or live-service provider test was executed.

## Implementation

- Added embedded native `DocTemplate` defaults for OPML, Djot, Textile, Markua, Haddock, TEI, XWiki, and ZimWiki.
- Registered these defaults for direct resource lookup, `templates/default` format fallback, extension-qualified output formats, and default partial fallback discovery.
- Added focused tests covering XML structure, metadata/body/include handoff, TOC markers, direct lookup, extension-qualified lookup, default sourceDesc behavior, and custom override precedence.
- Extended the WordPress doctemplate review-packet self-test for OPML, TEI, Djot, Markua, Textile, Haddock, XWiki, and ZimWiki default fallback handoff.

## Evidence

Baseline before this patch:

- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 835 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/DocTemplate.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/DocTemplateTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 889 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
- Result: `OK wordpress doctemplate review packet`
- `php -r '$file = "lanes/pandoc/lane-status.json"; json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": OK" . PHP_EOL;'`
- Result: `lanes/pandoc/lane-status.json: OK`
- `git diff --check -- lanes/pandoc`
- Result: passed

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `DocTemplate` renderer, resource resolver, default-template registry, extension-qualified output-format fallback, and WordPress doctemplate review example. Full writer conversion for these formats remains outside this bounded doctemplate support-library slice.

## Next Non-Overlap

Doctemplate follow-up can choose another remaining default-template cluster, a default-partial resolution edge, or bounded parser parity. Avoid repeating default Markdown/CommonMark, AsciiDoc, Muse, Org, Texinfo, RST, BBCode, Jira/DokuWiki/MediaWiki/Vimdoc, and this OPML/Djot/Textile/Markua/Haddock/TEI/XWiki/ZimWiki cluster.
