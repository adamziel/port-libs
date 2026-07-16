# pandoc-odf-open-document-core-current-base-20260608T200939Z

- Lane: `pandoc`
- Base accepted HEAD: `70d557c28daa508cdd36e70149395d52ed3b6a44`
- Scope: bounded ODF/OpenDocument `meta.xml` package modification metadata mapping.

## Behavior

`OdfReader` now preserves package-level `meta:modification-date` and
`meta:modification-time` from `office:document-meta` as
`modificationDate` and `modificationTime` metadata. The values are carried
through the document metadata payload and import report, so WordPress review
handoffs can distinguish source package revision time from rendered inline
`text:modification-date` field results.

The WordPress ODF open-document example self-test now exercises the same
metadata handoff.

## Source Truth

No hydrated local Pandoc upstream checkout was available at
`/home/claude/port-libs/.upstream-cache/pandoc` in this worker. This slice
uses the accepted ODF lane reader contract plus the OpenDocument `meta.xml`
vocabulary already used by the adjacent package-policy metadata slice.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Verification

- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 2193 assertions, 0 failures`
- Red-first focused test after adding the modification metadata expectations: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 2175 assertions, 1 failures` because `modificationDate` was absent
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 2197 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` -> `odf open document handoff self-test ok`
- Syntax: `php -l lanes/pandoc/src/OdfReader.php` -> no syntax errors
- Syntax: `php -l lanes/pandoc/tests/OdfReaderTest.php` -> no syntax errors
- Syntax: `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` -> no syntax errors
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` -> `json ok`
- Diff whitespace: `git diff --check -- lanes/pandoc` -> clean

Root harness: not run - isolated micro-slice.

## Counters

- `lane-status.json` `phpPass`: unchanged at `1795`; this extends an existing ODF PASS case rather than adding a new PASS line
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2215 -> 2216`
- ODF/OpenDocument mapped core cases: `13 -> 14`
- ODF/OpenDocument focused assertions: `295 -> 299`

## Dependency Closure

No new native PHP support component is needed. This reuses `OdfReader`
`meta.xml` parsing, `ZipPackage` XML package parts, document metadata handoff,
`MarkdownWriter`, `WordPressBlockWriter`, the focused ODF reader test, and the
existing WordPress ODF open-document example.

## Non-Overlap And Follow-Up

This avoids recent ODF content/table/field clusters: `text:drop-down`,
conditional and hidden text fields, database field/range metadata, data-pilot
metadata, named expressions, generated indexes, chart objects, tracked table
changes, heading anchors, and table style/caption behavior.

Useful follow-up stays separate: user-defined typed metadata, additional
editing metadata, table covered-cell provenance, linked-section refresh
policy, or richer form-control metadata, still without invoking external
office tools.
