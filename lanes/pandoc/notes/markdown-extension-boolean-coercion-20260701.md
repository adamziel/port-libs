# Markdown extension boolean coercion

Work item: `plib-k32g`

## Summary

Markdown reader and writer extension option maps now use one shared
`MarkdownFormatProfile` suffix normalizer. Associative extension values are
coerced with the same boolean tokens used by explicit format-profile options:
`1`, `true`, `yes`, and `on` enable an extension, while `0`, `false`, `no`,
and `off` disable one. Unknown scalar strings keep the previous native truthy
fallback, and indexed/string extension suffix inputs keep their existing
`+extension`/`-extension` behavior.

The focused fixture records direct-format parity for 10 mapped option cases:
four disabled reader gates, four enabled reader gates, and two writer
`line_blocks` gates. No direct-format accounting moved into shared lane status
for this narrow slice.

## Non-overlap

This slice does not change canonical format defaults, raw HTML/TeX policy,
YAML metadata, title blocks, fenced div parsing, line-block parsing, or
line-block escaping. It only removes duplicated suffix assembly and fixes
string boolean coercion for associative `extensions` maps.

## Validation

- `php -l lanes/pandoc/src/MarkdownFormatProfile.php && php -l lanes/pandoc/src/MarkdownReader.php && php -l lanes/pandoc/src/MarkdownWriter.php && php -l lanes/pandoc/tests/MarkdownExtensionBooleanCoercionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownExtensionBooleanCoercionTest.php`:
  1 file, 12 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownExtensionBooleanCoercionTest.php lanes/pandoc/tests/MarkdownReaderStrikeoutBareUriProfileCompletionTest.php lanes/pandoc/tests/MarkdownReaderEmojiExtensionProfileCompletionTest.php lanes/pandoc/tests/MarkdownReaderFlavorExtensionProfileCompletionTest.php lanes/pandoc/tests/MarkdownWriterExtensionAliasParityTest.php`:
  5 files, 180 assertions, 0 failures.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc`

Known baseline-red probes:

- Adding `lanes/pandoc/tests/MarkdownFormatProfileSurgeTest.php` to the
  extension bundle produced 6 files, 557 assertions, and 64 existing failures
  in YAML metadata, title-block, raw-attribute, and raw-HTML profile defaults.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderLineBlockProfileSurgeTest.php lanes/pandoc/tests/MarkdownWriterLineBlockCompletionSurgeTest.php lanes/pandoc/tests/MarkdownReaderContainerExtensionProfileSurgeTest.php`
  produced 3 files, 603 assertions, and 120 existing failures in fenced-div
  parsing plus line-block profile/escaping coverage.

No Pandoc binary, cmark/commonmark runner, office suite, TeX/browser engine,
archive tool, Jupyter, Node tooling, external validator, or online service was
invoked.
