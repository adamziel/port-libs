<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubWriter
{
    private const PACKAGE_PATH = 'EPUB/package.opf';
    private const NAV_PATH = 'EPUB/nav.xhtml';
    private const CHAPTER_PATH = 'EPUB/text/chapter.xhtml';
    private const STYLESHEET_PATH = 'EPUB/styles/stylesheet.css';

    /**
     * @param array{modified?: string, date?: string, title?: string, author?: string, lang?: string, identifier?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('EPUB writer expects a document node');
        }

        $metadata = $this->metadata($document);
        $chapter = $this->chapterXhtml($document, $metadata);

        return ZipPackage::build([
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $this->containerXml()],
            ['name' => self::PACKAGE_PATH, 'data' => $this->packageOpf($metadata)],
            ['name' => self::NAV_PATH, 'data' => $this->navXhtml($document, $metadata)],
            ['name' => self::CHAPTER_PATH, 'data' => $chapter],
            ['name' => self::STYLESHEET_PATH, 'data' => $this->stylesheet()],
        ]);
    }

    /**
     * @return array{title:string, author:string, lang:string, identifier:string, modified:string}
     */
    private function metadata(AstNode $document): array
    {
        $meta = $document->attr('meta', []);
        $meta = is_array($meta) ? $meta : [];

        $title = $this->optionString('title')
            ?? $this->metaString($meta, ['title'])
            ?? $this->metaInlinesText($meta, 'titleInlines')
            ?? $this->firstHeadingText($document)
            ?? 'Untitled';
        $author = $this->optionString('author')
            ?? $this->metaString($meta, ['author', 'creator'])
            ?? 'Port Libs';
        $lang = $this->optionString('lang')
            ?? $this->metaString($meta, ['lang', 'language'])
            ?? 'en';
        $modified = $this->optionString('modified')
            ?? $this->metaString($meta, ['modified', 'date'])
            ?? gmdate('Y-m-d\TH:i:s\Z');
        $identifier = $this->optionString('identifier')
            ?? $this->metaString($meta, ['identifier', 'id'])
            ?? $this->documentIdentifier($document, $title, $author, $lang);

        return [
            'title' => $title,
            'author' => $author,
            'lang' => $lang,
            'identifier' => $identifier,
            'modified' => $this->epubTimestamp($modified),
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $keys
     */
    private function metaString(array $meta, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $value = $meta[$key];
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        return trim($item);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function metaInlinesText(array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;
        if (!is_array($value)) {
            return null;
        }

        $text = $this->plainInlineText($value);

        return $text === '' ? null : $text;
    }

    private function optionString(string $key): ?string
    {
        $value = $this->options[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function firstHeadingText(AstNode $document): ?string
    {
        foreach ($document->children as $child) {
            if ($child->type !== 'heading') {
                continue;
            }
            $text = $this->plainBlockText($child);

            return $text === '' ? null : $text;
        }

        return null;
    }

    private function documentIdentifier(AstNode $document, string $title, string $author, string $lang): string
    {
        $seed = $title . "\n" . $author . "\n" . $lang . "\n" . $this->plainBlockText($document);
        $hex = substr(hash('sha256', $seed), 0, 32);

        return sprintf(
            'urn:uuid:%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function epubTimestamp(string $value): string
    {
        $trimmed = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $trimmed) === 1) {
            return $trimmed;
        }
        $timestamp = strtotime($trimmed);

        return $timestamp === false ? gmdate('Y-m-d\TH:i:s\Z') : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * @param array{title:string, author:string, lang:string, identifier:string, modified:string} $metadata
     */
    private function chapterXhtml(AstNode $document, array $metadata): string
    {
        $body = trim((new HtmlWriter(['writerWrapText' => 'preserve']))->write($document));
        if ($body === '') {
            $body = '<p></p>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $this->esc($metadata['lang']) . '" lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '<head>' . "\n"
            . '  <title>' . $this->esc($metadata['title']) . '</title>' . "\n"
            . '  <link rel="stylesheet" type="text/css" href="../styles/stylesheet.css" />' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . $body . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    private function containerXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">' . "\n"
            . '  <rootfiles>' . "\n"
            . '    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>' . "\n"
            . '  </rootfiles>' . "\n"
            . '</container>' . "\n";
    }

    /**
     * @param array{title:string, author:string, lang:string, identifier:string, modified:string} $metadata
     */
    private function packageOpf(array $metadata): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n"
            . '    <dc:identifier id="bookid">' . $this->esc($metadata['identifier']) . '</dc:identifier>' . "\n"
            . '    <dc:title>' . $this->esc($metadata['title']) . '</dc:title>' . "\n"
            . '    <dc:creator>' . $this->esc($metadata['author']) . '</dc:creator>' . "\n"
            . '    <dc:language>' . $this->esc($metadata['lang']) . '</dc:language>' . "\n"
            . '    <meta property="dcterms:modified">' . $this->esc($metadata['modified']) . '</meta>' . "\n"
            . '  </metadata>' . "\n"
            . '  <manifest>' . "\n"
            . '    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>' . "\n"
            . '    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>' . "\n"
            . '    <item id="stylesheet" href="styles/stylesheet.css" media-type="text/css"/>' . "\n"
            . '  </manifest>' . "\n"
            . '  <spine>' . "\n"
            . '    <itemref idref="chapter"/>' . "\n"
            . '  </spine>' . "\n"
            . '</package>' . "\n";
    }

    /**
     * @param array{title:string, author:string, lang:string, identifier:string, modified:string} $metadata
     */
    private function navXhtml(AstNode $document, array $metadata): string
    {
        $entries = $this->navEntries($document, $metadata['title']);
        $items = [];
        foreach ($entries as $entry) {
            $items[] = '        <li><a href="' . $this->esc($entry['href']) . '">' . $this->esc($entry['label']) . '</a></li>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="' . $this->esc($metadata['lang']) . '" lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '<head>' . "\n"
            . '  <title>Contents</title>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '  <nav epub:type="toc" id="toc">' . "\n"
            . '    <h1>Contents</h1>' . "\n"
            . '    <ol>' . "\n"
            . implode("\n", $items) . "\n"
            . '    </ol>' . "\n"
            . '  </nav>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * @return list<array{label:string, href:string}>
     */
    private function navEntries(AstNode $document, string $title): array
    {
        $entries = [];
        foreach ($document->children as $child) {
            if ($child->type !== 'heading') {
                continue;
            }
            $label = $this->plainBlockText($child);
            if ($label === '') {
                continue;
            }
            $id = trim((string) $child->attr('id', ''));
            $entries[] = [
                'label' => $label,
                'href' => 'text/chapter.xhtml' . ($id === '' ? '' : '#' . $id),
            ];
        }

        return $entries === [] ? [['label' => $title, 'href' => 'text/chapter.xhtml']] : $entries;
    }

    private function stylesheet(): string
    {
        return <<<'CSS'
body {
  font-family: serif;
  line-height: 1.5;
}
table {
  border-collapse: collapse;
}
th,
td {
  border: 1px solid #666;
  padding: 0.25em 0.4em;
}
pre {
  white-space: pre-wrap;
}

CSS;
    }

    private function plainBlockText(AstNode $node): string
    {
        if ($node->children === []) {
            $text = $node->attr('text', '');

            return is_string($text) ? trim($text) : '';
        }

        return trim($this->plainInlineText($node->children));
    }

    /**
     * @param list<mixed> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }
            if ($node->type === 'text' || $node->type === 'code') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'softbreak' || $node->type === 'linebreak') {
                $text .= ' ';
                continue;
            }
            $text .= $this->plainInlineText($node->children);
        }

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
