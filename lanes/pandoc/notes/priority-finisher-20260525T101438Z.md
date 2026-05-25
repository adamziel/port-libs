# Pandoc Priority Finisher - 2026-05-25 10:14 UTC

## Behavior Target

This isolated lane found stale Pandoc rework markers for older Markdown writer
Space/SoftBreak/LineBreak and table-span patches. Current accepted HEAD already
contains those behaviors and their manifest/status evidence, so this slice keeps
that evidence intact and adds the next bounded Markdown writer table branch:
multi-block table-cell fallback inside pipe-table output.

## Behavior Added

Pandoc Markdown pipe tables cannot structurally contain nested block tables.
When a table cell is rendered from block content, the native `MarkdownWriter`
now escapes unescaped pipe separators produced by the block renderer before the
outer pipe row is assembled. This preserves the existing multi-block fallback
policy of converting physical lines to `<br />` while preventing nested table
separator pipes from corrupting the parent table columns.

The focused test covers a paragraph plus nested table inside an outer table
cell, including an already escaped text pipe inside the nested row.

## WordPress Smoke

No new WordPress example was required for this slice. The user-visible
WordPress path for nested legacy table imports is already covered by the
accepted `wordpress-docbook-table-spans.php` and nested HTML table tests; this
change is specific to Markdown writer pipe-table fallback.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local
table AST, block renderer, inline renderer, pipe-table width/alignment logic,
caption renderer, and table-cell escaping. It does not activate DOCX/OpenXML,
legacy DOC/CFB, PDF, EPUB/ODT, citation, math, YAML/JSON metadata, archive,
compression, Unicode, or charset support rows.

## Verification

Focused verification for this worktree:

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-markdown-review-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
- `php lanes/pandoc/examples/wordpress-markdown-review-handoff.php | rg -n "Reviewer spacing packet|hard boundary follows|next reviewer line"`
- `git diff --check -- lanes/pandoc`

Root harness status: not run - isolated micro-slice.

## Next Task

Map another bounded Markdown writer branch after multi-block table-cell
fallback, such as additional raw block format variants or table
caption/short-caption writer edge cases with native upstream fixture parity.
