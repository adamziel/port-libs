<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdfReader
{
    public const MIMETYPE = 'application/vnd.oasis.opendocument.text';
    private const OFFICE_NS = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    private const TEXT_NS = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    private const STYLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    private const TABLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    private const DRAW_NS = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    private const SVG_NS = 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0';
    private const XLINK_NS = 'http://www.w3.org/1999/xlink';
    private const MANIFEST_NS = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    private const DC_NS = 'http://purl.org/dc/elements/1.1/';
    private const META_NS = 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
    private const FO_NS = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
    private const MATH_NS = 'http://www.w3.org/1998/Math/MathML';

    /** @var array<string, array<string, mixed>> */
    private array $trackedChanges = [];

    /** @var array<string, array<string, mixed>> */
    private array $manifestByPart = [];

    /** @var array<int, int> */
    private array $listContinuationStartCounters = [];

    private int $currentListLevel = 0;

    /**
     * @return array{
     *     document:AstNode,
     *     metadata:array<string, mixed>,
     *     manifest:list<array<string, mixed>>,
     *     styles:array<string, mixed>,
     *     listStyles:array<string, mixed>,
     *     pageLayouts:array<string, mixed>,
     *     masterPages:array<string, mixed>,
     *     media:list<array<string, mixed>>,
     *     trackedChanges:list<array<string, mixed>>,
     *     importReport:array<string, mixed>
     * }
     */
    public function readPackage(ZipPackage $package): array
    {
        $this->assertOdtMimetype($package);

        $manifest = $this->readManifest($package);
        $this->manifestByPart = $this->manifestByPart($manifest);
        $styleCatalog = $this->readStyles($package);
        $content = $this->readContent($package, $styleCatalog);
        $contentStats = $this->contentNodeStats($content['blocks']);
        $styleCatalog = $content['styleCatalog'];
        $metadata = $this->readMeta($package);
        $media = $this->mediaReport($package, $manifest);
        $encryptedItems = $this->encryptedManifestItems($manifest);

        $document = new AstNode('document', [
            'source' => 'odt',
            'metadata' => $metadata,
            'title' => (string) ($metadata['title'] ?? ''),
            'manifest' => [
                'mimetype' => self::MIMETYPE,
                'items' => $manifest,
            ],
            'styles' => [
                'count' => count($styleCatalog['styles']),
                'items' => array_values($styleCatalog['styles']),
            ],
            'listStyles' => [
                'count' => count($styleCatalog['listStyles']),
                'items' => array_values($styleCatalog['listStyles']),
            ],
            'pageLayouts' => [
                'count' => count($styleCatalog['pageLayouts']),
                'items' => array_values($styleCatalog['pageLayouts']),
            ],
            'masterPages' => [
                'count' => count($styleCatalog['masterPages']),
                'items' => array_values($styleCatalog['masterPages']),
            ],
            'trackedChanges' => [
                'count' => count($content['trackedChanges']),
                'items' => $content['trackedChanges'],
            ],
        ], $content['blocks']);

        return [
            'document' => $document,
            'metadata' => $metadata,
            'manifest' => $manifest,
            'styles' => $styleCatalog['styles'],
            'listStyles' => $styleCatalog['listStyles'],
            'pageLayouts' => $styleCatalog['pageLayouts'],
            'masterPages' => $styleCatalog['masterPages'],
            'media' => $media,
            'trackedChanges' => $content['trackedChanges'],
            'importReport' => [
                'mimetype' => self::MIMETYPE,
                'manifest' => [
                    'count' => count($manifest),
                    'items' => $manifest,
                    'missingItems' => array_values(array_filter(
                        $manifest,
                        static fn (array $item): bool => ($item['exists'] ?? false) !== true,
                    )),
                    'encryptedCount' => count($encryptedItems),
                    'encryptedItems' => $encryptedItems,
                ],
                'metadata' => $metadata,
                'styles' => [
                    'count' => count($styleCatalog['styles']),
                    'items' => array_values($styleCatalog['styles']),
                    'pageLayoutCount' => count($styleCatalog['pageLayouts']),
                    'masterPageCount' => count($styleCatalog['masterPages']),
                ],
                'listStyles' => [
                    'count' => count($styleCatalog['listStyles']),
                    'items' => array_values($styleCatalog['listStyles']),
                ],
                'pageLayouts' => [
                    'count' => count($styleCatalog['pageLayouts']),
                    'items' => array_values($styleCatalog['pageLayouts']),
                ],
                'masterPages' => [
                    'count' => count($styleCatalog['masterPages']),
                    'items' => array_values($styleCatalog['masterPages']),
                ],
                'media' => [
                    'count' => count($media),
                    'items' => $media,
                ],
                'trackedChanges' => [
                    'count' => count($content['trackedChanges']),
                    'items' => $content['trackedChanges'],
                ],
                'encryption' => [
                    'count' => count($encryptedItems),
                    'encryptedParts' => array_values(array_filter(
                        array_map(static fn (array $item): ?string => is_string($item['part'] ?? null) ? $item['part'] : null, $encryptedItems),
                        static fn (?string $part): bool => $part !== null && $part !== ''
                    )),
                    'items' => $encryptedItems,
                ],
                'content' => [
                    'blockCount' => count($content['blocks']),
                    'automaticStyleCount' => $content['automaticStyleCount'],
                    'noteCount' => $contentStats['noteCount'],
                    'bookmarkCount' => $contentStats['bookmarkCount'],
                    'bookmarkReferenceCount' => $contentStats['bookmarkReferenceCount'],
                    'referenceMarkCount' => $contentStats['referenceMarkCount'],
                    'referenceReferenceCount' => $contentStats['referenceReferenceCount'],
                    'sequenceCount' => $contentStats['sequenceCount'],
                    'fieldCount' => $contentStats['fieldCount'],
                    'citationCount' => $contentStats['citationCount'],
                    'annotationRangeCount' => $contentStats['annotationRangeCount'],
                    'trackedChangeCount' => $contentStats['trackedChangeCount'],
                    'mathCount' => $contentStats['mathCount'],
                    'sectionCount' => $contentStats['sectionCount'],
                    'linkedSectionCount' => $contentStats['linkedSectionCount'],
                    'protectedSectionCount' => $contentStats['protectedSectionCount'],
                    'continuedListCount' => $contentStats['continuedListCount'],
                ],
            ],
        ];
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        return $this->readPackage($package)['document'];
    }

    private function assertOdtMimetype(ZipPackage $package): void
    {
        if (!$package->has('mimetype')) {
            throw new \RuntimeException('ODT package is missing the root mimetype entry');
        }

        $entries = $package->localEntries();
        if ($entries === [] || $entries[0]->name !== 'mimetype') {
            throw new \RuntimeException('ODT mimetype entry must be the first local ZIP entry');
        }

        $mimetype = $package->entry('mimetype');
        if ($mimetype->compressionMethod !== 0) {
            throw new \RuntimeException('ODT mimetype entry must be stored without compression');
        }

        if ($package->read('mimetype') !== self::MIMETYPE) {
            throw new \RuntimeException('ODT mimetype entry must be application/vnd.oasis.opendocument.text');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readManifest(ZipPackage $package): array
    {
        if (!$package->has('META-INF/manifest.xml')) {
            throw new \RuntimeException('ODT package is missing META-INF/manifest.xml');
        }

        $dom = self::loadXml($package->read('META-INF/manifest.xml'), 'ODT manifest XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'manifest' || $root->namespaceURI !== self::MANIFEST_NS) {
            throw new \InvalidArgumentException('ODT manifest XML must use manifest:manifest as its root element');
        }

        $items = [];
        foreach (self::childElements($root, 'file-entry', self::MANIFEST_NS) as $entryElement) {
            $fullPath = self::attr($entryElement, self::MANIFEST_NS, 'full-path');
            if ($fullPath === '') {
                throw new \RuntimeException('ODT manifest file-entry is missing manifest:full-path');
            }

            $mediaType = self::attr($entryElement, self::MANIFEST_NS, 'media-type');
            $version = self::attr($entryElement, self::MANIFEST_NS, 'version');
            $declaredSize = self::nullableInt(self::attr($entryElement, self::MANIFEST_NS, 'size'));
            $encryptionElement = self::firstChildElement($entryElement, 'encryption-data', self::MANIFEST_NS);
            $encrypted = $encryptionElement instanceof \DOMElement;
            $part = $fullPath === '/' ? null : $this->manifestPackagePart($fullPath);
            $exists = $part === null ? true : $package->has($part);
            $zipEntry = $exists && $part !== null ? $package->entry($part) : null;

            $items[] = [
                'fullPath' => $fullPath,
                'part' => $part,
                'mediaType' => $mediaType,
                'version' => $version === '' ? null : $version,
                'exists' => $exists,
                'byteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'crc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $declaredSize,
                'encrypted' => $encrypted,
                'canExposeBytes' => !$encrypted,
                'encryption' => $encrypted ? $this->encryptionData($encryptionElement) : null,
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('ODT manifest does not contain file entries');
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function encryptionData(\DOMElement $element): array
    {
        $data = self::withoutEmpty([
            'checksumType' => self::nullable(self::attr($element, self::MANIFEST_NS, 'checksum-type')),
            'checksum' => self::nullable(self::attr($element, self::MANIFEST_NS, 'checksum')),
        ]);

        $algorithm = self::firstChildElement($element, 'algorithm', self::MANIFEST_NS);
        if ($algorithm instanceof \DOMElement) {
            $initialisationVector = self::attr($algorithm, self::MANIFEST_NS, 'initialisation-vector');
            if ($initialisationVector === '') {
                $initialisationVector = self::attr($algorithm, self::MANIFEST_NS, 'initialization-vector');
            }
            $data['algorithm'] = self::withoutEmpty([
                'name' => self::nullable(self::attr($algorithm, self::MANIFEST_NS, 'algorithm-name')),
                'initialisationVector' => self::nullable($initialisationVector),
            ]);
        }

        $keyDerivation = self::firstChildElement($element, 'key-derivation', self::MANIFEST_NS);
        if ($keyDerivation instanceof \DOMElement) {
            $data['keyDerivation'] = self::withoutEmpty([
                'name' => self::nullable(self::attr($keyDerivation, self::MANIFEST_NS, 'key-derivation-name')),
                'iterationCount' => self::nullableInt(self::attr($keyDerivation, self::MANIFEST_NS, 'iteration-count')),
                'salt' => self::nullable(self::attr($keyDerivation, self::MANIFEST_NS, 'salt')),
            ]);
        }

        $startKeyGeneration = self::firstChildElement($element, 'start-key-generation', self::MANIFEST_NS);
        if ($startKeyGeneration instanceof \DOMElement) {
            $data['startKeyGeneration'] = self::withoutEmpty([
                'name' => self::nullable(self::attr($startKeyGeneration, self::MANIFEST_NS, 'start-key-generation-name')),
                'keySize' => self::nullableInt(self::attr($startKeyGeneration, self::MANIFEST_NS, 'key-size')),
            ]);
        }

        return self::withoutEmpty($data);
    }

    /**
     * @return array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}
     */
    private function readStyles(ZipPackage $package): array
    {
        $catalog = [
            'styles' => [],
            'listStyles' => [],
            'pageLayouts' => [],
            'masterPages' => [],
        ];

        if (!$package->has('styles.xml')) {
            return $catalog;
        }

        $dom = self::loadXml($package->read('styles.xml'), 'ODT styles XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-styles' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT styles.xml must use office:document-styles as its root element');
        }

        $this->mergeStyleCollections($catalog, $this->styleCollectionsFromRoot($root));

        return $catalog;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $styleCatalog
     * @return array{blocks:list<AstNode>, styleCatalog:array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}, automaticStyleCount:int, trackedChanges:list<array<string, mixed>>}
     */
    private function readContent(ZipPackage $package, array $styleCatalog): array
    {
        if (!$package->has('content.xml')) {
            throw new \RuntimeException('ODT package is missing content.xml');
        }

        $dom = self::loadXml($package->read('content.xml'), 'ODT content XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-content' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT content.xml must use office:document-content as its root element');
        }

        $contentStyles = $this->styleCollectionsFromRoot($root);
        $this->mergeStyleCollections($styleCatalog, $contentStyles);
        $body = self::firstChildElement($root, 'body', self::OFFICE_NS);
        $text = $body instanceof \DOMElement ? self::firstChildElement($body, 'text', self::OFFICE_NS) : null;
        if (!$text instanceof \DOMElement) {
            throw new \RuntimeException('ODT content.xml is missing office:body/office:text');
        }

        $this->trackedChanges = $this->trackedChangesFromText($text);
        $this->listContinuationStartCounters = [];
        $this->currentListLevel = 0;

        return [
            'blocks' => $this->blockNodes($text, $package, $styleCatalog),
            'styleCatalog' => $styleCatalog,
            'automaticStyleCount' => count($contentStyles['styles']) + count($contentStyles['listStyles']) + count($contentStyles['pageLayouts']) + count($contentStyles['masterPages']),
            'trackedChanges' => array_values($this->trackedChanges),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readMeta(ZipPackage $package): array
    {
        if (!$package->has('meta.xml')) {
            return [];
        }

        $dom = self::loadXml($package->read('meta.xml'), 'ODT meta XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-meta' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT meta.xml must use office:document-meta as its root element');
        }

        $metaElement = self::firstChildElement($root, 'meta', self::OFFICE_NS);
        if (!$metaElement instanceof \DOMElement) {
            throw new \RuntimeException('ODT meta.xml is missing office:meta');
        }

        $metadata = [
            'keywords' => [],
            'userDefined' => [],
        ];
        foreach (self::childElements($metaElement) as $child) {
            if ($child->namespaceURI === self::DC_NS) {
                $name = $child->localName;
                $text = self::normalizedText($child);
                if (in_array($name, ['title', 'creator', 'description', 'language', 'date', 'subject'], true)) {
                    $metadata[$name] = $text;
                } else {
                    $metadata['dc:' . $name] = $text;
                }
                continue;
            }

            if ($child->namespaceURI !== self::META_NS) {
                continue;
            }

            if ($child->localName === 'keyword') {
                $metadata['keywords'][] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'initial-creator') {
                $metadata['initialCreator'] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'creation-date') {
                $metadata['created'] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'editing-cycles') {
                $metadata['editingCycles'] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'document-statistic') {
                $metadata['statistics'] = $this->documentStatistics($child);
                continue;
            }
            if ($child->localName === 'user-defined') {
                $name = self::attr($child, self::META_NS, 'name');
                if ($name !== '') {
                    $metadata['userDefined'][$name] = self::normalizedText($child);
                }
            }
        }

        if ($metadata['keywords'] === []) {
            unset($metadata['keywords']);
        }
        if ($metadata['userDefined'] === []) {
            unset($metadata['userDefined']);
        }

        return $metadata;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function trackedChangesFromText(\DOMElement $text): array
    {
        $trackedChanges = self::firstChildElement($text, 'tracked-changes', self::TEXT_NS);
        if (!$trackedChanges instanceof \DOMElement) {
            return [];
        }

        $changes = [];
        foreach (self::childElements($trackedChanges, 'changed-region', self::TEXT_NS) as $region) {
            $id = self::attr($region, self::TEXT_NS, 'id');
            if ($id === '') {
                continue;
            }

            $changeElement = $this->firstTrackedChangeElement($region);
            if (!$changeElement instanceof \DOMElement) {
                continue;
            }

            $changeInfo = self::firstChildElement($changeElement, 'change-info', self::OFFICE_NS);
            $comments = [];
            if ($changeInfo instanceof \DOMElement) {
                foreach (self::childElements($changeInfo, 'p', self::TEXT_NS) as $paragraph) {
                    $textContent = self::normalizedText($paragraph);
                    if ($textContent !== '') {
                        $comments[] = $textContent;
                    }
                }
            }

            $entry = [
                'id' => $id,
                'type' => $changeElement->localName,
                'creator' => $changeInfo instanceof \DOMElement ? $this->changeInfoText($changeInfo, 'creator') : '',
                'date' => $changeInfo instanceof \DOMElement ? $this->changeInfoText($changeInfo, 'date') : '',
                'comments' => $comments,
                'text' => $this->trackedChangeBodyText($changeElement),
            ];

            $changes[$id] = array_filter(
                $entry,
                static fn (mixed $value): bool => $value !== '' && $value !== []
            );
        }

        return $changes;
    }

    private function firstTrackedChangeElement(\DOMElement $region): ?\DOMElement
    {
        foreach (self::childElements($region) as $child) {
            if ($this->isElement($child, self::TEXT_NS, 'insertion')
                || $this->isElement($child, self::TEXT_NS, 'deletion')
                || $this->isElement($child, self::TEXT_NS, 'format-change')
            ) {
                return $child;
            }
        }

        return null;
    }

    private function changeInfoText(\DOMElement $changeInfo, string $name): string
    {
        $element = self::firstChildElement($changeInfo, $name, self::DC_NS);

        return $element instanceof \DOMElement ? self::normalizedText($element) : '';
    }

    private function trackedChangeBodyText(\DOMElement $changeElement): string
    {
        $parts = [];
        foreach ($changeElement->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isElement($child, self::OFFICE_NS, 'change-info')) {
                continue;
            }
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text = trim($child->textContent);
                if ($text !== '') {
                    $parts[] = $text;
                }
                continue;
            }
            if ($child instanceof \DOMElement) {
                $text = self::normalizedText($child);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        $text = implode(' ', $parts);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return array<string, int>
     */
    private function documentStatistics(\DOMElement $element): array
    {
        $statistics = [];
        foreach (['page-count', 'table-count', 'image-count', 'object-count', 'paragraph-count', 'word-count', 'character-count'] as $name) {
            $value = self::attr($element, self::META_NS, $name);
            if ($value !== '' && ctype_digit($value)) {
                $statistics[self::camelCase($name)] = (int) $value;
            }
        }

        return $statistics;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function blockNodes(\DOMElement $parent, ZipPackage $package, array $catalog): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'h')) {
                $blocks[] = $this->headingNode($child, $catalog, $package);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'p')) {
                $paragraph = $this->paragraphNode($child, $catalog, $package);
                if ($paragraph !== null) {
                    $blocks[] = $paragraph;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'list')) {
                $blocks[] = $this->listNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'section')) {
                $blocks[] = $this->sectionNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TABLE_NS, 'table')) {
                $blocks[] = $this->tableNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'frame')) {
                $block = $this->frameBlockNode($child, $package, $catalog);
                if ($block !== null) {
                    $blocks[] = $block;
                }
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation')) {
                $blocks[] = $this->annotationBlockNode($child, $package, $catalog);
            }
        }

        return $blocks;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function headingNode(\DOMElement $heading, array $catalog, ?ZipPackage $package = null): AstNode
    {
        $styleName = self::attr($heading, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $level = self::intAttr($heading, self::TEXT_NS, 'outline-level', (int) ($style['headingLevel'] ?? 1));
        $level = max(1, min(6, $level));
        $attrs = [
            'level' => $level,
            'sourceFormat' => 'odt',
        ];
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['style'] = $style;
        }

        return new AstNode('heading', $attrs, $this->coalesceTextNodes($this->inlineNodes($heading, $catalog, $package)));
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function paragraphNode(\DOMElement $paragraph, array $catalog, ?ZipPackage $package = null): ?AstNode
    {
        $styleName = self::attr($paragraph, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $inlines = $this->coalesceTextNodes($this->inlineNodes($paragraph, $catalog, $package));
        $text = $this->plainInlineText($inlines);
        if ($inlines === [] && trim($text) === '') {
            return null;
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'text' => $text,
        ];
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['style'] = $style;
        }

        $headingLevel = (int) ($style['headingLevel'] ?? 0);
        if ($headingLevel > 0) {
            $attrs['level'] = max(1, min(6, $headingLevel));

            return new AstNode('heading', $attrs, $inlines);
        }

        return new AstNode('paragraph', $attrs, $inlines);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function listNode(\DOMElement $list, ZipPackage $package, array $catalog): AstNode
    {
        $previousLevel = $this->currentListLevel;
        $this->currentListLevel++;

        try {
            return $this->listNodeAtCurrentLevel($list, $package, $catalog);
        } finally {
            $this->currentListLevel = $previousLevel;
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function listNodeAtCurrentLevel(\DOMElement $list, ZipPackage $package, array $catalog): AstNode
    {
        $styleName = self::attr($list, self::TEXT_NS, 'style-name');
        $level = max(1, $this->currentListLevel);
        $definition = $this->listDefinition($styleName, $level, $catalog);
        $ordered = $definition['type'] === 'number';
        $continueNumbering = strtolower(self::attr($list, self::TEXT_NS, 'continue-numbering')) === 'true';
        $defaultStart = max(1, (int) ($definition['start'] ?? 1));
        $start = $continueNumbering
            ? max(1, $this->listContinuationStartCounters[$level] ?? $defaultStart)
            : $defaultStart;
        $attrs = [
            'sourceFormat' => 'odt',
            'listLevel' => $level,
        ];
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
        }
        if ($continueNumbering) {
            $attrs['continued'] = true;
        }
        if ($ordered) {
            $attrs['style'] = $this->orderedListStyle((string) ($definition['format'] ?? '1'));
            $attrs['start'] = $start;
        } else {
            $attrs['format'] = (string) ($definition['bulletChar'] ?? 'bullet');
        }

        $items = [];
        foreach (self::childElements($list) as $child) {
            if (!$this->isElement($child, self::TEXT_NS, 'list-item') && !$this->isElement($child, self::TEXT_NS, 'list-header')) {
                continue;
            }

            $itemBlocks = $this->blockNodes($child, $package, $catalog);
            $items[] = new AstNode('list_item', [
                'sourceFormat' => 'odt',
            ], $itemBlocks);
        }

        $this->listContinuationStartCounters[$level] = $start + count($items);

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function sectionNode(\DOMElement $section, ZipPackage $package, array $catalog): AstNode
    {
        $name = self::attr($section, self::TEXT_NS, 'name');
        $styleName = self::attr($section, self::TEXT_NS, 'style-name');
        $classes = ['odf-section'];
        $attributes = [];
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => $classes,
            'attributes' => $attributes,
        ];
        if ($name !== '') {
            $attrs['id'] = self::slug($name);
            $attrs['attributes']['data-odf-section-name'] = $name;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['attributes']['data-odf-section-style-name'] = $styleName;
        }

        $protectedValue = strtolower(self::attr($section, self::TEXT_NS, 'protected'));
        if ($protectedValue !== '') {
            $protected = in_array($protectedValue, ['true', '1'], true);
            $attrs['protected'] = $protected;
            $attrs['attributes']['data-odf-section-protected'] = $protected ? 'true' : 'false';
        }

        $protectionKey = self::attr($section, self::TEXT_NS, 'protection-key');
        if ($protectionKey !== '') {
            $attrs['protectionKeyPresent'] = true;
            $attrs['attributes']['data-odf-section-protection-key-present'] = 'true';
        }
        $digestAlgorithm = self::attr($section, self::TEXT_NS, 'protection-key-digest-algorithm');
        if ($digestAlgorithm !== '') {
            $attrs['protectionKeyDigestAlgorithm'] = $digestAlgorithm;
            $attrs['attributes']['data-odf-section-protection-key-digest-algorithm'] = $digestAlgorithm;
        }

        $sectionSource = self::firstChildElement($section, 'section-source', self::TEXT_NS);
        if ($sectionSource instanceof \DOMElement) {
            $source = $this->sectionSourceMetadata($sectionSource);
            if ($source !== []) {
                $attrs['sectionSource'] = $source;
                $attrs['classes'][] = 'odf-linked-section';
                foreach ($source as $name => $value) {
                    $attributeName = $name === 'sectionName' ? 'name' : self::kebabCase($name);
                    $attrs['attributes']['data-odf-section-source-' . $attributeName] = $value;
                }
            }
        }
        if (($attrs['protected'] ?? false) === true) {
            $attrs['classes'][] = 'odf-protected-section';
        }

        return new AstNode('div', $attrs, $this->blockNodes($section, $package, $catalog));
    }

    /**
     * @return array<string, string>
     */
    private function sectionSourceMetadata(\DOMElement $sectionSource): array
    {
        $source = self::withoutEmpty([
            'href' => self::nullable(self::attr($sectionSource, self::XLINK_NS, 'href')),
            'sectionName' => self::nullable(self::attr($sectionSource, self::TEXT_NS, 'section-name')),
            'filterName' => self::nullable(self::attr($sectionSource, self::TEXT_NS, 'filter-name')),
            'type' => self::nullable(self::attr($sectionSource, self::XLINK_NS, 'type')),
            'show' => self::nullable(self::attr($sectionSource, self::XLINK_NS, 'show')),
            'actuate' => self::nullable(self::attr($sectionSource, self::XLINK_NS, 'actuate')),
        ]);

        return array_map(static fn (mixed $value): string => (string) $value, $source);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function tableNode(\DOMElement $table, ZipPackage $package, array $catalog): AstNode
    {
        $columnWidths = $this->tableColumnWidths($table, $catalog);
        $children = [];
        $headerRows = [];
        $bodyRows = [];
        foreach (self::childElements($table) as $child) {
            if ($this->isElement($child, self::TABLE_NS, 'table-header-rows')) {
                foreach (self::childElements($child, 'table-row', self::TABLE_NS) as $row) {
                    array_push($headerRows, ...$this->repeatedRows($row, $package, $catalog));
                }
                continue;
            }
            if ($this->isElement($child, self::TABLE_NS, 'table-row')) {
                array_push($bodyRows, ...$this->repeatedRows($child, $package, $catalog));
            }
        }

        if ($headerRows !== []) {
            $children[] = new AstNode('table_head', [], $headerRows);
        }
        $children[] = new AstNode('table_body', [], $bodyRows);

        $attrs = [
            'sourceFormat' => 'odt',
            'caption' => '',
        ];
        if ($columnWidths !== []) {
            $attrs['widths'] = $columnWidths;
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function repeatedRows(\DOMElement $row, ZipPackage $package, array $catalog): array
    {
        $repeat = min(32, max(1, self::intAttr($row, self::TABLE_NS, 'number-rows-repeated', 1)));
        $rows = [];
        for ($index = 0; $index < $repeat; $index++) {
            $rows[] = $this->tableRowNode($row, $package, $catalog);
        }

        return $rows;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function tableRowNode(\DOMElement $row, ZipPackage $package, array $catalog): AstNode
    {
        $cells = [];
        foreach (self::childElements($row) as $cellElement) {
            if ($this->isElement($cellElement, self::TABLE_NS, 'covered-table-cell')) {
                continue;
            }
            if (!$this->isElement($cellElement, self::TABLE_NS, 'table-cell')) {
                continue;
            }

            $repeat = min(32, max(1, self::intAttr($cellElement, self::TABLE_NS, 'number-columns-repeated', 1)));
            for ($index = 0; $index < $repeat; $index++) {
                $cells[] = $this->tableCellNode($cellElement, $package, $catalog);
            }
        }

        return new AstNode('table_row', [], $cells);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function tableCellNode(\DOMElement $cell, ZipPackage $package, array $catalog): AstNode
    {
        $blocks = $this->blockNodes($cell, $package, $catalog);
        $attrs = [
            'sourceFormat' => 'odt',
            'text' => $this->plainBlockText($blocks),
        ];
        $colspan = self::intAttr($cell, self::TABLE_NS, 'number-columns-spanned', 1);
        $rowspan = self::intAttr($cell, self::TABLE_NS, 'number-rows-spanned', 1);
        $styleName = self::attr($cell, self::TABLE_NS, 'style-name');
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<float>
     */
    private function tableColumnWidths(\DOMElement $table, array $catalog): array
    {
        $widths = [];
        foreach (self::childElements($table, 'table-column', self::TABLE_NS) as $column) {
            $repeat = min(32, max(1, self::intAttr($column, self::TABLE_NS, 'number-columns-repeated', 1)));
            $style = $this->resolveStyle(self::attr($column, self::TABLE_NS, 'style-name'), $catalog);
            $width = $this->lengthToPoints((string) ($style['tableColumnProperties']['columnWidth'] ?? ''));
            for ($index = 0; $index < $repeat; $index++) {
                $widths[] = $width;
            }
        }

        $positive = array_values(array_filter($widths, static fn (?float $width): bool => $width !== null && $width > 0.0));
        if ($positive === []) {
            return [];
        }

        $total = array_sum($positive);
        if ($total <= 0.0) {
            return [];
        }

        return array_map(static fn (float $width): float => $width / $total, $positive);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function frameBlockNode(\DOMElement $frame, ZipPackage $package, array $catalog): ?AstNode
    {
        $textBox = self::firstChildElement($frame, 'text-box', self::DRAW_NS);
        if ($textBox instanceof \DOMElement) {
            $name = self::attr($frame, self::DRAW_NS, 'name');
            $attrs = [
                'sourceFormat' => 'odt',
                'classes' => ['odf-text-box'],
                'attributes' => [],
            ];
            if ($name !== '') {
                $attrs['attributes']['data-odf-frame-name'] = $name;
            }

            return new AstNode('div', $attrs, $this->blockNodes($textBox, $package, $catalog));
        }

        $math = $this->frameObjectMathNode($frame, $package);
        if ($math instanceof AstNode) {
            return new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => (string) $math->attr('text', ''),
            ], [$math]);
        }

        $image = $this->frameImageNode($frame, $package);
        if (!$image instanceof AstNode) {
            return null;
        }

        return new AstNode('figure', [
            'sourceFormat' => 'odt',
            'caption' => (string) $image->attr('alt', ''),
        ], [$image]);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function annotationBlockNode(\DOMElement $annotation, ZipPackage $package, array $catalog): AstNode
    {
        $note = $this->annotationNoteNode($annotation, $package, $catalog);

        return new AstNode('blockquote', [
            'sourceFormat' => 'odt',
            'classes' => ['odf-annotation'],
        ], $note->children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMElement $parent, array $catalog, ?ZipPackage $package = null): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        return $this->inlineNodesFromNodeList($children, $catalog, $package);
    }

    /**
     * @param list<\DOMNode> $children
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function inlineNodesFromNodeList(array $children, array $catalog, ?ZipPackage $package = null): array
    {
        $nodes = [];
        for ($index = 0, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if ($child->textContent !== '') {
                    $nodes[] = new AstNode('text', ['text' => $child->textContent]);
                }
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'change-start')) {
                $changeId = self::attr($child, self::TEXT_NS, 'change-id');
                $range = $changeId === '' ? null : $this->trackedChangeRange($children, $index, $changeId);
                if ($range !== null) {
                    $inner = $this->coalesceTextNodes($this->inlineNodesFromNodeList($range['nodes'], $catalog, $package));
                    $node = $this->trackedChangeSpanNode($changeId, $inner);
                    if ($node instanceof AstNode) {
                        $nodes[] = $node;
                    }
                    $index = $range['endIndex'];
                    continue;
                }
            }
            if ($this->isElement($child, self::TEXT_NS, 'change')) {
                $changeId = self::attr($child, self::TEXT_NS, 'change-id');
                $node = $this->standaloneTrackedChangeNode($changeId);
                if ($node instanceof AstNode) {
                    $nodes[] = $node;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'change-end')) {
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'span')) {
                array_push($nodes, ...$this->spanNodes($child, $catalog, $package));
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'a')) {
                $attrs = ['url' => self::attr($child, self::XLINK_NS, 'href')];
                $title = self::attr($child, self::OFFICE_NS, 'title');
                if ($title !== '') {
                    $attrs['title'] = $title;
                }
                $nodes[] = new AstNode('link', $attrs, $this->coalesceTextNodes($this->inlineNodes($child, $catalog, $package)));
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 's')) {
                $spaceCount = max(1, self::intAttr($child, self::TEXT_NS, 'c', 1));
                $nodes[] = new AstNode('text', ['text' => str_repeat(' ', $spaceCount)]);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'tab')) {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'line-break')) {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if ($this->isTextFieldElement($child)) {
                $field = $this->fieldNode($child, $catalog, $package);
                if ($field instanceof AstNode) {
                    $nodes[] = $field;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'sequence')) {
                $sequence = $this->sequenceNode($child, $catalog, $package);
                if ($sequence instanceof AstNode) {
                    $nodes[] = $sequence;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'bibliography-mark')) {
                $citation = $this->bibliographyMarkNode($child, $catalog, $package);
                if ($citation instanceof AstNode) {
                    $nodes[] = $citation;
                }
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'frame')) {
                $image = $this->frameImageNode($child, $package);
                if ($image instanceof AstNode) {
                    $nodes[] = $image;
                    continue;
                }
                $math = $this->frameObjectMathNode($child, $package);
                if ($math instanceof AstNode) {
                    $nodes[] = $math;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'note')) {
                $nodes[] = $this->noteNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'bookmark-start') || $this->isElement($child, self::TEXT_NS, 'bookmark')) {
                $bookmark = $this->bookmarkAnchorNode($child);
                if ($bookmark instanceof AstNode) {
                    $nodes[] = $bookmark;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'reference-mark') || $this->isElement($child, self::TEXT_NS, 'reference-mark-start')) {
                $referenceMark = $this->referenceMarkAnchorNode($child);
                if ($referenceMark instanceof AstNode) {
                    $nodes[] = $referenceMark;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'reference-mark-end')) {
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'bookmark-ref')) {
                $bookmarkRef = $this->bookmarkReferenceNode($child, $catalog, $package);
                if ($bookmarkRef instanceof AstNode) {
                    $nodes[] = $bookmarkRef;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'reference-ref')) {
                $reference = $this->referenceReferenceNode($child, $catalog, $package);
                if ($reference instanceof AstNode) {
                    $nodes[] = $reference;
                }
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation')) {
                $annotationName = self::attr($child, self::OFFICE_NS, 'name');
                $range = $annotationName === '' ? null : $this->annotationRange($children, $index, $annotationName);
                if ($range !== null) {
                    $inner = $this->coalesceTextNodes($this->inlineNodesFromNodeList($range['nodes'], $catalog, $package));
                    $node = $this->annotationRangeSpanNode($child, $annotationName, $inner, $catalog, $package);
                    if ($node instanceof AstNode) {
                        $nodes[] = $node;
                    }
                    $index = $range['endIndex'];
                    continue;
                }

                $nodes[] = $this->annotationNoteNode($child, null, $catalog);
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation-end')) {
                continue;
            }
        }

        return $nodes;
    }

    private function isTextFieldElement(\DOMElement $element): bool
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return false;
        }

        return in_array($element->localName, [
            'variable-set',
            'variable-get',
            'user-field-get',
            'expression',
            'page-number',
            'page-count',
            'date',
            'time',
        ], true);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function fieldNode(\DOMElement $field, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($field, $catalog, $package));
        $metadata = $this->fieldMetadata($field);
        if ($children === []) {
            $text = $this->fieldFallbackText($field, $metadata);
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $fieldType = $field->localName;
        $attributes = [
            'data-odf-field-type' => $fieldType,
        ];
        foreach ($metadata as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $attributes['data-odf-field-' . self::kebabCase((string) $name)] = is_bool($value)
                ? ($value ? 'true' : 'false')
                : (string) $value;
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'fieldType' => $fieldType,
            'fieldMetadata' => $metadata,
            'classes' => ['odf-field', 'odf-field-' . $fieldType],
            'attributes' => $attributes,
        ];
        if (isset($metadata['name']) && is_string($metadata['name']) && $metadata['name'] !== '') {
            $attrs['fieldName'] = $metadata['name'];
        }

        return new AstNode('span', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldMetadata(\DOMElement $field): array
    {
        $dateValue = self::attr($field, self::TEXT_NS, 'date-value');
        if ($dateValue === '') {
            $dateValue = self::attr($field, self::OFFICE_NS, 'date-value');
        }
        $timeValue = self::attr($field, self::TEXT_NS, 'time-value');
        if ($timeValue === '') {
            $timeValue = self::attr($field, self::OFFICE_NS, 'time-value');
        }
        $fixed = self::attr($field, self::TEXT_NS, 'fixed');

        $metadata = self::withoutEmpty([
            'name' => self::nullable(self::attr($field, self::TEXT_NS, 'name')),
            'formula' => self::nullable(self::attr($field, self::TEXT_NS, 'formula')),
            'valueType' => self::nullable(self::attr($field, self::OFFICE_NS, 'value-type')),
            'value' => self::nullable(self::attr($field, self::OFFICE_NS, 'value')),
            'stringValue' => self::nullable(self::attr($field, self::OFFICE_NS, 'string-value')),
            'dateValue' => self::nullable($dateValue),
            'timeValue' => self::nullable($timeValue),
            'selectPage' => self::nullable(self::attr($field, self::TEXT_NS, 'select-page')),
            'pageAdjust' => self::nullable(self::attr($field, self::TEXT_NS, 'page-adjust')),
            'styleName' => self::nullable(self::attr($field, self::STYLE_NS, 'data-style-name')),
        ]);

        if ($fixed !== '') {
            $metadata['fixed'] = in_array(strtolower($fixed), ['true', '1'], true);
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function fieldFallbackText(\DOMElement $field, array $metadata): string
    {
        $text = self::normalizedText($field);
        if ($text !== '') {
            return $text;
        }

        foreach (['stringValue', 'value', 'dateValue', 'timeValue'] as $name) {
            $value = $metadata[$name] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function sequenceNode(\DOMElement $sequence, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($sequence, $catalog, $package));
        if ($children === []) {
            $text = self::normalizedText($sequence);
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $attributes = [];
        foreach ([
            'name' => 'data-odf-sequence-name',
            'formula' => 'data-odf-sequence-formula',
            'ref-name' => 'data-odf-sequence-ref-name',
            'num-format' => 'data-odf-sequence-num-format',
        ] as $source => $target) {
            $value = self::attr($sequence, self::TEXT_NS, $source);
            if ($value !== '') {
                $attributes[$target] = $value;
            }
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'classes' => ['odf-sequence'],
            'attributes' => $attributes,
        ], $children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function bibliographyMarkNode(\DOMElement $mark, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $identifier = trim(self::attr($mark, self::TEXT_NS, 'identifier'));
        if ($identifier === '') {
            return null;
        }

        $children = $this->coalesceTextNodes($this->inlineNodes($mark, $catalog, $package));
        $displayText = $this->plainInlineText($children);
        if ($displayText === '') {
            $displayText = self::normalizedText($mark);
        }

        $sourceText = '[@' . $identifier . ']';
        if ($children === []) {
            $children = [new AstNode('text', ['text' => $displayText === '' ? $sourceText : $displayText])];
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'id' => $identifier,
            'text' => $sourceText,
            'mode' => 'normal',
            'displayText' => $displayText === '' ? $sourceText : $displayText,
        ];
        $number = self::nullableInt(self::attr($mark, self::TEXT_NS, 'number'));
        if ($number !== null) {
            $attrs['citationNumber'] = $number;
        }

        return new AstNode('citation', $attrs, $children);
    }

    /**
     * @param list<\DOMNode> $children
     * @return ?array{nodes:list<\DOMNode>, endIndex:int}
     */
    private function trackedChangeRange(array $children, int $startIndex, string $changeId): ?array
    {
        $range = [];
        for ($index = $startIndex + 1, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMElement && $this->isElement($child, self::TEXT_NS, 'change-end')) {
                $endChangeId = self::attr($child, self::TEXT_NS, 'change-id');
                if ($endChangeId === $changeId) {
                    return [
                        'nodes' => $range,
                        'endIndex' => $index,
                    ];
                }
            }

            $range[] = $child;
        }

        return null;
    }

    /**
     * @param list<AstNode> $children
     */
    private function trackedChangeSpanNode(string $changeId, array $children): ?AstNode
    {
        if ($changeId === '') {
            return null;
        }

        $change = $this->trackedChanges[$changeId] ?? [
            'id' => $changeId,
            'type' => 'change',
        ];
        if ($children === []) {
            $text = (string) ($change['text'] ?? '');
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $type = $this->trackedChangeType($change);
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => ['odf-change', 'odf-' . $type],
            'attributes' => [
                'data-odf-change-id' => $changeId,
                'data-odf-change-type' => $type,
            ],
        ];
        foreach (['creator' => 'data-odf-change-creator', 'date' => 'data-odf-change-date'] as $source => $target) {
            $value = (string) ($change[$source] ?? '');
            if ($value !== '') {
                $attrs['attributes'][$target] = $value;
            }
        }

        return new AstNode('span', $attrs, $children);
    }

    private function standaloneTrackedChangeNode(string $changeId): ?AstNode
    {
        if ($changeId === '' || !isset($this->trackedChanges[$changeId])) {
            return null;
        }

        $change = $this->trackedChanges[$changeId];
        if ($this->trackedChangeType($change) !== 'deletion') {
            return $this->trackedChangeSpanNode($changeId, []);
        }

        $text = (string) ($change['text'] ?? '');
        if ($text === '') {
            return null;
        }

        return $this->trackedChangeSpanNode($changeId, [new AstNode('text', ['text' => $text])]);
    }

    /**
     * @param array<string, mixed> $change
     */
    private function trackedChangeType(array $change): string
    {
        $type = (string) ($change['type'] ?? 'change');
        $type = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $type) ?? 'change');
        $type = trim($type, '-');

        return $type === '' ? 'change' : $type;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function spanNodes(\DOMElement $span, array $catalog, ?ZipPackage $package = null): array
    {
        $styleName = self::attr($span, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $children = $this->coalesceTextNodes($this->inlineNodes($span, $catalog, $package));
        if ($children === []) {
            return [];
        }

        if ($styleName !== '') {
            $children = [new AstNode('span', [
                'sourceFormat' => 'odt',
                'styleName' => $styleName,
                'attributes' => ['data-odf-style-name' => $styleName],
            ], $children)];
        }

        $properties = $style['textProperties'] ?? [];
        if (!is_array($properties)) {
            return $children;
        }

        if (($properties['bold'] ?? false) === true) {
            $children = [new AstNode('strong', [], $children)];
        }
        if (($properties['italic'] ?? false) === true) {
            $children = [new AstNode('emph', [], $children)];
        }
        if (($properties['underline'] ?? false) === true) {
            $children = [new AstNode('underline', [], $children)];
        }
        if (($properties['strikeout'] ?? false) === true) {
            $children = [new AstNode('strikeout', [], $children)];
        }
        if (($properties['smallCaps'] ?? false) === true) {
            $children = [new AstNode('small_caps', [], $children)];
        }

        return $children;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function noteNode(\DOMElement $note, ?ZipPackage $package, array $catalog): AstNode
    {
        $noteClass = self::attr($note, self::TEXT_NS, 'note-class');
        $noteClass = $noteClass === '' ? 'footnote' : $noteClass;
        $citation = self::firstChildElement($note, 'note-citation', self::TEXT_NS);
        $body = self::firstChildElement($note, 'note-body', self::TEXT_NS);
        $blocks = [];
        if ($body instanceof \DOMElement) {
            $blocks = $package instanceof ZipPackage
                ? $this->blockNodes($body, $package, $catalog)
                : $this->noteFallbackBlocks($body, $catalog);
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'noteClass' => $noteClass,
        ];
        $id = self::attr($note, self::TEXT_NS, 'id');
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($citation instanceof \DOMElement) {
            $attrs['citation'] = self::normalizedText($citation);
        }

        return new AstNode('note', $attrs, $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function noteFallbackBlocks(\DOMElement $body, array $catalog): array
    {
        $blocks = [];
        foreach (self::childElements($body, 'p', self::TEXT_NS) as $paragraph) {
            $node = $this->paragraphNode($paragraph, $catalog);
            if ($node instanceof AstNode) {
                $blocks[] = $node;
            }
        }

        return $blocks;
    }

    private function bookmarkAnchorNode(\DOMElement $bookmark): ?AstNode
    {
        $name = self::attr($bookmark, self::TEXT_NS, 'name');
        if ($name === '') {
            return null;
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'id' => self::bookmarkId($name),
            'classes' => ['anchor', 'odf-bookmark'],
            'attributes' => [
                'data-odf-bookmark-name' => $name,
            ],
        ]);
    }

    private function referenceMarkAnchorNode(\DOMElement $referenceMark): ?AstNode
    {
        $name = self::attr($referenceMark, self::TEXT_NS, 'name');
        if ($name === '') {
            return null;
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'id' => self::referenceId($name),
            'classes' => ['anchor', 'odf-reference-mark'],
            'attributes' => [
                'data-odf-reference-name' => $name,
            ],
        ]);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function bookmarkReferenceNode(\DOMElement $reference, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $name = self::attr($reference, self::TEXT_NS, 'ref-name');
        if ($name === '') {
            $name = self::attr($reference, self::TEXT_NS, 'name');
        }
        if ($name === '') {
            return null;
        }

        $children = $this->coalesceTextNodes($this->inlineNodes($reference, $catalog, $package));
        if ($children === []) {
            $children = [new AstNode('text', ['text' => $name])];
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'url' => '#' . self::bookmarkId($name),
            'classes' => ['odf-bookmark-ref'],
            'attributes' => [
                'data-odf-ref-name' => $name,
            ],
        ];
        $format = self::attr($reference, self::TEXT_NS, 'reference-format');
        if ($format !== '') {
            $attrs['attributes']['data-odf-reference-format'] = $format;
        }

        return new AstNode('link', $attrs, $children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function referenceReferenceNode(\DOMElement $reference, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $name = self::attr($reference, self::TEXT_NS, 'ref-name');
        if ($name === '') {
            $name = self::attr($reference, self::TEXT_NS, 'name');
        }
        if ($name === '') {
            return null;
        }

        $children = $this->coalesceTextNodes($this->inlineNodes($reference, $catalog, $package));
        if ($children === []) {
            $children = [new AstNode('text', ['text' => $name])];
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'url' => '#' . self::referenceId($name),
            'classes' => ['odf-reference-ref'],
            'attributes' => [
                'data-odf-ref-name' => $name,
            ],
        ];
        $format = self::attr($reference, self::TEXT_NS, 'reference-format');
        if ($format !== '') {
            $attrs['attributes']['data-odf-reference-format'] = $format;
        }

        return new AstNode('link', $attrs, $children);
    }

    /**
     * @param list<\DOMNode> $children
     * @return ?array{nodes:list<\DOMNode>, endIndex:int}
     */
    private function annotationRange(array $children, int $startIndex, string $name): ?array
    {
        $range = [];
        for ($index = $startIndex + 1, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMElement && $this->isElement($child, self::OFFICE_NS, 'annotation-end')) {
                $endName = self::attr($child, self::OFFICE_NS, 'name');
                if ($endName === $name) {
                    return [
                        'nodes' => $range,
                        'endIndex' => $index,
                    ];
                }
            }

            $range[] = $child;
        }

        return null;
    }

    /**
     * @param list<AstNode> $children
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function annotationRangeSpanNode(
        \DOMElement $annotation,
        string $name,
        array $children,
        array $catalog,
        ?ZipPackage $package
    ): ?AstNode {
        $note = $this->annotationNoteNode($annotation, $package, $catalog);
        if ($this->annotationNoteHasReviewContent($note)) {
            $children[] = $note;
        }
        if ($children === []) {
            return null;
        }

        $metadata = $this->annotationMetadata($annotation);
        $attributes = [
            'data-odf-annotation-name' => $name,
        ];
        foreach ($metadata as $key => $value) {
            if ($value !== '') {
                $attributes['data-odf-annotation-' . self::kebabCase($key)] = $value;
            }
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'annotationName' => $name,
            'annotationMetadata' => $metadata,
            'classes' => ['odf-annotation-range'],
            'attributes' => $attributes,
        ], $children);
    }

    private function annotationNoteHasReviewContent(AstNode $note): bool
    {
        return $note->children !== []
            || (string) $note->attr('author', '') !== ''
            || (string) $note->attr('date', '') !== '';
    }

    /**
     * @return array{author:string,date:string}
     */
    private function annotationMetadata(\DOMElement $annotation): array
    {
        $creator = self::firstChildElement($annotation, 'creator', self::DC_NS);
        $date = self::firstChildElement($annotation, 'date', self::DC_NS);

        return [
            'author' => $creator instanceof \DOMElement ? self::normalizedText($creator) : '',
            'date' => $date instanceof \DOMElement ? self::normalizedText($date) : '',
        ];
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function annotationNoteNode(\DOMElement $annotation, ?ZipPackage $package, array $catalog): AstNode
    {
        $metadata = $this->annotationMetadata($annotation);
        $blocks = $package instanceof ZipPackage
            ? $this->blockNodes($annotation, $package, $catalog)
            : $this->annotationInlineFallbackBlocks($annotation, $catalog);

        return new AstNode('note', [
            'sourceFormat' => 'odt',
            'author' => $metadata['author'],
            'date' => $metadata['date'],
        ], $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function annotationInlineFallbackBlocks(\DOMElement $annotation, array $catalog): array
    {
        $blocks = [];
        foreach (self::childElements($annotation, 'p', self::TEXT_NS) as $paragraph) {
            $node = $this->paragraphNode($paragraph, $catalog);
            if ($node instanceof AstNode) {
                $blocks[] = $node;
            }
        }

        return $blocks;
    }

    private function frameImageNode(\DOMElement $frame, ?ZipPackage $package): ?AstNode
    {
        $image = self::firstChildElement($frame, 'image', self::DRAW_NS);
        if (!$image instanceof \DOMElement) {
            return null;
        }

        $href = self::attr($image, self::XLINK_NS, 'href');
        if ($href === '') {
            return null;
        }

        $title = self::firstChildElement($image, 'title', self::SVG_NS)
            ?? self::firstChildElement($frame, 'title', self::SVG_NS);
        $desc = self::firstChildElement($image, 'desc', self::SVG_NS)
            ?? self::firstChildElement($frame, 'desc', self::SVG_NS);
        $name = self::attr($frame, self::DRAW_NS, 'name');
        $alt = $desc instanceof \DOMElement ? self::normalizedText($desc) : ($title instanceof \DOMElement ? self::normalizedText($title) : $name);
        $part = $this->manifestPackagePart($href);
        $manifestItem = $this->manifestByPart[$part] ?? null;
        $encrypted = is_array($manifestItem) && ($manifestItem['encrypted'] ?? false) === true;
        $attrs = [
            'url' => $href,
            'alt' => $alt,
            'sourceFormat' => 'odt',
            'sourcePart' => $part,
        ];
        $dimensions = $this->frameImageDimensions($frame, $image);
        if ($dimensions !== []) {
            $attrs += $dimensions;
        }
        if (is_array($manifestItem)) {
            $attrs['mediaType'] = $manifestItem['mediaType'] ?? null;
            $attrs['encrypted'] = $encrypted;
            $attrs['canExposeBytes'] = !$encrypted;
            $attrs['declaredSize'] = $manifestItem['declaredSize'] ?? null;
            $attrs['encryption'] = $manifestItem['encryption'] ?? null;
        }
        if ($title instanceof \DOMElement) {
            $attrs['title'] = self::normalizedText($title);
        }
        if ($package instanceof ZipPackage && $package->has($part) && !$encrypted) {
            $attrs['bytes'] = strlen($package->read($part));
        }

        return new AstNode('image', $attrs, $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
    }

    /**
     * @return array{width?:string,height?:string,attributes?:array<string, string>}
     */
    private function frameImageDimensions(\DOMElement $frame, \DOMElement $image): array
    {
        $attributes = [];
        foreach (['width', 'height'] as $name) {
            $value = self::attr($image, self::SVG_NS, $name);
            if ($value === '') {
                $value = self::attr($frame, self::SVG_NS, $name);
            }
            if ($value === '') {
                continue;
            }

            $attributes[$name] = $value;
        }

        if ($attributes === []) {
            return [];
        }

        return $attributes + ['attributes' => $attributes];
    }

    private function frameObjectMathNode(\DOMElement $frame, ?ZipPackage $package): ?AstNode
    {
        if (!$package instanceof ZipPackage) {
            return null;
        }

        $object = self::firstChildElement($frame, 'object', self::DRAW_NS);
        if (!$object instanceof \DOMElement) {
            return null;
        }

        $href = self::attr($object, self::XLINK_NS, 'href');
        if ($href === '') {
            return null;
        }

        [$objectPath, $contentPart] = $this->objectContentPart($href);
        if (!$package->has($contentPart)) {
            return null;
        }

        $dom = self::loadXml($package->read($contentPart), 'ODT MathML object ' . $contentPart);
        $math = $this->firstMathElement($dom);
        if (!$math instanceof \DOMElement) {
            return null;
        }

        $mathml = $this->mathElementXml($math);
        if ($mathml === '') {
            return null;
        }

        return new AstNode('math', [
            'sourceFormat' => 'odt-mathml',
            'display' => true,
            'text' => $this->mathPlainText($math),
            'mathml' => $mathml,
            'objectPath' => $objectPath,
            'sourcePart' => $contentPart,
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function objectContentPart(string $href): array
    {
        $part = $this->manifestPackagePart($href);
        if (str_ends_with($part, '/content.xml')) {
            return [substr($part, 0, -strlen('/content.xml')), $part];
        }

        $objectPath = rtrim($part, '/');

        return [$objectPath, $objectPath . '/content.xml'];
    }

    private function firstMathElement(\DOMDocument $dom): ?\DOMElement
    {
        $math = $dom->getElementsByTagNameNS(self::MATH_NS, 'math')->item(0);

        return $math instanceof \DOMElement ? $math : null;
    }

    private function mathElementXml(\DOMElement $math): string
    {
        $xml = $math->ownerDocument instanceof \DOMDocument ? $math->ownerDocument->saveXML($math) : '';

        return trim(is_string($xml) ? $xml : '');
    }

    private function mathPlainText(\DOMElement $math): string
    {
        $parts = [];
        $this->collectMathText($math, $parts);
        $text = implode('', $parts);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<string> $parts
     */
    private function collectMathText(\DOMNode $node, array &$parts): void
    {
        if ($node instanceof \DOMElement && $node->namespaceURI === self::MATH_NS && in_array($node->localName, ['annotation', 'annotation-xml'], true)) {
            return;
        }

        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            $parts[] = $node->textContent;

            return;
        }

        foreach ($node->childNodes as $child) {
            $this->collectMathText($child, $parts);
        }
    }

    /**
     * @return array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}
     */
    private function styleCollectionsFromRoot(\DOMElement $root): array
    {
        $styles = [];
        foreach ($root->getElementsByTagNameNS(self::STYLE_NS, 'style') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($style, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $styles[$name] = $this->styleDefinition($style);
        }

        $listStyles = [];
        foreach ($root->getElementsByTagNameNS(self::TEXT_NS, 'list-style') as $listStyle) {
            if (!$listStyle instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($listStyle, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $listStyles[$name] = $this->listStyleDefinition($listStyle);
        }

        $pageLayouts = [];
        foreach ($root->getElementsByTagNameNS(self::STYLE_NS, 'page-layout') as $pageLayout) {
            if (!$pageLayout instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($pageLayout, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $pageLayouts[$name] = $this->pageLayoutDefinition($pageLayout);
        }

        $masterPages = [];
        foreach ($root->getElementsByTagNameNS(self::STYLE_NS, 'master-page') as $masterPage) {
            if (!$masterPage instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($masterPage, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $masterPages[$name] = $this->masterPageDefinition($masterPage);
        }

        return [
            'styles' => $styles,
            'listStyles' => $listStyles,
            'pageLayouts' => $pageLayouts,
            'masterPages' => $masterPages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function styleDefinition(\DOMElement $style): array
    {
        $definition = [
            'name' => self::attr($style, self::STYLE_NS, 'name'),
            'family' => self::attr($style, self::STYLE_NS, 'family'),
            'displayName' => self::nullable(self::attr($style, self::STYLE_NS, 'display-name')),
            'parentName' => self::nullable(self::attr($style, self::STYLE_NS, 'parent-style-name')),
            'listStyleName' => self::nullable(self::attr($style, self::STYLE_NS, 'list-style-name')),
            'masterPageName' => self::nullable(self::attr($style, self::STYLE_NS, 'master-page-name')),
            'headingLevel' => self::nullableInt(self::attr($style, self::STYLE_NS, 'default-outline-level')),
            'textProperties' => [],
            'paragraphProperties' => [],
            'tableColumnProperties' => [],
        ];

        $textProperties = self::firstChildElement($style, 'text-properties', self::STYLE_NS);
        if ($textProperties instanceof \DOMElement) {
            $definition['textProperties'] = $this->textProperties($textProperties);
        }

        $paragraphProperties = self::firstChildElement($style, 'paragraph-properties', self::STYLE_NS);
        if ($paragraphProperties instanceof \DOMElement) {
            $definition['paragraphProperties'] = self::withoutEmpty([
                'textAlign' => self::nullable(self::attr($paragraphProperties, self::FO_NS, 'text-align')),
                'breakBefore' => self::nullable(self::attr($paragraphProperties, self::FO_NS, 'break-before')),
                'breakAfter' => self::nullable(self::attr($paragraphProperties, self::FO_NS, 'break-after')),
                'keepTogether' => self::nullable(self::attr($paragraphProperties, self::FO_NS, 'keep-together')),
                'keepWithNext' => self::nullable(self::attr($paragraphProperties, self::FO_NS, 'keep-with-next')),
            ]);
        }

        $columnProperties = self::firstChildElement($style, 'table-column-properties', self::STYLE_NS);
        if ($columnProperties instanceof \DOMElement) {
            $definition['tableColumnProperties'] = [
                'columnWidth' => self::nullable(self::attr($columnProperties, self::STYLE_NS, 'column-width')),
            ];
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function pageLayoutDefinition(\DOMElement $pageLayout): array
    {
        $definition = self::withoutEmpty([
            'name' => self::attr($pageLayout, self::STYLE_NS, 'name'),
            'pageUsage' => self::nullable(self::attr($pageLayout, self::STYLE_NS, 'page-usage')),
            'properties' => [],
        ]);

        $properties = self::firstChildElement($pageLayout, 'page-layout-properties', self::STYLE_NS);
        if ($properties instanceof \DOMElement) {
            $definition['properties'] = $this->pageLayoutProperties($properties);
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function pageLayoutProperties(\DOMElement $properties): array
    {
        $lengthAttributes = [
            'pageWidth' => [self::FO_NS, 'page-width'],
            'pageHeight' => [self::FO_NS, 'page-height'],
            'margin' => [self::FO_NS, 'margin'],
            'marginTop' => [self::FO_NS, 'margin-top'],
            'marginRight' => [self::FO_NS, 'margin-right'],
            'marginBottom' => [self::FO_NS, 'margin-bottom'],
            'marginLeft' => [self::FO_NS, 'margin-left'],
        ];
        $result = [];
        foreach ($lengthAttributes as $target => [$namespace, $attribute]) {
            $value = self::attr($properties, $namespace, $attribute);
            if ($value === '') {
                continue;
            }

            $result[$target] = $value;
            $points = $this->lengthToPoints($value);
            if ($points !== null) {
                $result[$target . 'Points'] = $points;
            }
        }

        return self::withoutEmpty($result + [
            'printOrientation' => self::nullable(self::attr($properties, self::STYLE_NS, 'print-orientation')),
            'writingMode' => self::nullable(self::attr($properties, self::STYLE_NS, 'writing-mode')),
            'numFormat' => self::nullable(self::attr($properties, self::STYLE_NS, 'num-format')),
            'firstPageNumber' => self::nullable(self::attr($properties, self::STYLE_NS, 'first-page-number')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function masterPageDefinition(\DOMElement $masterPage): array
    {
        return self::withoutEmpty([
            'name' => self::attr($masterPage, self::STYLE_NS, 'name'),
            'displayName' => self::nullable(self::attr($masterPage, self::STYLE_NS, 'display-name')),
            'pageLayoutName' => self::nullable(self::attr($masterPage, self::STYLE_NS, 'page-layout-name')),
            'nextStyleName' => self::nullable(self::attr($masterPage, self::STYLE_NS, 'next-style-name')),
            'drawStyleName' => self::nullable(self::attr($masterPage, self::DRAW_NS, 'style-name')),
            'headerText' => $this->masterPageTextBlocks($masterPage, 'header'),
            'headerLeftText' => $this->masterPageTextBlocks($masterPage, 'header-left'),
            'footerText' => $this->masterPageTextBlocks($masterPage, 'footer'),
            'footerLeftText' => $this->masterPageTextBlocks($masterPage, 'footer-left'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function masterPageTextBlocks(\DOMElement $masterPage, string $containerName): array
    {
        $container = self::firstChildElement($masterPage, $containerName, self::STYLE_NS);
        if (!$container instanceof \DOMElement) {
            return [];
        }

        $texts = [];
        foreach ($container->getElementsByTagNameNS(self::TEXT_NS, 'p') as $paragraph) {
            if (!$paragraph instanceof \DOMElement) {
                continue;
            }

            $text = self::normalizedText($paragraph);
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @return array<string, bool|string|null>
     */
    private function textProperties(\DOMElement $properties): array
    {
        $fontWeight = strtolower(self::attr($properties, self::FO_NS, 'font-weight'));
        $fontStyle = strtolower(self::attr($properties, self::FO_NS, 'font-style'));
        $underline = strtolower(self::attr($properties, self::STYLE_NS, 'text-underline-style'));
        $strikeout = strtolower(self::attr($properties, self::STYLE_NS, 'text-line-through-style'));
        $variant = strtolower(self::attr($properties, self::FO_NS, 'font-variant'));

        $result = [];
        if ($fontWeight === 'bold' || $fontWeight === '700') {
            $result['bold'] = true;
        }
        if ($fontStyle === 'italic' || $fontStyle === 'oblique') {
            $result['italic'] = true;
        }
        if ($underline !== '' && $underline !== 'none') {
            $result['underline'] = true;
        }
        if ($strikeout !== '' && $strikeout !== 'none') {
            $result['strikeout'] = true;
        }
        if ($variant === 'small-caps') {
            $result['smallCaps'] = true;
        }

        return $result;
    }

    /**
     * @return array{name:string, levels:array<int, array<string, mixed>>}
     */
    private function listStyleDefinition(\DOMElement $listStyle): array
    {
        $levels = [];
        foreach (self::childElements($listStyle) as $levelStyle) {
            if (!$this->isElement($levelStyle, self::TEXT_NS, 'list-level-style-bullet')
                && !$this->isElement($levelStyle, self::TEXT_NS, 'list-level-style-number')
            ) {
                continue;
            }

            $level = max(1, self::intAttr($levelStyle, self::TEXT_NS, 'level', 1));
            $levels[$level] = [
                'type' => $levelStyle->localName === 'list-level-style-number' ? 'number' : 'bullet',
                'level' => $level,
                'format' => self::attr($levelStyle, self::STYLE_NS, 'num-format'),
                'bulletChar' => self::attr($levelStyle, self::TEXT_NS, 'bullet-char'),
                'start' => self::intAttr($levelStyle, self::TEXT_NS, 'start-value', 1),
            ];
        }

        return [
            'name' => self::attr($listStyle, self::STYLE_NS, 'name'),
            'levels' => $levels,
        ];
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $target
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $source
     */
    private function mergeStyleCollections(array &$target, array $source): void
    {
        foreach ($source['styles'] as $name => $style) {
            $target['styles'][$name] = $style;
        }
        foreach ($source['listStyles'] as $name => $style) {
            $target['listStyles'][$name] = $style;
        }
        foreach ($source['pageLayouts'] as $name => $layout) {
            $target['pageLayouts'][$name] = $layout;
        }
        foreach ($source['masterPages'] as $name => $page) {
            $target['masterPages'][$name] = $page;
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return array<string, mixed>
     */
    private function resolveStyle(string $name, array $catalog, array $seen = []): array
    {
        if ($name === '' || !isset($catalog['styles'][$name]) || isset($seen[$name])) {
            return [];
        }

        $style = $catalog['styles'][$name];
        $seen[$name] = true;
        $parentName = (string) ($style['parentName'] ?? '');
        $parent = $this->resolveStyle($parentName, $catalog, $seen);
        if ($parent === []) {
            return $style;
        }

        return $this->mergeResolvedStyle($parent, $style);
    }

    /**
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $style
     * @return array<string, mixed>
     */
    private function mergeResolvedStyle(array $parent, array $style): array
    {
        $merged = $parent;
        foreach ($style as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = array_merge($merged[$key], array_filter(
                    $value,
                    static fn (mixed $entry): bool => $entry !== null && $entry !== ''
                ));
                continue;
            }
            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return array<string, mixed>
     */
    private function listDefinition(string $styleName, int $level, array $catalog): array
    {
        $listStyle = $catalog['listStyles'][$styleName] ?? null;
        if (!is_array($listStyle)) {
            return [
                'type' => 'bullet',
                'level' => $level,
                'bulletChar' => 'bullet',
            ];
        }

        $levels = $listStyle['levels'] ?? [];
        if (!is_array($levels)) {
            return [
                'type' => 'bullet',
                'level' => $level,
                'bulletChar' => 'bullet',
            ];
        }

        $definition = $levels[$level] ?? reset($levels);

        return is_array($definition) ? $definition : [
            'type' => 'bullet',
            'level' => $level,
            'bulletChar' => 'bullet',
        ];
    }

    private function orderedListStyle(string $format): string
    {
        return match ($format) {
            'a' => 'lower_alpha',
            'A' => 'upper_alpha',
            'i' => 'lower_roman',
            'I' => 'upper_roman',
            default => 'decimal',
        };
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function mediaReport(ZipPackage $package, array $manifest): array
    {
        $media = [];
        foreach ($manifest as $item) {
            $part = $item['part'] ?? null;
            $mediaType = (string) ($item['mediaType'] ?? '');
            if (!is_string($part) || $part === '') {
                continue;
            }
            if (str_ends_with($part, '/')) {
                continue;
            }
            if (in_array($part, ['content.xml', 'styles.xml', 'meta.xml', 'settings.xml'], true)) {
                continue;
            }
            if ($mediaType === '' || str_contains($mediaType, 'xml')) {
                continue;
            }

            $encrypted = ($item['encrypted'] ?? false) === true;
            $entry = $package->has($part) ? $package->entry($part) : null;
            $media[] = [
                'fullPath' => $item['fullPath'],
                'part' => $part,
                'mediaType' => $mediaType,
                'exists' => $entry instanceof ZipPackageEntry,
                'byteLength' => !$encrypted && $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'crc32' => !$encrypted && $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'storedByteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'storedCrc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'declaredSize' => $item['declaredSize'] ?? null,
                'encrypted' => $encrypted,
                'canExposeBytes' => !$encrypted,
                'encryption' => $item['encryption'] ?? null,
            ];
        }

        return $media;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @return array<string, array<string, mixed>>
     */
    private function manifestByPart(array $manifest): array
    {
        $byPart = [];
        foreach ($manifest as $item) {
            $part = $item['part'] ?? null;
            if (is_string($part) && $part !== '') {
                $byPart[$part] = $item;
            }
        }

        return $byPart;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function encryptedManifestItems(array $manifest): array
    {
        return array_values(array_filter(
            $manifest,
            static fn (array $item): bool => ($item['encrypted'] ?? false) === true
        ));
    }

    /**
     * @param list<AstNode> $nodes
     * @return array{noteCount:int, bookmarkCount:int, bookmarkReferenceCount:int, referenceMarkCount:int, referenceReferenceCount:int, sequenceCount:int, fieldCount:int, citationCount:int, annotationRangeCount:int, trackedChangeCount:int, mathCount:int, sectionCount:int, linkedSectionCount:int, protectedSectionCount:int, continuedListCount:int}
     */
    private function contentNodeStats(array $nodes): array
    {
        $stats = [
            'noteCount' => 0,
            'bookmarkCount' => 0,
            'bookmarkReferenceCount' => 0,
            'referenceMarkCount' => 0,
            'referenceReferenceCount' => 0,
            'sequenceCount' => 0,
            'fieldCount' => 0,
            'citationCount' => 0,
            'annotationRangeCount' => 0,
            'trackedChangeCount' => 0,
            'mathCount' => 0,
            'sectionCount' => 0,
            'linkedSectionCount' => 0,
            'protectedSectionCount' => 0,
            'continuedListCount' => 0,
        ];
        foreach ($nodes as $node) {
            if ($node->type === 'note') {
                $stats['noteCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-section')) {
                $stats['sectionCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-linked-section')) {
                $stats['linkedSectionCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-protected-section')) {
                $stats['protectedSectionCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-bookmark')) {
                $stats['bookmarkCount']++;
            }
            if ($node->type === 'link' && $this->nodeHasClass($node, 'odf-bookmark-ref')) {
                $stats['bookmarkReferenceCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-reference-mark')) {
                $stats['referenceMarkCount']++;
            }
            if ($node->type === 'link' && $this->nodeHasClass($node, 'odf-reference-ref')) {
                $stats['referenceReferenceCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-sequence')) {
                $stats['sequenceCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-field')) {
                $stats['fieldCount']++;
            }
            if ($node->type === 'citation') {
                $stats['citationCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-annotation-range')) {
                $stats['annotationRangeCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-change')) {
                $stats['trackedChangeCount']++;
            }
            if ($node->type === 'math') {
                $stats['mathCount']++;
            }
            if (($node->type === 'ordered_list' || $node->type === 'bullet_list') && $node->attr('continued') === true) {
                $stats['continuedListCount']++;
            }

            $childStats = $this->contentNodeStats($node->children);
            foreach ($childStats as $name => $count) {
                $stats[$name] += $count;
            }
        }

        return $stats;
    }

    private function nodeHasClass(AstNode $node, string $class): bool
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            return false;
        }

        return in_array($class, array_map(static fn (mixed $value): string => (string) $value, $classes), true);
    }

    private function manifestPackagePart(string $path): string
    {
        $path = preg_replace('/[#?].*$/', '', $path) ?? $path;
        $path = ltrim($path, '/');
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        if ($path === '') {
            throw new \RuntimeException('ODT package part path must not be empty');
        }
        if (str_contains($path, '..') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) === 1) {
            throw new \InvalidArgumentException('ODT package part path is not a safe package-relative path: ' . $path);
        }

        return $path;
    }

    /**
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $coalesced !== []) {
                $last = $coalesced[count($coalesced) - 1];
                if ($last->type === 'text') {
                    $attrs = $last->attrs;
                    $attrs['text'] = (string) ($attrs['text'] ?? '') . (string) $node->attr('text', '');
                    $coalesced[count($coalesced) - 1] = new AstNode('text', $attrs, $last->children);
                    continue;
                }
            }

            $coalesced[] = $node;
        }

        return $coalesced;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'linebreak') {
                $text .= "\n";
                continue;
            }
            if ($node->type === 'image') {
                $text .= (string) $node->attr('alt', '');
                continue;
            }
            if ($node->type === 'math') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'note') {
                continue;
            }
            $text .= $this->plainInlineText($node->children);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if ($block->children !== []) {
                $parts[] = $this->plainInlineText($block->children);
            } else {
                $parts[] = (string) $block->attr('text', '');
            }
        }

        return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function lengthToPoints(string $length): ?float
    {
        if ($length === '' || preg_match('/^([0-9]+(?:\.[0-9]+)?)(cm|mm|in|pt)$/', $length, $matches) !== 1) {
            return null;
        }

        $value = (float) $matches[1];

        return match ($matches[2]) {
            'cm' => $value * 28.3464567,
            'mm' => $value * 2.83464567,
            'in' => $value * 72.0,
            default => $value,
        };
    }

    private function isElement(\DOMElement $element, string $namespace, string $localName): bool
    {
        return $element->namespaceURI === $namespace && $element->localName === $localName;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadXML($xml, LIBXML_NONET);
            if (!$loaded) {
                $errors = libxml_get_errors();
                $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
                throw new \InvalidArgumentException("Unable to parse {$label}: {$message}");
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $dom;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function childElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $elements = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($localName !== null && $child->localName !== $localName) {
                continue;
            }
            if ($namespace !== null && $child->namespaceURI !== $namespace) {
                continue;
            }
            $elements[] = $child;
        }

        return $elements;
    }

    private static function firstChildElement(\DOMElement $element, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        foreach (self::childElements($element, $localName, $namespace) as $child) {
            return $child;
        }

        return null;
    }

    private static function attr(\DOMElement $element, string $namespace, string $name): string
    {
        return trim($element->getAttributeNS($namespace, $name));
    }

    private static function intAttr(\DOMElement $element, string $namespace, string $name, int $default): int
    {
        $value = self::attr($element, $namespace, $name);

        return ctype_digit($value) ? (int) $value : $default;
    }

    private static function nullableInt(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }

    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function withoutEmpty(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
    }

    private static function normalizedText(\DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent;

        return trim($text);
    }

    private static function camelCase(string $name): string
    {
        return preg_replace_callback(
            '/-([a-z])/',
            static fn (array $match): string => strtoupper($match[1]),
            $name
        ) ?? $name;
    }

    private static function kebabCase(string $name): string
    {
        $name = preg_replace('/(?<!^)[A-Z]/', '-$0', $name) ?? $name;

        return strtolower($name);
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($value)) ?? '');
        $slug = trim($slug, '-');

        return $slug === '' ? 'odf-section' : $slug;
    }

    private static function bookmarkId(string $name): string
    {
        $id = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)) ?? '');
        $id = trim($id, '-');

        return $id === '' ? 'odf-bookmark' : $id;
    }

    private static function referenceId(string $name): string
    {
        $id = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)) ?? '');
        $id = trim($id, '-');

        return $id === '' ? 'odf-reference' : $id;
    }
}
