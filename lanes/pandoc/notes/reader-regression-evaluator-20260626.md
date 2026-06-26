# Pandoc Reader Regression Evaluator - 2026-06-26

## Target

- Hooked bead: `plib-s8u2i`
- Requested checkpoint context: `plainmath-parity-20260625` at `399a0e0f22a4349202023a0ffd9cf140148baf19`
- Verified checkout: `7d8fdebd9f174e2562947b7dd44e56d02de99613`
- Note: the local branch was clean but behind `origin/plainmath-parity-20260625`; `git pull --rebase` completed cleanly before verification and skipped the previously applied local PlainMath commit. A second clean `git pull --rebase` was run when the tracked branch advanced by one BibLaTeX cleanup commit during this session. The verified commit is the current tracked branch tip and contains the requested checkpoint in its ancestry.

## Command Discovery

- `composer.json` defines `composer test` as `php tools/run-tests.php`.
- `tools/run-tests.php` accepts repo-relative focused file or directory arguments.
- Existing Pandoc notes and `lane-status.json` use `php tools/run-tests.php lanes/pandoc/tests` as the full Pandoc lane command.

## Focused Reader Tests

### CsvReaderTest

Command:

```bash
php tools/run-tests.php lanes/pandoc/tests/CsvReaderTest.php
```

Outcome: passed.

Summary:

```text
1 test files, 71 assertions, 0 failures
```

Executed test names:

```text
PASS reads csv headers quoted commas escaped quotes and multiline cells into a table
PASS reads tsv without quote interpretation through the converter
PASS reads csv dialect directives ragged rows and headerless options
PASS detects semicolon csv without counting quoted commas as delimiters
PASS reads csv comments alternate quote escape encoding and inferred types
PASS reads empty csv input as an empty document with table metadata
```

### BibTexReaderTest

Command:

```bash
php tools/run-tests.php lanes/pandoc/tests/BibTexReaderTest.php
```

Outcome: passed.

Summary:

```text
1 test files, 76 assertions, 0 failures
```

Executed test names:

```text
PASS reads bibtex entries strings names and bibliography blocks into shared ast
PASS routes biblatex through the converter with biblatex field aliases
PASS resolves bibtex and biblatex inheritance dates and name particles
PASS decodes bibtex tex text and biblatex date and field aliases
PASS cleans nested biblatex tex commands date ranges and name particles
PASS returns a visible empty bibliography notice for files without entries
```

### XlsxReaderTest

Command:

```bash
php tools/run-tests.php lanes/pandoc/tests/XlsxReaderTest.php
```

Outcome: passed.

Summary:

```text
1 test files, 51 assertions, 0 failures
```

Executed test names:

```text
PASS reads xlsx workbook sheets shared strings styles and tables into shared ast
PASS reads xlsx bytes through the converter input path
```

### PptxReaderTest

Command:

```bash
php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php
```

Outcome: passed.

Summary:

```text
1 test files, 40 assertions, 0 failures
```

Executed test names:

```text
PASS reads pptx package slides text media notes and tables into shared ast
PASS reads pptx bytes through the converter input path
```

### DocxReaderTest

Command:

```bash
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
```

Outcome: passed.

Summary:

```text
1 test files, 66 assertions, 0 failures
```

Executed test names:

```text
PASS reads docx package body metadata notes headers footers and review spans into shared ast
PASS reads docx bytes through the converter input path
PASS preserves docx numbering levels styles starts and delimiters
PASS reads docx bookmarks reference fields and omml equations into shared ast
```

Focused reader total: 5 files, 304 assertions, 0 failures.

## Full Pandoc Lane

Command:

```bash
php tools/run-tests.php lanes/pandoc/tests
```

Outcome: passed.

Summary:

```text
Focused test run: 28 selected test files (root lock skipped)
28 test files, 23894 assertions, 0 failures
```

Reader coverage spot checks in the full lane output included the assigned readers:

```text
PASS reads bibtex entries strings names and bibliography blocks into shared ast
PASS cleans nested biblatex tex commands date ranges and name particles
PASS reads csv headers quoted commas escaped quotes and multiline cells into a table
PASS reads docx package body metadata notes headers footers and review spans into shared ast
PASS reads pptx package slides text media notes and tables into shared ast
PASS reads xlsx workbook sheets shared strings styles and tables into shared ast
```

## Failures

No focused reader test or full Pandoc lane failure occurred. There are no failing test names or failure output snippets to report.

## Unresolved Blockers

None.
