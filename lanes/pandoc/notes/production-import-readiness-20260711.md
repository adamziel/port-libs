# Pandoc Import Production Readiness - 2026-07-11

## Evidence Collected

- The generated showcase has 93 real or upstream-derived samples across 40
  supported input formats. Its WordPress import-quality gate reports 92 pass,
  1 review, and 0 fail.
- Registry coverage is 38 of Pandoc's 51 upstream input formats, plus local
  PDF and legacy DOC readers. The registry labels 11 entries
  reader-equivalent (including aliases), 27 partial, and 13 unsupported.
- A representative public corpus fetched 17 documents without download
  failures. It covered GFM Markdown, standards HTML, EPUB, PPTX, XLSX,
  CSV/TSV, and roff man pages.
- Direct fresh-process EPUB imports succeeded at a 128 MB limit:
  Project Gutenberg Pride and Prejudice produced 1,032,868 bytes of WordPress
  blocks at a 48 MB peak; Ulysses produced 1,690,481 bytes at a 52 MB peak.
- A 1.1 MB W3C MathML HTML page converted to WordPress blocks under a 128 MB
  limit with a 112 MB peak. This is usable but leaves little headroom for
  constrained hosts.

## Representative Comparison Results

The external comparison run excluding EPUB completed for 15 files:

| Format | Exact Pandoc-native matches | Interpretation |
| --- | --- | --- |
| CSV | 1/1 | Table structure matched. |
| TSV | 1/1 | Table structure matched. |
| PPTX | 1/1 | Small public slide deck matched. |
| Markdown | 0/6 | Every file differs first in retained source-provenance attributes; Node's FS API also differs in table count (7 local, 2 Pandoc), and framework docs have smaller raw-HTML/list/link differences. |
| HTML | 0/3 | Complex standards pages expose substantive structure gaps, especially WHATWG HTML and ARIA-in-HTML; MathML Core retains headings and tables but has paragraph/link-count differences. |
| man | 0/2 | High-level heading, paragraph, list, and table metrics match, but native serialization and metadata differ. |
| XLSX | 0/1 | Sheet and table structure match; one boolean cell is TRUE locally versus Pandoc's 1.0. |

The all-17 comparison could not complete because parsing Pandoc's native output
for the first large EPUB exhausted the comparison process at 1 GB in
NativeReader. This is an audit-harness scalability defect, not a direct EPUB
import failure: both large EPUBs above imported directly within 128 MB.

## MathML

Presentation MathML is covered by the HTML and EPUB readers. A simple HTML
MathML fragment is preserved as native MathML in WordPress blocks when the
writer is configured with:

    ['writerOptions' => ['htmlMathMethod' => 'mathml']]

The default WordPress math method emits TeX-style MathJax spans instead. Complex
standards HTML should not be used as evidence of full MathML parity until the
HTML structure gaps above are resolved.

## Remaining Production Blockers

Do not claim broad arbitrary-document production readiness yet.

1. Thirteen upstream input formats have no PHP reader:
   AsciiDoc, Creole, Djot, Haddock, Muse, Org, POD, Text2Tags, Textile,
   TikiWiki, TWiki, Typst, and Vimwiki.
2. Large, real standards HTML is not semantically close enough to Pandoc. The
   reader needs focused work on document-region selection, generated navigation,
   table/list content, link preservation, and HTML attributes.
3. The external native-AST comparator must use a streaming representation or
   isolated worker processes so large EPUB comparisons cannot exhaust memory.
4. Markdown needs a provenance-aware comparison normalizer before source
   attributes can be separated from semantic differences; the Node API table
   divergence still needs a real reader fix.
5. Partial package readers need the gaps recorded in
   reader-missing-feature-audit-20260626.md closed before broad claims:
   spreadsheet formulas/styles/hidden sheets, PPTX diagrams and graphics,
   DOCX shapes/altChunk/style edge cases, RTF tables/media, and bibliography
   TeX/citeproc parity.
6. Resource policies need documented per-format ceilings and user-visible
   diagnostics. The MathML standards page's 112 MB peak shows that a 128 MB
   deployment cannot safely accept arbitrary large HTML without a guardrail.
7. PDF should remain a searchable-text import with quality review for complex
   forms, maps, scans, dense scientific layouts, and arbitrary reading order.

## Practical Claim Today

It is reasonable to describe the port as production-capable for the tested,
supported common workflows with import-quality gates and review of complex
documents. It is not yet defensible to describe it as a faithful importer for
any document or as full Pandoc reader parity.
