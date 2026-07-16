# Doctemplates Core Current Base - Wiki/Vimdoc Defaults

Slice: `pandoc-doctemplates-core-current-base-20260608T175510Z`

Accepted base: `f2ba04d4070c87822ee15c9bf00e9247a5017259`

## Source Truth

Pinned upstream Pandoc commit: `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

This slice ports the bounded one-line default writer templates:

- `data/templates/default.jira`
- `data/templates/default.dokuwiki`
- `data/templates/default.mediawiki`
- `data/templates/default.vimdoc`

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, roff renderer, TeX/PDF engine, Word, LibreOffice, zip/unzip, online service, live provider test, or live-service provider test was executed.

## Implementation

- Added embedded native `DocTemplate` defaults for Jira, DokuWiki, MediaWiki, and Vimdoc.
- Registered the defaults for direct resource lookup, `templates/default` format fallback, extension-qualified output formats, and default partial fallback discovery.
- Added focused tests for direct resources, default-format lookup, extension-qualified lookup, TOC/body/include handoff, Vimdoc filename/title/TOC/modeline metadata, and custom resource override precedence.
- Extended the WordPress doctemplate review-packet self-test for MediaWiki and Vimdoc default fallback handoff.

## Evidence

Baseline before this patch:

- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 810 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/DocTemplate.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/DocTemplateTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 835 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
- Result: `OK wordpress doctemplate review packet`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": OK" . PHP_EOL; }'`
- Result: both lane JSON files OK
- `git diff --check -- lanes/pandoc`
- Result: passed

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `DocTemplate` renderer, resource resolver, default-template registry, and extension-qualified output-format fallback. Full writer conversion for these formats remains outside this bounded doctemplate support-library slice.

## Next Non-Overlap

Doctemplate follow-up can choose remaining lightweight writer defaults or a renderer-semantics gap. It should avoid repeating default Markdown/CommonMark, AsciiDoc, Muse, Org, Texinfo, RST, BBCode, LaTeX, ConTeXt, man, ms, Beamer/reveal/legacy slides, office/EPUB, ICML, DocBook, JATS, Typst, and this Jira/DokuWiki/MediaWiki/Vimdoc cluster.
