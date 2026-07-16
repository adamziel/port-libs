# DOCX/OpenXML Mail-Merge Settings Handoff

Slice: `pandoc-docx-openxml-core-current-base-duplicate-20260609T061346Z`
Base accepted HEAD: `ad25c5c67f0859a34d555620436625e00d668451`

## Behavior

- `DocxReader` now reads bounded `w:mailMerge` metadata from `word/settings.xml`.
- Preserved review metadata includes main document type, destination, data type, query, merged-data/link-to-query flags, blank-line suppression policy, active record, subject, and check-error policy.
- Raw `w:connectString` text is not exposed in import reports; the reader records presence, byte length, and SHA-256 fingerprint instead.
- `w:dataSource` and `w:headerSource` relationship IDs are resolved against `word/_rels/settings.xml.rels`, with external target preflight and internal part existence/content-type metadata.
- The WordPress smoke confirms visible document body content still renders while mail-merge source metadata remains review-only.

## Evidence

Rework notes:

- No current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` notes existed before this slice.

Baseline focused test before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 4286 assertions, 0 failures
```

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php
1 test files, 4319 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-docx-mail-merge-settings-handoff.php --self-test
wordpress-docx-mail-merge-settings-handoff self-test passed
```

Focused delta: `+1` PHP PASS case and `+33` focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
OPC relationship parsing, DOM-based `DocxReader` settings parsing, the existing
Pandoc-like AST/import-report handoff, `WordPressBlockWriter`, and the focused
lane TestRunner. Full upstream Pandoc DOCX runner parity remains a separate
upstream-runner dependency task requiring hydrated Pandoc sources and Haskell
test executables.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external office tool, browser renderer, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted DOCX package/body parsing, OPC preflight,
document variables, attached-template settings, compatibility settings,
document defaults, numbering style links, table style inheritance/conditional
regions, chart/drawing metadata, comments/endnotes, tracked revisions,
bookmarks, field-code hyperlinks, content controls, custom XML, settings
document-policy flags, or legacy DOC/CFB work. It only closes bounded
`w:mailMerge` settings metadata and mail-merge source relationship preflight.

## Follow-Up

Useful non-overlapping DOCX/OpenXML follow-ups: latent style defaults, chart
theme/style inheritance, additional settings metadata outside accepted
mail-merge/document-variable/attached-template policy, or richer style
interaction gaps that do not require office tooling.
