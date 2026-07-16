# Pandoc Grid Table Spans Evidence - 2026-05-23T031704Z

Session: `port-pandoc`

## Scope

- Lane: Pandoc native PHP.
- Implemented the remaining bounded `test/markdown-reader-more.txt/native`
  grid-table span slice: row/column spans and the adjacent complex multi-row
  header shape.
- Did not edit non-Pandoc lanes, `progress.md`, `porting.html`, or
  `porting-summary.json`.
- Did not invoke upstream `pandoc`, Haskell test executables, or any upstream
  binary as implementation behavior.

## Implementation

- `MarkdownReader` keeps the existing rectangular grid-table parser as the
  first path.
- Added a bounded fallback for span-shaped grid tables:
  - final columns are derived from horizontal grid geometry;
  - omitted interior top-grid dividers become `colspan`;
  - partial horizontal separators become `rowspan`;
  - multi-row header bands are emitted as multiple `table_head` rows.
- `WordPressBlockWriter` already had safe `colspan`/`rowspan` table-cell output,
  so the slice only needed reader-side span metadata and tests.

## Verification

```text
php -l lanes/pandoc/src/MarkdownReader.php
No syntax errors detected in lanes/pandoc/src/MarkdownReader.php
```

```text
Focused grid-table command:
5 focused tests, 85 assertions, 0 failures
```

```text
Full Pandoc MarkdownReaderTest.php:
1 test file, 157 tests, 1,554 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-import-markdown.php
517 output lines; output includes Grid table span import queue with
colspan="2" and rowspan="3".
```

```text
JSON validation:
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json: valid
lanes/pandoc/lane-status.json: valid
```

```text
git diff --check -- lanes/pandoc
passed
```

```text
php tools/run-tests.php
177 test files, 16,983 assertions, 0 failures
```

## Residual Risk

- Full upstream Pandoc Haskell runner remains unexecuted for the existing lane
  reason: it requires hydrating/building the upstream Tasty executables and
  dependency graph from the blob-filtered cache.
