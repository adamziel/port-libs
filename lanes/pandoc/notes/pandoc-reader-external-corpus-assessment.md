# Pandoc Reader External Corpus Assessment

Use `tools/pandoc-reader-external-corpus.php` to assess local readers against
public documents beyond checked-in upstream fixtures.

## Commands

Build the fast canary manifest:

```sh
php tools/pandoc-reader-external-corpus.php --mode=canary
```

Build the canary manifest and run the Haskell Pandoc comparison report:

```sh
php tools/pandoc-reader-external-corpus.php --mode=canary --run-report
```

Use `--mode=representative` for more real docs and `--mode=stress` for large
inputs. Fetched documents are stored under `.upstream-cache/`, which is ignored.

## What Gets Recorded

Each manifest row includes the local file path, source URL, byte size, SHA-256,
source kind, feature tags, Pandoc input format, and local reader options. The
comparison report adds local/Pandoc parse status, normalized native AST status,
structural metric deltas, and failure clusters grouped by likely difference type.

## Current Canary Sources

- WordPress Gutenberg README, Kubernetes docs, and mdBook Markdown docs.
- W3C MathML Core and WHATWG HTML spec pages.
- Project Gutenberg EPUB.
- Public PPTX and XLSX samples from GitHub-hosted repositories.
- Plotly CSV, plus a TSV derivative generated from the same public CSV.
- Linux man-pages roff source.
