<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubWriter
{
    private const PACKAGE_PATH = 'EPUB/package.opf';
    private const NAV_PATH = 'EPUB/nav.xhtml';
    private const NCX_PATH = 'EPUB/toc.ncx';
    private const CHAPTER_PATH = 'EPUB/text/chapter.xhtml';
    private const TITLE_PAGE_PATH = 'EPUB/text/title_page.xhtml';
    private const STYLESHEET_PATH = 'EPUB/styles/stylesheet.css';

    /**
     * @param array{modified?: string, date?: string, title?: string, author?: string, lang?: string, identifier?: string, writerEpubTitlePage?: bool|string|int, epubTitlePage?: bool|string|int, titlePage?: bool|string|int, writerSplitLevel?: int|string|bool, splitLevel?: int|string|bool, epubSplitLevel?: int|string|bool, epubChapterLevel?: int|string|bool, mediaResources?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, resources?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, resourceMap?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, media?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, coverImage?: string, epubCoverImage?: string, epubCoverImagePath?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('EPUB writer expects a document node');
        }

        $media = $this->withMediaResources($document);
        $document = $media['document'];
        $metadata = $this->metadata($document);
        $chapterSet = $this->chapters($document, $metadata);
        $titlePage = $this->titlePage($metadata);

        $parts = [
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $this->containerXml()],
            ['name' => self::PACKAGE_PATH, 'data' => $this->packageOpf($metadata, $titlePage, $chapterSet['chapters'], $media['entries'])],
            ['name' => self::NCX_PATH, 'data' => $this->tocNcx($document, $metadata, $titlePage, $chapterSet['headingHrefs'], $chapterSet['defaultHref'])],
            ['name' => self::NAV_PATH, 'data' => $this->navXhtml($document, $metadata, $titlePage, $chapterSet['headingHrefs'], $chapterSet['defaultHref'])],
        ];
        if ($titlePage !== null) {
            $parts[] = ['name' => $titlePage['packagePath'], 'data' => $titlePage['contents']];
        }
        foreach ($chapterSet['chapters'] as $chapter) {
            $parts[] = ['name' => $chapter['packagePath'], 'data' => $chapter['contents']];
        }
        foreach ($media['entries'] as $entry) {
            $parts[] = ['name' => $entry['packagePath'], 'data' => $entry['contents']];
        }
        $parts[] = ['name' => self::STYLESHEET_PATH, 'data' => $this->stylesheet()];

        return ZipPackage::build($parts);
    }

    /**
     * @return array{title:string, titleExplicit:bool, author:string, authorExplicit:bool, lang:string, identifier:string, modified:string}
     */
    private function metadata(AstNode $document): array
    {
        $meta = $document->attr('meta', []);
        $meta = is_array($meta) ? $meta : [];

        $explicitTitle = $this->optionString('title')
            ?? $this->metaString($meta, ['title'])
            ?? $this->metaInlinesText($meta, 'titleInlines');
        $title = $explicitTitle
            ?? $this->firstHeadingText($document)
            ?? 'Untitled';
        $explicitAuthor = $this->optionString('author')
            ?? $this->metaString($meta, ['author', 'creator']);
        $author = $explicitAuthor ?? 'Port Libs';
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
            'titleExplicit' => $explicitTitle !== null,
            'author' => $author,
            'authorExplicit' => $explicitAuthor !== null,
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
     * @param array{title:string, titleExplicit:bool, author:string, authorExplicit:bool, lang:string, identifier:string, modified:string} $metadata
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

    /**
     * @param array{title:string, titleExplicit:bool, author:string, authorExplicit:bool, lang:string, identifier:string, modified:string} $metadata
     * @return array{
     *     chapters:list<array{id:string, href:string, packagePath:string, contents:string}>,
     *     headingHrefs:array<int, string>,
     *     defaultHref:string
     * }
     */
    private function chapters(AstNode $document, array $metadata): array
    {
        $chunks = $this->chapterChunks($document);
        $split = count($chunks) > 1;
        $chapterSpecs = [];
        $headingHrefs = [];
        $idChapterFiles = [];

        foreach ($chunks as $index => $children) {
            $fileName = $split ? 'ch' . ($index + 1) . '.xhtml' : basename(self::CHAPTER_PATH);
            $href = 'text/' . $fileName;
            $chapterSpecs[] = [
                'id' => $split ? 'chapter' . ($index + 1) : 'chapter',
                'href' => $href,
                'packagePath' => 'EPUB/' . $href,
                'fileName' => $fileName,
                'children' => $children,
            ];
            foreach ($children as $child) {
                $this->collectHeadingHrefs($child, $href, $headingHrefs);
                $this->collectNodeIdChapterFiles($child, $fileName, $idChapterFiles);
            }
        }

        $chapters = [];
        foreach ($chapterSpecs as $chapter) {
            $children = [];
            foreach ($chapter['children'] as $child) {
                $children[] = $this->rewriteInternalChapterLinks($child, $chapter['fileName'], $idChapterFiles);
            }
            $chapterDocument = new AstNode('document', $document->attrs, $children);
            $chapters[] = [
                'id' => $chapter['id'],
                'href' => $chapter['href'],
                'packagePath' => $chapter['packagePath'],
                'contents' => $this->chapterXhtml($chapterDocument, $metadata),
            ];
        }

        return [
            'chapters' => $chapters,
            'headingHrefs' => $headingHrefs,
            'defaultHref' => $chapters[0]['href'],
        ];
    }

    /**
     * @return list<list<AstNode>>
     */
    private function chapterChunks(AstNode $document): array
    {
        $splitLevel = $this->writerSplitLevel();
        if ($splitLevel < 1) {
            return [$document->children];
        }

        $chunks = [];
        $current = [];
        foreach ($document->children as $child) {
            if ($child->type === 'heading' && max(1, min(6, (int) $child->attr('level', 1))) <= $splitLevel) {
                if ($current !== []) {
                    $chunks[] = $current;
                }
                $current = [$child];
                continue;
            }
            $current[] = $child;
        }
        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks === [] ? [[]] : $chunks;
    }

    private function writerSplitLevel(): int
    {
        foreach (['writerSplitLevel', 'splitLevel', 'epubSplitLevel', 'epubChapterLevel'] as $key) {
            if (!array_key_exists($key, $this->options)) {
                continue;
            }

            $value = $this->options[$key];
            if ($value === false || $value === null) {
                return 0;
            }
            if ($value === true) {
                return 1;
            }
            if (is_int($value) || is_float($value)) {
                return max(0, (int) $value);
            }
            if (is_string($value) && is_numeric(trim($value))) {
                return max(0, (int) trim($value));
            }
        }

        return 1;
    }

    /**
     * @param array<int, string> $headingHrefs
     */
    private function collectHeadingHrefs(AstNode $node, string $href, array &$headingHrefs): void
    {
        if ($node->type === 'heading') {
            $headingHrefs[spl_object_id($node)] = $href;
        }
        foreach ($node->children as $child) {
            $this->collectHeadingHrefs($child, $href, $headingHrefs);
        }
    }

    /**
     * @param array<string, string> $idChapterFiles
     */
    private function collectNodeIdChapterFiles(AstNode $node, string $fileName, array &$idChapterFiles): void
    {
        $id = trim((string) $node->attr('id', ''));
        if ($id !== '' && !array_key_exists($id, $idChapterFiles)) {
            $idChapterFiles[$id] = $fileName;
        }
        foreach ($node->children as $child) {
            $this->collectNodeIdChapterFiles($child, $fileName, $idChapterFiles);
        }
    }

    /**
     * @param array<string, string> $idChapterFiles
     */
    private function rewriteInternalChapterLinks(AstNode $node, string $currentFileName, array $idChapterFiles): AstNode
    {
        $changed = false;
        $children = [];
        foreach ($node->children as $child) {
            $rewritten = $this->rewriteInternalChapterLinks($child, $currentFileName, $idChapterFiles);
            $children[] = $rewritten;
            $changed = $changed || $rewritten !== $child;
        }

        $attrs = $node->attrs;
        if ($node->type === 'link') {
            $urlKey = array_key_exists('url', $attrs) || !array_key_exists('href', $attrs) ? 'url' : 'href';
            $url = (string) ($attrs[$urlKey] ?? '');
            if (preg_match('/^#(.+)$/', $url, $match) === 1) {
                $fragment = $match[1];
                $targetId = rawurldecode($fragment);
                $targetFileName = $idChapterFiles[$targetId] ?? null;
                if ($targetFileName !== null && $targetFileName !== $currentFileName) {
                    $attrs[$urlKey] = $targetFileName . '#' . $fragment;
                    $changed = true;
                }
            }
        }

        return $changed ? new AstNode($node->type, $attrs, $children) : $node;
    }

    /**
     * @param array{title:string, titleExplicit:bool, author:string, authorExplicit:bool, lang:string, identifier:string, modified:string} $metadata
     * @return array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null
     */
    private function titlePage(array $metadata): ?array
    {
        if (!$this->epubTitlePageEnabled()) {
            return null;
        }

        return [
            'id' => 'title_page_xhtml',
            'href' => 'text/' . basename(self::TITLE_PAGE_PATH),
            'packagePath' => self::TITLE_PAGE_PATH,
            'contents' => $this->titlePageXhtml($metadata),
            'linear' => $metadata['titleExplicit'],
        ];
    }

    private function epubTitlePageEnabled(): bool
    {
        foreach (['writerEpubTitlePage', 'epubTitlePage', 'titlePage'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return $this->optionBool($this->options[$key], true);
            }
        }

        return true;
    }

    private function optionBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', 'none'], true)) {
                return false;
            }
        }

        return $default;
    }

    /**
     * @param array{title:string, titleExplicit:bool, author:string, authorExplicit:bool, lang:string, identifier:string, modified:string} $metadata
     */
    private function titlePageXhtml(array $metadata): string
    {
        $author = $metadata['authorExplicit']
            ? '    <p class="author">' . $this->esc($metadata['author']) . '</p>' . "\n"
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="' . $this->esc($metadata['lang']) . '" lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '<head>' . "\n"
            . '  <title>' . $this->esc($metadata['title']) . '</title>' . "\n"
            . '  <link rel="stylesheet" type="text/css" href="../styles/stylesheet.css" />' . "\n"
            . '</head>' . "\n"
            . '<body epub:type="frontmatter">' . "\n"
            . '  <section id="titlepage" epub:type="titlepage" role="doc-titlepage">' . "\n"
            . '    <h1 class="title">' . $this->esc($metadata['title']) . '</h1>' . "\n"
            . $author
            . '  </section>' . "\n"
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
     * @param array{title:string, titleExplicit:bool, author:string, authorExplicit:bool, lang:string, identifier:string, modified:string} $metadata
     * @param array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null $titlePage
     * @param list<array{id:string, href:string, packagePath:string, contents:string}> $chapters
     * @param list<array{id:string, href:string, packagePath:string, mediaType:string, contents:string, properties:list<string>}> $mediaEntries
     */
    private function packageOpf(array $metadata, ?array $titlePage, array $chapters, array $mediaEntries): string
    {
        $titlePageItem = '';
        $titlePageSpineItem = '';
        if ($titlePage !== null) {
            $titlePageItem = '    <item id="' . $this->esc($titlePage['id']) . '" href="' . $this->esc($titlePage['href']) . '" media-type="application/xhtml+xml"/>' . "\n";
            $titlePageSpineItem = '    <itemref idref="' . $this->esc($titlePage['id']) . '" linear="' . ($titlePage['linear'] ? 'yes' : 'no') . '"/>' . "\n";
        }

        $chapterItems = '';
        $spineItems = '';
        foreach ($chapters as $chapter) {
            $chapterItems .= '    <item id="' . $this->esc($chapter['id']) . '" href="' . $this->esc($chapter['href']) . '" media-type="application/xhtml+xml"/>' . "\n";
            $spineItems .= '    <itemref idref="' . $this->esc($chapter['id']) . '"/>' . "\n";
        }

        $mediaItems = '';
        foreach ($mediaEntries as $entry) {
            $mediaItems .= '    <item id="' . $this->esc($entry['id']) . '" href="' . $this->esc($entry['href']) . '" media-type="' . $this->esc($entry['mediaType']) . '"';
            if ($entry['properties'] !== []) {
                $mediaItems .= ' properties="' . $this->esc(implode(' ', $entry['properties'])) . '"';
            }
            $mediaItems .= '/>' . "\n";
        }

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
            . '    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>' . "\n"
            . '    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>' . "\n"
            . $titlePageItem
            . $chapterItems
            . '    <item id="stylesheet" href="styles/stylesheet.css" media-type="text/css"/>' . "\n"
            . $mediaItems
            . '  </manifest>' . "\n"
            . '  <spine toc="ncx">' . "\n"
            . $titlePageSpineItem
            . $spineItems
            . '  </spine>' . "\n"
            . '</package>' . "\n";
    }

    /**
     * @param array{title:string, titleExplicit:bool, author:string, authorExplicit:bool, lang:string, identifier:string, modified:string} $metadata
     * @param array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null $titlePage
     * @param array<int, string> $headingHrefs
     */
    private function navXhtml(AstNode $document, array $metadata, ?array $titlePage, array $headingHrefs, string $defaultHref): string
    {
        $entries = $this->navEntries($document, $metadata['title'], $headingHrefs, $defaultHref);
        $entryIndex = 0;
        $list = $this->renderNavList($entries, $entryIndex, 0, 4);
        $landmarks = $this->renderLandmarksNav($titlePage);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="' . $this->esc($metadata['lang']) . '" lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '<head>' . "\n"
            . '  <title>Contents</title>' . "\n"
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '  <nav epub:type="toc" id="toc">' . "\n"
            . '    <h1>Contents</h1>' . "\n"
            . $list . "\n"
            . '  </nav>' . "\n"
            . $landmarks
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * @param array<int, string> $headingHrefs
     * @return list<array{label:string, href:string, level:int}>
     */
    private function navEntries(AstNode $document, string $title, array $headingHrefs, string $defaultHref): array
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
            $href = $headingHrefs[spl_object_id($child)] ?? $defaultHref;
            $entries[] = [
                'label' => $label,
                'href' => $href . ($id === '' ? '' : '#' . $id),
                'level' => max(1, min(6, (int) $child->attr('level', 1))),
            ];
        }

        return $entries === [] ? [['label' => $title, 'href' => $defaultHref, 'level' => 1]] : $entries;
    }

    /**
     * @param array{title:string, titleExplicit:bool, author:string, authorExplicit:bool, lang:string, identifier:string, modified:string} $metadata
     * @param array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null $titlePage
     * @param array<int, string> $headingHrefs
     */
    private function tocNcx(AstNode $document, array $metadata, ?array $titlePage, array $headingHrefs, string $defaultHref): string
    {
        $entries = $this->navEntries($document, $metadata['title'], $headingHrefs, $defaultHref);
        $entryIndex = 0;
        $navPointIndex = 1;
        $navPoints = [];
        if ($titlePage !== null) {
            $navPoints[] = '    <navPoint id="navPoint-0">' . "\n"
                . '      <navLabel><text>' . $this->esc($metadata['title']) . '</text></navLabel>' . "\n"
                . '      <content src="' . $this->esc($titlePage['href']) . '"/>' . "\n"
                . '    </navPoint>';
        }
        $renderedEntries = $this->renderNcxNavPoints($entries, $entryIndex, 0, $navPointIndex, 4);
        if ($renderedEntries !== '') {
            $navPoints[] = $renderedEntries;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">' . "\n"
            . '  <head>' . "\n"
            . '    <meta name="dtb:uid" content="' . $this->esc($metadata['identifier']) . '"/>' . "\n"
            . '    <meta name="dtb:depth" content="1"/>' . "\n"
            . '    <meta name="dtb:totalPageCount" content="0"/>' . "\n"
            . '    <meta name="dtb:maxPageNumber" content="0"/>' . "\n"
            . '  </head>' . "\n"
            . '  <docTitle><text>' . $this->esc($metadata['title']) . '</text></docTitle>' . "\n"
            . '  <navMap>' . "\n"
            . implode("\n", $navPoints) . "\n"
            . '  </navMap>' . "\n"
            . '</ncx>' . "\n";
    }

    /**
     * @param list<array{label:string, href:string, level:int}> $entries
     */
    private function renderNcxNavPoints(array $entries, int &$index, int $parentLevel, int &$navPointIndex, int $indent): string
    {
        $lines = [];
        $count = count($entries);
        while ($index < $count) {
            $entry = $entries[$index];
            if ($entry['level'] <= $parentLevel) {
                break;
            }

            $index++;
            $id = 'navPoint-' . $navPointIndex;
            $navPointIndex++;
            $lines[] = str_repeat(' ', $indent) . '<navPoint id="' . $this->esc($id) . '">';
            $lines[] = str_repeat(' ', $indent + 2) . '<navLabel><text>' . $this->esc($entry['label']) . '</text></navLabel>';
            $lines[] = str_repeat(' ', $indent + 2) . '<content src="' . $this->esc($entry['href']) . '"/>';
            if ($index < $count && $entries[$index]['level'] > $entry['level']) {
                $nested = $this->renderNcxNavPoints($entries, $index, $entry['level'], $navPointIndex, $indent + 2);
                if ($nested !== '') {
                    $lines[] = $nested;
                }
            }
            $lines[] = str_repeat(' ', $indent) . '</navPoint>';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null $titlePage
     */
    private function renderLandmarksNav(?array $titlePage): string
    {
        $items = [];
        if ($titlePage !== null) {
            $items[] = '      <li><a href="' . $this->esc($titlePage['href']) . '" epub:type="titlepage">Title Page</a></li>';
        }
        $items[] = '      <li><a href="#toc" epub:type="toc">Table of Contents</a></li>';

        return '  <nav epub:type="landmarks" id="landmarks" hidden="hidden">' . "\n"
            . '    <h2>Landmarks</h2>' . "\n"
            . '    <ol>' . "\n"
            . implode("\n", $items) . "\n"
            . '    </ol>' . "\n"
            . '  </nav>' . "\n";
    }

    /**
     * @param list<array{label:string, href:string, level:int}> $entries
     */
    private function renderNavList(array $entries, int &$index, int $parentLevel, int $indent): string
    {
        $lines = [str_repeat(' ', $indent) . '<ol>'];
        $count = count($entries);
        while ($index < $count) {
            $entry = $entries[$index];
            if ($entry['level'] <= $parentLevel) {
                break;
            }

            $index++;
            $lines[] = str_repeat(' ', $indent + 2)
                . '<li><a href="' . $this->esc($entry['href']) . '">' . $this->esc($entry['label']) . '</a>';
            if ($index < $count && $entries[$index]['level'] > $entry['level']) {
                $lines[] = $this->renderNavList($entries, $index, $entry['level'], $indent + 4);
            }
            $lines[] = str_repeat(' ', $indent + 2) . '</li>';
        }
        $lines[] = str_repeat(' ', $indent) . '</ol>';

        return implode("\n", $lines);
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

    /**
     * @return array{
     *     document:AstNode,
     *     entries:list<array{id:string, href:string, packagePath:string, mediaType:string, contents:string, properties:list<string>}>,
     *     diagnostics:list<string>
     * }
     */
    private function withMediaResources(AstNode $document): array
    {
        $bag = new MediaBag();
        $resources = $this->mediaResourcesOption();
        $diagnostics = [];
        if ($resources !== null) {
            $filled = $bag->fillDocument($document, $resources);
            $document = $filled['document'];
            $diagnostics = array_merge($diagnostics, $filled['diagnostics']);
        } else {
            $this->loadDataUriMedia($document, $bag, $diagnostics);
        }

        if ($bag->directory() === []) {
            return ['document' => $document, 'entries' => [], 'diagnostics' => $diagnostics];
        }

        $extracted = $bag->extractMedia($document, 'media');
        $document = $extracted['document'];
        $diagnostics = array_merge($diagnostics, $extracted['diagnostics']);
        $coverImage = $this->coverImageSource($document);
        $entries = [];
        $index = 1;
        foreach ($extracted['entries'] as $entry) {
            $path = $this->normalizeMediaPath((string) $entry['path']);
            $properties = [];
            if ($this->isCoverImageEntry($entry, $path, $coverImage)) {
                $properties[] = 'cover-image';
            }
            $entries[] = [
                'id' => 'media' . $index,
                'href' => 'text/' . $path,
                'packagePath' => 'EPUB/text/' . $path,
                'mediaType' => (string) $entry['mimeType'],
                'contents' => (string) $entry['contents'],
                'properties' => $properties,
            ];
            $index++;
        }

        return ['document' => $document, 'entries' => $entries, 'diagnostics' => $diagnostics];
    }

    /**
     * @return array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>|null
     */
    private function mediaResourcesOption(): ?array
    {
        foreach (['mediaResources', 'resources', 'resourceMap', 'media'] as $key) {
            $value = $this->options[$key] ?? null;
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param list<string> $diagnostics
     */
    private function loadDataUriMedia(AstNode $node, MediaBag $bag, array &$diagnostics): void
    {
        if ($node->type === 'image' || $node->type === 'link') {
            $url = (string) $node->attr('url', $node->attr('src', ''));
            if (str_starts_with($url, 'data:') && !$bag->has($url)) {
                try {
                    $bag->insertDataUri($url);
                    $diagnostics[] = $node->type === 'link'
                        ? 'media-resource-link-loaded:data-uri'
                        : 'media-resource-loaded:data-uri';
                } catch (\InvalidArgumentException) {
                    $diagnostics[] = $node->type === 'link'
                        ? 'media-resource-link-invalid:data-uri'
                        : 'media-resource-invalid:data-uri';
                }
            }
        }

        foreach ($node->children as $child) {
            $this->loadDataUriMedia($child, $bag, $diagnostics);
        }
    }

    private function normalizeMediaPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function coverImageSource(AstNode $document): ?string
    {
        foreach (['coverImage', 'epubCoverImage', 'epubCoverImagePath'] as $key) {
            $value = $this->options[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $meta = $document->attr('meta', []);
        if (is_array($meta)) {
            foreach (['coverImage', 'epubCoverImage', 'epubCoverImagePath'] as $key) {
                $value = $meta[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isCoverImageEntry(array $entry, string $path, ?string $coverImage): bool
    {
        if ($coverImage === null) {
            return false;
        }

        $cover = $this->normalizeMediaComparisonPath($coverImage);
        foreach (['source', 'sourcePath', 'mediaPath', 'path'] as $key) {
            $value = $entry[$key] ?? null;
            if (is_string($value) && $this->normalizeMediaComparisonPath($value) === $cover) {
                return true;
            }
        }

        foreach ([$path, 'text/' . $path, 'EPUB/text/' . $path] as $candidate) {
            if ($this->normalizeMediaComparisonPath($candidate) === $cover) {
                return true;
            }
        }

        return false;
    }

    private function normalizeMediaComparisonPath(string $path): string
    {
        $path = rawurldecode(trim(str_replace('\\', '/', $path)));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        return strtolower($path);
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
