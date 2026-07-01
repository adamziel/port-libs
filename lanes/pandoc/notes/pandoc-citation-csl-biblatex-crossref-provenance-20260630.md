# Pandoc Citation/CSL BibLaTeX Crossref Provenance

Implemented one bounded native PHP Citation/CSL slice for BibLaTeX relation
metadata. `BibtexCslParser`, `BibtexCslProcessor`, and
`CitationCslProcessor` now carry crossref parent provenance through CSL item
metadata, direct bibliography rendering, CSL style variables, missing-parent
diagnostics, and WordPress bibliography output.

The slice stays inside `lanes/pandoc` and does not invoke Pandoc, citeproc,
Biber, BibTeX, CSL services, office suites, TeX engines, browsers, or network
lookups. Relation entries are summarized as metadata-only CSL review records;
source attachment and package byte exposure policy is unchanged.

Direct-format parity remains active. This closes only a bounded CSL/BibLaTeX
provenance gap; broader citeproc behavior, full style-module parity, and the
known full Pandoc lane backlog remain separate blocker work.

Focused validation passed `php -l` for the touched parser, processor, and test
files; `BibtexCslProcessorTest.php` plus `CitationCslProcessorTest.php` passed
with 6,721 assertions and 0 failures; `BibliographyReaderTest.php` passed with
28 assertions and 0 failures. `git diff --check` passed.

The full `lanes/pandoc/tests` gate remains baseline-red outside this slice:
303 files, 118,784 assertions, and 9,634 failures, with first visible failures
in DocBookReader, HtmlWriterGlobalAttributeReview, LatexWriter, and Markdown
surge suites.
