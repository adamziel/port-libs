# Pandoc ODF Parent-Relative Link Handoff

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260606T011239Z`
- Accepted base: `1789555a75c4ac7702b8e1616910b9ccdf8c86b8`
- Upstream source truth: pinned Pandoc `ContentReader.hs` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, `read_link` calls `fixRelativeLink`, and `fixRelativeLink` strips one leading `../` path segment while keeping the rest of the URI. Source inspected at `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/ODT/ContentReader.hs`.

## Implementation

- `OdfReader::linkNode()` now normalizes `text:a` `xlink:href` values through a bounded native `fixRelativeLink()` helper.
- The helper is intentionally narrow: it drops exactly one leading `../` and leaves `./`, absolute URLs, query strings, fragments, and package media/object references outside this text-link path unchanged.
- `OdfReaderTest.php` adds a focused ODT content fixture proving AST, Markdown, and WordPress output normalize `../media/source.odt?download=1#review` to `media/source.odt?download=1#review` while preserving `./local.odt`.
- `wordpress-odf-open-document-handoff.php --self-test` now covers the same WordPress-visible parent-relative source link handoff.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed before the implementation with `1 test files, 1042 assertions, 1 failures`; the failing assertion expected `media/source.odt?download=1#review` but received `../media/source.odt?download=1#review`.
- `php -l lanes/pandoc/src/OdfReader.php`: no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`: `pandoc json ok`.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`: `1 test files, 1051 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`: `odf open document handoff self-test ok`.
- `git diff --check -- lanes/pandoc`: passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1134 -> 1135`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1586 -> 1587`.
- ODF/OpenDocument mapped cases: `10 -> 11`.
- ODF/OpenDocument focused assertion inventory: `217 -> 229`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native `OdfReader`, `ZipPackage` fixture support, shared AST, `MarkdownWriter`, and `WordPressBlockWriter`. No Pandoc, Cabal solver/build/test command, Haskell runner, stack, Word, LibreOffice, zip/unzip, external converter, online service, or live provider test was executed.

## Non-Overlap And Follow-Up

This slice is separate from accepted ODF text-tab normalization, blockquote paragraph style mapping, and frame text-box image caption recovery. It only maps the ODT `read_link` / `fixRelativeLink` behavior for `text:a` links.

Follow-up ODF work should keep richer list prefix/suffix numbering, default style inheritance, and export-side ODT writing as separate bounded slices.
