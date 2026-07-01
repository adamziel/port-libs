# DocBook bibliography citation role diagnostics

2026-07-01 / plib-5ibdr

`XmlHtmlDom::summarizeDocBookBibliography()` now carries review-only DocBook bibliography citation linkage metadata without claiming direct reader parity or invoking Pandoc/XML validators.

Coverage added:

- `citation` role-token targets and citation-text targets.
- `citerefentry` linkend/role provenance, including `refentrytitle` and `manvolnum` summaries.
- resolved, missing, and duplicate bibliography target rollups.
- bibliography-entry linkage summaries tying incoming references to title, contributor, year, and publisher metadata.
- unsupported bibliography child role summaries.

Focused validation passed with `php -l` for `XmlHtmlDom.php` and `XmlHtmlDomTest.php`, plus `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`.
