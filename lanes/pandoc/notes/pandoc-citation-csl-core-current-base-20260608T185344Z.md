# Pandoc Citation CSL Audiovisual Creator Slice

Date: 2026-06-08 UTC
Base: f9bba6f9c783bd48666fbc44e7bc915ba6249e5f
Micro-slice: pandoc-citation-csl-core-current-base-20260608T185344Z

## Scope

This slice adds one bounded native CSL/BibLaTeX behavior cluster for audiovisual creator roles without invoking Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runners, external bibliography managers, online services, live provider tests, or live-service provider tests.

The implementation reuses the existing native PHP BibTeX parser, CSL style validator, CSL renderer, Markdown reader, and WordPress block writer:

- BibLaTeX fields `producer`, `performer`, `narrator`, `host`, `guest`, `executiveproducer` / `executive-producer`, and `scriptwriter` / `script-writer` map into CSL name variables.
- Direct CSL item input normalizes the same roles into typed item name arrays.
- CSL `<names variable="...">`, `<text variable="...">`, and `<if is-creator="...">` now accept and render those audiovisual creator variables.
- Default bibliography role parts and `name-annotation-summary` preserve production credits and name annotations for reviewer handoff.
- `wordpress-citation-csl-audiovisual-creator-handoff.php` verifies rendered WordPress citation and bibliography blocks.

## Evidence

Baseline focused verification before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 2629 assertions, 0 failures
```

Intermediate focused run after implementation and before expectation correction:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 2655 assertions, 1 failures
```

The failure was an expected-string ordering mismatch: this processor emits name-annotation metadata before default role-name bibliography parts.

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 2663 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-audiovisual-creator-handoff.php --self-test
wordpress-citation-csl-audiovisual-creator-handoff self-test passed
```

Bookkeeping delta:

- Focused assertions: 2629 -> 2663 (+34)
- `phpPass`: 1718 -> 1719
- mapped native case: +1 citation/CSL audiovisual creator handoff case

## Non-Overlap

This does not repeat accepted citation-position, near-note, first-reference-note-number, part-number, institution, name substitute, et-al, subsequent-author, is-creator author/editor/translator, participant-role, redactor, secondary-editor role, event-organizer, entry-subtype, event-place, pagination, date-marker, or original-title slices. It is limited to audiovisual CSL creator name variables and their BibLaTeX/WordPress handoff.

## Dependency Closure

No new support component is needed. The slice reuses native PHP BibTeX parsing, CSL style XML parsing and validation, CSL item normalization/rendering, MarkdownReader citation parsing, and WordPressBlockWriter bibliography output. Full citeproc parity, broader CSL locale/style completeness, Pandoc runner parity, BibTeX/Biber execution, Cabal/Haskell test execution, external converter execution, and live-service/provider tests remain out of scope for this isolated micro-slice.
