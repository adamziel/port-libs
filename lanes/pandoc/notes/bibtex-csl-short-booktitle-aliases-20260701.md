# BibTeX CSL short booktitle aliases

BibTeX/BibLaTeX CSL normalization now maps short book-title aliases such as `shortbooktitle`, `short-book-title`, `booktitleshort`, and `book-title-short` into `container-title-short`. The same value is available through the existing journal-abbreviation mirror used by CSL short-form rendering.

The slice covers both native BibTeX paths: `BibtexCslProcessor` and `CitationCslProcessor::fromBibtex()` via `BibtexCslParser`. CSL style rendering also accepts `shortbooktitle` and hyphenated book-title short aliases as variables for bounded review handoff.
