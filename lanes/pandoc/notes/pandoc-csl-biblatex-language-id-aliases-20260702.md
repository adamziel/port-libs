# Pandoc Citation/CSL BibLaTeX Language-ID Alias Slice

Bead: `plib-5uxmy`

This slice carries the BibLaTeX-style `language-id` and compact `languageid` aliases into the existing CSL `language` metadata path. `BibtexCslProcessor` now recognizes both aliases alongside `language`, `langid`, and `hyphenation`.

The focused regression covers raw BibTeX field preservation, normalized `CitationCslProcessor` language and `languageList` fallback, CSL style variables `language`, `language-id`, `languageid`, and `language-list`, sort behavior, rendered bibliography entries, and WordPress block output.

Non-scope: this does not change primary `language` literal-list splitting, original-language lists, language options, subtitle-family metadata, container-author metadata, conference organization/event organizer mapping, external citeproc/BibTeX/Biber/Pandoc execution, or any identifier lookup.
