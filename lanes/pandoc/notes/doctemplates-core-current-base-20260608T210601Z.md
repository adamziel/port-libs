# Doctemplates Core Current Base - EPUB2 Default Fallback

Slice: `pandoc-doctemplates-core-current-base-20260608T210601Z`

Accepted base: `abc313637c76f7f217fa1dc23516e40d06807602`

## Source Truth

Pinned upstream Pandoc commit: `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

This slice ports the bounded default writer template:

- `data/templates/default.epub2`

The relevant upstream default-template resolver is `src/Text/Pandoc/Templates.hs`, whose `getDefaultTemplate` path resolves writer names to `templates/default.<format>`.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, TeX/PDF engine, Word, LibreOffice, zip/unzip, online service, live provider test, or live-service provider test was executed.

## Implementation

- Added embedded native `DocTemplate` default resource support for `templates/default.epub2`.
- Registered `default.epub2` for direct resource lookup, `templates/default` format fallback, extension-qualified output formats such as `epub2+smart`, and bundled default partial fallback discovery.
- Preserved the upstream EPUB2 title-page branch that tests `title.text`, distinct from the existing EPUB3 `title.type` branch.
- Added focused tests for XHTML 1.1 metadata, titlepage/body/coverpage branches, bundled `styles.citations.html` partial fallback, extension-qualified format lookup, and caller override precedence.
- Extended the WordPress doctemplate review-packet self-test for EPUB2 default fallback handoff.

## Evidence

Baseline before this patch:

- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 935 assertions, 0 failures`

Final focused verification:

- `php -l lanes/pandoc/src/DocTemplate.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/DocTemplateTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 965 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
- Result: `OK wordpress doctemplate review packet`
- `php -r '$file = "lanes/pandoc/lane-status.json"; json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": OK" . PHP_EOL;'`
- Result: `lanes/pandoc/lane-status.json: OK`
- `git diff --check -- lanes/pandoc`
- Result: passed

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native `DocTemplate` resource lookup, bundled default-template fallback, and default partial fallback for `styles.citations.html`. Full EPUB2 writer conversion, EPUB package assembly, and browser/Pandoc rendering remain outside this bounded doctemplate support-library slice.

## Next Non-Overlap

Doctemplate follow-up can choose a renderer-semantics gap or another remaining default-resource edge. Avoid repeating default Markdown/CommonMark, Beamer, man/ms, Jira/DokuWiki/MediaWiki/Vimdoc, OPML/Djot/Textile/Markua/Haddock/TEI/XWiki/ZimWiki, and this EPUB2 default fallback cluster.
