# Pandoc CSL BibLaTeX primaryclass archive slice

## Scope

`plib-3tfr3` maps arXiv-style BibLaTeX `primaryclass`, `primary-class`, and `primary_class` fields as `archive-place`/archive class metadata. The alias now flows through both native BibTeX-to-CSL parser paths, `CitationCslProcessor` item normalization, archive summary generation, CSL sort/text variable aliases, direct CSL item aliases, and WordPress bibliography handoff.

## Verification boundary

Coverage uses native PHP parser and renderer tests with synthetic BibTeX/CSL fixtures. It does not invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser engines, Node, Jupyter, office suites, TeX/PDF engines, unzip/zip, online services, or external validators.
