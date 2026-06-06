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
    private const FORM_NS = 'urn:oasis:names:tc:opendocument:xmlns:form:1.0';
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

    /** @var array<string, array<string, mixed>> */
    private array $formControlsById = [];

    /** @var array<string, mixed> */
    private array $contentDeclarations = [];

    /** @var array<int, int> */
    private array $listContinuationStartCounters = [];

    /** @var list<string> */
    private array $currentListStyleNames = [];

    private int $currentListLevel = 0;

    /** @var array<string, int> */
    private array $headingAnchorUses = [];

    /**
     * @return array{
     *     document:AstNode,
     *     metadata:array<string, mixed>,
     *     manifest:list<array<string, mixed>>,
     *     styles:array<string, mixed>,
     *     listStyles:array<string, mixed>,
     *     tableTemplates:array<string, mixed>,
     *     pageLayouts:array<string, mixed>,
     *     masterPages:array<string, mixed>,
     *     contentDeclarations:array<string, mixed>,
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
            'tableTemplates' => [
                'count' => count($styleCatalog['tableTemplates']),
                'items' => array_values($styleCatalog['tableTemplates']),
            ],
            'pageLayouts' => [
                'count' => count($styleCatalog['pageLayouts']),
                'items' => array_values($styleCatalog['pageLayouts']),
            ],
            'masterPages' => [
                'count' => count($styleCatalog['masterPages']),
                'items' => array_values($styleCatalog['masterPages']),
            ],
            'contentDeclarations' => $content['contentDeclarations'],
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
            'tableTemplates' => $styleCatalog['tableTemplates'],
            'pageLayouts' => $styleCatalog['pageLayouts'],
            'masterPages' => $styleCatalog['masterPages'],
            'contentDeclarations' => $content['contentDeclarations'],
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
                    'tableTemplateCount' => count($styleCatalog['tableTemplates']),
                ],
                'listStyles' => [
                    'count' => count($styleCatalog['listStyles']),
                    'items' => array_values($styleCatalog['listStyles']),
                ],
                'tableTemplates' => [
                    'count' => count($styleCatalog['tableTemplates']),
                    'items' => array_values($styleCatalog['tableTemplates']),
                ],
                'pageLayouts' => [
                    'count' => count($styleCatalog['pageLayouts']),
                    'items' => array_values($styleCatalog['pageLayouts']),
                ],
                'masterPages' => [
                    'count' => count($styleCatalog['masterPages']),
                    'items' => array_values($styleCatalog['masterPages']),
                ],
                'contentDeclarations' => $content['contentDeclarations'],
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
                    'blockquoteCount' => $contentStats['blockquoteCount'],
                    'noteCount' => $contentStats['noteCount'],
                    'bookmarkCount' => $contentStats['bookmarkCount'],
                    'bookmarkReferenceCount' => $contentStats['bookmarkReferenceCount'],
                    'referenceMarkCount' => $contentStats['referenceMarkCount'],
                    'referenceReferenceCount' => $contentStats['referenceReferenceCount'],
                    'indexMarkCount' => $contentStats['indexMarkCount'],
                    'sequenceCount' => $contentStats['sequenceCount'],
                    'fieldCount' => $contentStats['fieldCount'],
                    'placeholderCount' => $contentStats['placeholderCount'],
                    'rubyCount' => $contentStats['rubyCount'],
                    'softPageBreakCount' => $contentStats['softPageBreakCount'],
                    'citationCount' => $contentStats['citationCount'],
                    'annotationRangeCount' => $contentStats['annotationRangeCount'],
                    'trackedChangeCount' => $contentStats['trackedChangeCount'],
                    'mathCount' => $contentStats['mathCount'],
                    'embeddedObjectCount' => $contentStats['embeddedObjectCount'],
                    'missingEmbeddedObjectCount' => $contentStats['missingEmbeddedObjectCount'],
                    'formControlCount' => $contentStats['formControlCount'],
                    'missingFormControlCount' => $contentStats['missingFormControlCount'],
                    'sectionCount' => $contentStats['sectionCount'],
                    'linkedSectionCount' => $contentStats['linkedSectionCount'],
                    'protectedSectionCount' => $contentStats['protectedSectionCount'],
                    'tableOfContentsCount' => $contentStats['tableOfContentsCount'],
                    'generatedIndexCount' => $contentStats['generatedIndexCount'],
                    'tableCaptionCount' => $contentStats['tableCaptionCount'],
                    'preformattedCodeBlockCount' => $contentStats['preformattedCodeBlockCount'],
                    'continuedListCount' => $contentStats['continuedListCount'],
                    'listHeaderCount' => $contentStats['listHeaderCount'],
                    'tableTemplateReferenceCount' => $contentStats['tableTemplateReferenceCount'],
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
        $package->assertStoredFirstEntry('mimetype', self::MIMETYPE, 'ODT mimetype entry');
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
     * @return array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}
     */
    private function readStyles(ZipPackage $package): array
    {
        $catalog = [
            'styles' => [],
            'listStyles' => [],
            'tableTemplates' => [],
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
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $styleCatalog
     * @return array{blocks:list<AstNode>, styleCatalog:array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}, automaticStyleCount:int, trackedChanges:list<array<string, mixed>>, contentDeclarations:array<string, mixed>}
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
        $this->formControlsById = $this->formControlsFromText($text);
        $this->contentDeclarations = $this->contentDeclarationsFromText($text);
        $this->listContinuationStartCounters = [];
        $this->currentListStyleNames = [];
        $this->currentListLevel = 0;
        $this->headingAnchorUses = [];

        return [
            'blocks' => $this->blockNodes($text, $package, $styleCatalog),
            'styleCatalog' => $styleCatalog,
            'automaticStyleCount' => count($contentStyles['styles']) + count($contentStyles['listStyles']) + count($contentStyles['tableTemplates']) + count($contentStyles['pageLayouts']) + count($contentStyles['masterPages']),
            'trackedChanges' => array_values($this->trackedChanges),
            'contentDeclarations' => $this->contentDeclarations,
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
            if ($this->isElement($child, self::TEXT_NS, 'table-of-content')) {
                $tableOfContents = $this->tableOfContentsNode($child, $package, $catalog);
                if ($tableOfContents !== null) {
                    $blocks[] = $tableOfContents;
                }
                continue;
            }
            if ($this->generatedIndexType($child) !== null) {
                $generatedIndex = $this->generatedIndexNode($child, $package, $catalog);
                if ($generatedIndex !== null) {
                    $blocks[] = $generatedIndex;
                }
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
            if ($this->isElement($child, self::DRAW_NS, 'control')) {
                $control = $this->formControlNode($child, null, false);
                if ($control !== null) {
                    $blocks[] = $control;
                }
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation')) {
                $blocks[] = $this->annotationBlockNode($child, $package, $catalog);
            }
        }

        return $this->postProcessBlocks($blocks);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function postProcessBlocks(array $blocks): array
    {
        $processed = [];
        for ($index = 0, $count = count($blocks); $index < $count; $index++) {
            $block = $blocks[$index];
            $next = $blocks[$index + 1] ?? null;
            if ($block->type === 'table' && $next instanceof AstNode && $this->nodeHasClass($next, 'odf-table-caption')) {
                $processed[] = $this->tableWithFollowingCaption($block, $next);
                $index++;
                continue;
            }

            $processed[] = $block;
        }

        return $processed;
    }

    private function tableWithFollowingCaption(AstNode $table, AstNode $caption): AstNode
    {
        $captionText = trim((string) $caption->attr('text', ''));
        if ($captionText === '') {
            $captionText = trim($this->plainBlockText($caption->children));
        }
        if ($captionText === '') {
            return $table;
        }

        $captionBlocks = $caption->children;
        $captionInlines = [];
        foreach ($captionBlocks as $block) {
            if ($block instanceof AstNode && $block->type === 'paragraph') {
                $captionInlines = $block->children;
                break;
            }
        }

        $sourceAttributes = [
            'classes' => ['odf-table-caption'],
            'attributes' => [
                'data-odf-table-caption-source' => 'following-paragraph',
            ],
        ];
        $styleName = (string) $caption->attr('styleName', '');
        if ($styleName !== '') {
            $sourceAttributes['attributes']['data-odf-table-caption-style-name'] = $styleName;
        }

        $attrs = $table->attrs;
        $attrs['caption'] = $captionText;
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }
        if ($captionBlocks !== []) {
            $attrs['captionBlocks'] = $captionBlocks;
        }
        $attrs['captionSource'] = [
            'source' => 'odf-table-caption-paragraph',
            'element' => 'text:p',
            'sourceElement' => 'text:p',
            'position' => 'following-table',
            'sourcePosition' => 'following-table',
            'styleName' => $styleName === '' ? null : $styleName,
            'sourceAttributes' => $sourceAttributes,
        ];
        $attrs['odfCaptionParagraph'] = true;

        $tableName = trim((string) ($attrs['tableName'] ?? ''));

        return TableGeometry::withReviewPacket(
            new AstNode('table', $attrs, $table->children),
            ['idPrefix' => $tableName === '' ? 'odf-table' : $tableName]
        );
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
        $inlines = $this->coalesceTextNodes($this->inlineNodes($heading, $catalog, $package));
        $headingAnchor = $this->extractHeadingBookmarkAnchor($inlines);
        $inlines = $headingAnchor['inlines'];
        $text = $this->plainInlineText($inlines);
        $attrs = [
            'level' => $level,
            'sourceFormat' => 'odt',
            'text' => $text,
            'id' => $headingAnchor['anchor'] === null
                ? $this->uniqueHeadingAnchor($text)
                : $this->uniqueHeadingAnchorFromBase($headingAnchor['anchor']['id']),
        ];
        if ($headingAnchor['anchor'] !== null) {
            $this->addHeadingBookmarkAnchorAttrs($attrs, $headingAnchor['anchor']);
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['style'] = $style;
        }

        return new AstNode('heading', $attrs, $inlines);
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

        if ($this->isPreformattedParagraphStyle($styleName, $style)) {
            $codeAttrs = $attrs;
            $codeAttrs['odfPreformatted'] = true;
            $codeAttrs['attributes'] = [
                'data-odf-preformatted' => 'true',
            ];
            if ($styleName !== '') {
                $codeAttrs['attributes']['data-odf-style-name'] = $styleName;
            }

            return new AstNode('code_block', $codeAttrs);
        }

        $headingLevel = (int) ($style['headingLevel'] ?? 0);
        if ($headingLevel > 0) {
            $attrs['level'] = max(1, min(6, $headingLevel));
            $headingAnchor = $this->extractHeadingBookmarkAnchor($inlines);
            $inlines = $headingAnchor['inlines'];
            $text = $this->plainInlineText($inlines);
            $attrs['text'] = $text;
            $attrs['id'] = $headingAnchor['anchor'] === null
                ? $this->uniqueHeadingAnchor($text)
                : $this->uniqueHeadingAnchorFromBase($headingAnchor['anchor']['id']);
            if ($headingAnchor['anchor'] !== null) {
                $this->addHeadingBookmarkAnchorAttrs($attrs, $headingAnchor['anchor']);
            }

            return new AstNode('heading', $attrs, $inlines);
        }

        $node = new AstNode('paragraph', $attrs, $inlines);
        if ($styleName === 'Table') {
            return new AstNode('div', [
                'sourceFormat' => 'odt',
                'styleName' => $styleName,
                'style' => $style,
                'text' => $text,
                'tableCaption' => true,
                'classes' => ['caption', 'odf-table-caption'],
                'attributes' => [
                    'data-odf-table-caption-style-name' => $styleName,
                ],
            ], [$node]);
        }
        if ($this->isBlockquoteParagraphStyle($style)) {
            $quoteAttrs = $attrs;
            $quoteAttrs['classes'] = ['odf-blockquote'];

            return new AstNode('blockquote', $quoteAttrs, [$node]);
        }

        return $node;
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
        $explicitStyleName = self::attr($list, self::TEXT_NS, 'style-name');
        $inheritedStyleName = $explicitStyleName === '' ? $this->currentListStyleName() : '';
        $styleName = $explicitStyleName !== '' ? $explicitStyleName : $inheritedStyleName;
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
        if ($explicitStyleName !== '') {
            $attrs['styleName'] = $explicitStyleName;
        } elseif ($inheritedStyleName !== '') {
            $attrs['inheritedStyleName'] = $inheritedStyleName;
        }
        if ($continueNumbering) {
            $attrs['continued'] = true;
        }
        if ($ordered) {
            $attrs['style'] = $this->orderedListStyle((string) ($definition['format'] ?? '1'));
            $attrs['start'] = $start;
            $numPrefix = (string) ($definition['numPrefix'] ?? '');
            $numSuffix = (string) ($definition['numSuffix'] ?? '');
            $delimiter = $this->orderedListDelimiter($numPrefix, $numSuffix);
            if ($delimiter !== '') {
                $attrs['delimiter'] = $delimiter;
            }
            if ($numPrefix !== '') {
                $attrs['numberPrefix'] = $numPrefix;
            }
            if ($numSuffix !== '') {
                $attrs['numberSuffix'] = $numSuffix;
            }
        } else {
            $attrs['format'] = (string) ($definition['bulletChar'] ?? 'bullet');
        }

        $items = [];
        $numberedItemCount = 0;
        $pushedStyleName = $this->pushCurrentListStyleName($styleName, $catalog);
        try {
            foreach (self::childElements($list) as $child) {
                $isListHeader = $this->isElement($child, self::TEXT_NS, 'list-header');
                if (!$this->isElement($child, self::TEXT_NS, 'list-item') && !$isListHeader) {
                    continue;
                }

                $itemBlocks = $this->blockNodes($child, $package, $catalog);
                $itemAttrs = [
                    'sourceFormat' => 'odt',
                ];
                if ($isListHeader) {
                    $itemAttrs['listHeader'] = true;
                    $itemAttrs['classes'] = ['odf-list-header'];
                    $itemAttrs['attributes'] = [
                        'data-odf-list-header' => 'true',
                        'data-odf-list-level' => (string) $level,
                    ];
                } else {
                    $numberedItemCount++;
                }

                $items[] = new AstNode('list_item', $itemAttrs, $itemBlocks);
            }
        } finally {
            if ($pushedStyleName) {
                array_pop($this->currentListStyleNames);
            }
        }

        $this->listContinuationStartCounters[$level] = $start + $numberedItemCount;

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    private function currentListStyleName(): string
    {
        if ($this->currentListStyleNames === []) {
            return '';
        }

        return $this->currentListStyleNames[array_key_last($this->currentListStyleNames)];
    }

    /**
     * @param array{listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function pushCurrentListStyleName(string $styleName, array $catalog): bool
    {
        if ($styleName === '' || !isset($catalog['listStyles'][$styleName])) {
            return false;
        }

        $this->currentListStyleNames[] = $styleName;

        return true;
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
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function tableOfContentsNode(\DOMElement $tableOfContents, ZipPackage $package, array $catalog): ?AstNode
    {
        $name = self::attr($tableOfContents, self::TEXT_NS, 'name');
        $styleName = self::attr($tableOfContents, self::TEXT_NS, 'style-name');
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => ['odf-table-of-contents'],
            'attributes' => [],
        ];

        if ($name !== '') {
            $attrs['id'] = self::slug($name);
            $attrs['tableOfContentsName'] = $name;
            $attrs['attributes']['data-odf-toc-name'] = $name;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['attributes']['data-odf-toc-style-name'] = $styleName;
        }

        $protected = self::nullableBool(self::attr($tableOfContents, self::TEXT_NS, 'protected'));
        if ($protected !== null) {
            $attrs['protected'] = $protected;
            $attrs['attributes']['data-odf-toc-protected'] = $protected ? 'true' : 'false';
            if ($protected) {
                $attrs['classes'][] = 'odf-protected-table-of-contents';
            }
        }

        $protectionKey = self::attr($tableOfContents, self::TEXT_NS, 'protection-key');
        if ($protectionKey !== '') {
            $attrs['protectionKeyPresent'] = true;
            $attrs['attributes']['data-odf-toc-protection-key-present'] = 'true';
        }
        $digestAlgorithm = self::attr($tableOfContents, self::TEXT_NS, 'protection-key-digest-algorithm');
        if ($digestAlgorithm !== '') {
            $attrs['protectionKeyDigestAlgorithm'] = $digestAlgorithm;
            $attrs['attributes']['data-odf-toc-protection-key-digest-algorithm'] = $digestAlgorithm;
        }

        $source = self::firstChildElement($tableOfContents, 'table-of-content-source', self::TEXT_NS);
        if ($source instanceof \DOMElement) {
            $sourceMetadata = $this->tableOfContentsSourceMetadata($source);
            if ($sourceMetadata !== []) {
                $attrs['tableOfContentsSource'] = $sourceMetadata;
                $this->addTableOfContentsSourceAttributes($attrs['attributes'], $sourceMetadata);
            }
        }

        $children = [];
        foreach (self::childElements($tableOfContents) as $child) {
            if ($this->isElement($child, self::TEXT_NS, 'index-title')) {
                $titleBlocks = $this->blockNodes($child, $package, $catalog);
                if ($titleBlocks !== []) {
                    $children[] = new AstNode('div', [
                        'sourceFormat' => 'odt',
                        'classes' => ['odf-index-title'],
                        'attributes' => [
                            'data-odf-index-title' => 'true',
                        ],
                    ], $titleBlocks);
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'index-body')) {
                $bodyBlocks = $this->blockNodes($child, $package, $catalog);
                if ($bodyBlocks !== []) {
                    $children[] = new AstNode('div', [
                        'sourceFormat' => 'odt',
                        'classes' => ['odf-index-body'],
                        'attributes' => [
                            'data-odf-index-body' => 'true',
                        ],
                    ], $bodyBlocks);
                }
            }
        }

        if ($children === [] && $attrs['attributes'] === []) {
            return null;
        }

        return new AstNode('div', $attrs, $children);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableOfContentsSourceMetadata(\DOMElement $source): array
    {
        $metadata = self::withoutEmpty([
            'outlineLevel' => self::nullableInt(self::attr($source, self::TEXT_NS, 'outline-level')),
            'relativeTabStopPosition' => self::nullableBool(self::attr($source, self::TEXT_NS, 'relative-tab-stop-position')),
            'useIndexMarks' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-index-marks')),
            'useIndexSourceStyles' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-index-source-styles')),
            'useObjects' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-objects')),
            'useTables' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-tables')),
            'useGraphics' => self::nullableBool(self::attr($source, self::TEXT_NS, 'use-graphics')),
        ]);

        $sourceStyles = $this->tableOfContentsSourceStyles($source);
        if ($sourceStyles !== []) {
            $metadata['sourceStyles'] = $sourceStyles;
        }

        $templates = $this->tableOfContentsTemplates($source);
        if ($templates !== []) {
            $metadata['templates'] = $templates;
        }

        return $metadata;
    }

    /**
     * @return list<array{outlineLevel?:int, styleNames:list<string>}>
     */
    private function tableOfContentsSourceStyles(\DOMElement $source): array
    {
        $styles = [];
        foreach (self::childElements($source, 'index-source-styles', self::TEXT_NS) as $sourceStyles) {
            $styleNames = [];
            foreach (self::childElements($sourceStyles, 'index-source-style', self::TEXT_NS) as $sourceStyle) {
                $styleName = self::attr($sourceStyle, self::TEXT_NS, 'style-name');
                if ($styleName !== '') {
                    $styleNames[] = $styleName;
                }
            }

            $entry = self::withoutEmpty([
                'outlineLevel' => self::nullableInt(self::attr($sourceStyles, self::TEXT_NS, 'outline-level')),
                'styleNames' => $styleNames,
            ]);
            if ($entry !== []) {
                $styles[] = $entry;
            }
        }

        return $styles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableOfContentsTemplates(\DOMElement $source): array
    {
        $templates = [];
        foreach (self::childElements($source) as $template) {
            if (!$this->isElement($template, self::TEXT_NS, 'index-title-template')
                && !$this->isElement($template, self::TEXT_NS, 'table-of-content-entry-template')) {
                continue;
            }

            $templates[] = self::withoutEmpty([
                'type' => $template->localName === 'index-title-template' ? 'title' : 'entry',
                'outlineLevel' => self::nullableInt(self::attr($template, self::TEXT_NS, 'outline-level')),
                'styleName' => self::nullable(self::attr($template, self::TEXT_NS, 'style-name')),
                'components' => $this->tableOfContentsTemplateComponents($template),
            ]);
        }

        return $templates;
    }

    /**
     * @return list<array<string, string>>
     */
    private function tableOfContentsTemplateComponents(\DOMElement $template): array
    {
        $components = [];
        foreach (self::childElements($template) as $component) {
            if ($component->namespaceURI !== self::TEXT_NS) {
                continue;
            }

            $componentMetadata = self::withoutEmpty([
                'type' => $component->localName,
                'styleName' => self::attr($component, self::TEXT_NS, 'style-name'),
                'leaderChar' => self::attr($component, self::STYLE_NS, 'leader-char'),
                'tabStopType' => self::attr($component, self::STYLE_NS, 'type'),
                'tabStopPosition' => self::attr($component, self::STYLE_NS, 'position'),
            ]);
            if ($componentMetadata !== []) {
                $components[] = $componentMetadata;
            }
        }

        return $components;
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed> $sourceMetadata
     */
    private function addTableOfContentsSourceAttributes(array &$attributes, array $sourceMetadata): void
    {
        foreach ($sourceMetadata as $name => $value) {
            if (is_bool($value)) {
                $attributes['data-odf-toc-source-' . self::kebabCase($name)] = $value ? 'true' : 'false';
                continue;
            }
            if (is_int($value) || is_string($value)) {
                $attributes['data-odf-toc-source-' . self::kebabCase($name)] = (string) $value;
            }
        }

        if (isset($sourceMetadata['sourceStyles']) && is_array($sourceMetadata['sourceStyles'])) {
            $attributes['data-odf-toc-source-style-count'] = (string) count($sourceMetadata['sourceStyles']);
        }
        if (isset($sourceMetadata['templates']) && is_array($sourceMetadata['templates'])) {
            $attributes['data-odf-toc-template-count'] = (string) count($sourceMetadata['templates']);
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function generatedIndexNode(\DOMElement $index, ZipPackage $package, array $catalog): ?AstNode
    {
        $type = $this->generatedIndexType($index);
        if ($type === null) {
            return null;
        }

        $element = $index->localName;
        $name = self::attr($index, self::TEXT_NS, 'name');
        $styleName = self::attr($index, self::TEXT_NS, 'style-name');
        $attrs = [
            'sourceFormat' => 'odt',
            'generatedIndexType' => $type,
            'generatedIndexElement' => $element,
            'classes' => ['odf-generated-index', 'odf-' . $element],
            'attributes' => [
                'data-odf-index-type' => $type,
                'data-odf-index-element' => $element,
            ],
        ];

        if ($name !== '') {
            $attrs['id'] = self::slug($name);
            $attrs['generatedIndexName'] = $name;
            $attrs['attributes']['data-odf-index-name'] = $name;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['attributes']['data-odf-index-style-name'] = $styleName;
        }

        $protected = self::nullableBool(self::attr($index, self::TEXT_NS, 'protected'));
        if ($protected !== null) {
            $attrs['protected'] = $protected;
            $attrs['attributes']['data-odf-index-protected'] = $protected ? 'true' : 'false';
            if ($protected) {
                $attrs['classes'][] = 'odf-protected-generated-index';
            }
        }

        $protectionKey = self::attr($index, self::TEXT_NS, 'protection-key');
        if ($protectionKey !== '') {
            $attrs['protectionKeyPresent'] = true;
            $attrs['attributes']['data-odf-index-protection-key-present'] = 'true';
        }
        $digestAlgorithm = self::attr($index, self::TEXT_NS, 'protection-key-digest-algorithm');
        if ($digestAlgorithm !== '') {
            $attrs['protectionKeyDigestAlgorithm'] = $digestAlgorithm;
            $attrs['attributes']['data-odf-index-protection-key-digest-algorithm'] = $digestAlgorithm;
        }

        $source = self::firstChildElement($index, $this->generatedIndexSourceElementName($element), self::TEXT_NS);
        if ($source instanceof \DOMElement) {
            $sourceMetadata = $this->generatedIndexSourceMetadata($source);
            if ($sourceMetadata !== []) {
                $attrs['generatedIndexSource'] = $sourceMetadata;
                $this->addGeneratedIndexSourceAttributes($attrs['attributes'], $sourceMetadata);
            }
        }

        $children = $this->generatedIndexTitleAndBodyNodes($index, $package, $catalog);
        if ($children === [] && count($attrs['attributes']) <= 2) {
            return null;
        }

        return new AstNode('div', $attrs, $children);
    }

    private function generatedIndexType(\DOMElement $element): ?string
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return null;
        }

        return match ($element->localName) {
            'alphabetical-index' => 'alphabetical',
            'bibliography' => 'bibliography',
            'illustration-index' => 'illustration',
            'object-index' => 'object',
            'table-index' => 'table',
            'user-index' => 'user',
            default => null,
        };
    }

    private function generatedIndexSourceElementName(string $indexElementName): string
    {
        return $indexElementName === 'bibliography' ? 'bibliography-source' : $indexElementName . '-source';
    }

    /**
     * @return array<string, mixed>
     */
    private function generatedIndexSourceMetadata(\DOMElement $source): array
    {
        $metadata = array_merge(
            ['element' => $source->localName],
            $this->odfElementMetadataAttributes($source, [self::TEXT_NS])
        );

        $sourceStyles = $this->tableOfContentsSourceStyles($source);
        if ($sourceStyles !== []) {
            $metadata['sourceStyles'] = $sourceStyles;
        }

        $templates = $this->generatedIndexTemplates($source);
        if ($templates !== []) {
            $metadata['templates'] = $templates;
        }

        return self::withoutEmpty($metadata);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function generatedIndexTemplates(\DOMElement $source): array
    {
        $templates = [];
        foreach (self::childElements($source) as $template) {
            if ($template->namespaceURI !== self::TEXT_NS) {
                continue;
            }
            if ($template->localName !== 'index-title-template' && !str_ends_with($template->localName, '-entry-template')) {
                continue;
            }

            $templates[] = self::withoutEmpty(array_merge([
                'type' => $template->localName === 'index-title-template' ? 'title' : 'entry',
                'element' => $template->localName,
            ], $this->odfElementMetadataAttributes($template, [self::TEXT_NS]), [
                'components' => $this->tableOfContentsTemplateComponents($template),
            ]));
        }

        return $templates;
    }

    /**
     * @param list<string> $namespaces
     * @return array<string, mixed>
     */
    private function odfElementMetadataAttributes(\DOMElement $element, array $namespaces): array
    {
        $attributes = [];
        if (!$element->hasAttributes()) {
            return $attributes;
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr || !in_array((string) $attribute->namespaceURI, $namespaces, true)) {
                continue;
            }

            $attributes[self::camelCase($attribute->localName)] = self::typedOdfAttributeValue($attribute->value);
        }

        return self::withoutEmpty($attributes);
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, mixed> $sourceMetadata
     */
    private function addGeneratedIndexSourceAttributes(array &$attributes, array $sourceMetadata): void
    {
        foreach ($sourceMetadata as $name => $value) {
            if (is_bool($value)) {
                $attributes['data-odf-index-source-' . self::kebabCase((string) $name)] = $value ? 'true' : 'false';
                continue;
            }
            if (is_int($value) || is_string($value)) {
                $attributes['data-odf-index-source-' . self::kebabCase((string) $name)] = (string) $value;
            }
        }

        if (isset($sourceMetadata['sourceStyles']) && is_array($sourceMetadata['sourceStyles'])) {
            $attributes['data-odf-index-source-style-count'] = (string) count($sourceMetadata['sourceStyles']);
        }
        if (isset($sourceMetadata['templates']) && is_array($sourceMetadata['templates'])) {
            $attributes['data-odf-index-template-count'] = (string) count($sourceMetadata['templates']);
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function generatedIndexTitleAndBodyNodes(\DOMElement $index, ZipPackage $package, array $catalog): array
    {
        $children = [];
        foreach (self::childElements($index) as $child) {
            if ($this->isElement($child, self::TEXT_NS, 'index-title')) {
                $titleBlocks = $this->blockNodes($child, $package, $catalog);
                if ($titleBlocks !== []) {
                    $children[] = new AstNode('div', [
                        'sourceFormat' => 'odt',
                        'classes' => ['odf-index-title'],
                        'attributes' => [
                            'data-odf-index-title' => 'true',
                        ],
                    ], $titleBlocks);
                }
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'index-body')) {
                $bodyBlocks = $this->blockNodes($child, $package, $catalog);
                if ($bodyBlocks !== []) {
                    $children[] = new AstNode('div', [
                        'sourceFormat' => 'odt',
                        'classes' => ['odf-index-body'],
                        'attributes' => [
                            'data-odf-index-body' => 'true',
                        ],
                    ], $bodyBlocks);
                }
            }
        }

        return $children;
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
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>} $catalog
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
        $tableName = self::attr($table, self::TABLE_NS, 'name');
        if ($tableName !== '') {
            $attrs['caption'] = $tableName;
            $attrs['tableName'] = $tableName;
            $attrs['htmlAttributes'] = [
                'data-odf-table-name' => $tableName,
            ];
        }

        $styleName = self::attr($table, self::TABLE_NS, 'style-name');
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['htmlAttributes']['data-odf-table-style-name'] = $styleName;
        }

        $templateName = self::attr($table, self::TABLE_NS, 'template-name');
        if ($templateName !== '') {
            $template = $catalog['tableTemplates'][$templateName] ?? null;
            $attrs['templateName'] = $templateName;
            $attrs['htmlAttributes']['data-odf-table-template-name'] = $templateName;
            $attrs['htmlAttributes']['data-odf-table-template-exists'] = is_array($template) ? 'true' : 'false';
            $attrs['classes'] = ['odf-table-template'];
            if (is_array($template)) {
                $attrs['tableTemplate'] = $template;
                $attrs['htmlAttributes']['data-odf-table-template-style-count'] = (string) count($this->tableTemplateStyleNames($template));
            } else {
                $attrs['classes'][] = 'odf-missing-table-template';
            }
        }

        $protectedValue = strtolower(self::attr($table, self::TABLE_NS, 'protected'));
        if ($protectedValue !== '') {
            $protected = in_array($protectedValue, ['true', '1'], true);
            $attrs['protected'] = $protected;
            $attrs['htmlAttributes']['data-odf-table-protected'] = $protected ? 'true' : 'false';
        }

        $protectionKey = self::attr($table, self::TABLE_NS, 'protection-key');
        if ($protectionKey !== '') {
            $attrs['protectionKeyPresent'] = true;
            $attrs['htmlAttributes']['data-odf-table-protection-key-present'] = 'true';
        }

        $digestAlgorithm = self::attr($table, self::TABLE_NS, 'protection-key-digest-algorithm');
        if ($digestAlgorithm !== '') {
            $attrs['protectionKeyDigestAlgorithm'] = $digestAlgorithm;
            $attrs['htmlAttributes']['data-odf-table-protection-key-digest-algorithm'] = $digestAlgorithm;
        }
        if ($columnWidths !== []) {
            $attrs['widths'] = $columnWidths;
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children), [
            'idPrefix' => $tableName === '' ? 'odf-table' : $tableName,
        ]);
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
        $metadata = $this->tableCellMetadata($cell);
        if ($metadata !== []) {
            $attrs['odfCellMetadata'] = $metadata;
            $attrs['htmlAttributes'] = $this->tableCellMetadataHtmlAttributes($metadata);
            $classes = [];
            if ($this->tableCellMetadataHasTypedValue($metadata)) {
                $classes[] = 'odf-table-cell-value';
            }
            if (isset($metadata['formula'])) {
                $classes[] = 'odf-table-cell-formula';
            }
            if ($classes !== []) {
                $attrs['classes'] = $classes;
            }
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    /**
     * @return array<string, bool|string>
     */
    private function tableCellMetadata(\DOMElement $cell): array
    {
        return self::withoutEmpty([
            'formula' => self::nullable(self::attr($cell, self::TABLE_NS, 'formula')),
            'valueType' => self::nullable(self::attr($cell, self::OFFICE_NS, 'value-type')),
            'value' => self::nullable(self::attr($cell, self::OFFICE_NS, 'value')),
            'currency' => self::nullable(self::attr($cell, self::OFFICE_NS, 'currency')),
            'stringValue' => self::nullable(self::attr($cell, self::OFFICE_NS, 'string-value')),
            'dateValue' => self::nullable(self::attr($cell, self::OFFICE_NS, 'date-value')),
            'timeValue' => self::nullable(self::attr($cell, self::OFFICE_NS, 'time-value')),
            'booleanValue' => self::nullableBool(self::attr($cell, self::OFFICE_NS, 'boolean-value')),
        ]);
    }

    /**
     * @param array<string, bool|string> $metadata
     * @return array<string, string>
     */
    private function tableCellMetadataHtmlAttributes(array $metadata): array
    {
        $attributes = [];
        $map = [
            'formula' => 'data-odf-cell-formula',
            'valueType' => 'data-odf-cell-value-type',
            'value' => 'data-odf-cell-value',
            'currency' => 'data-odf-cell-currency',
            'stringValue' => 'data-odf-cell-string-value',
            'dateValue' => 'data-odf-cell-date-value',
            'timeValue' => 'data-odf-cell-time-value',
            'booleanValue' => 'data-odf-cell-boolean-value',
        ];

        foreach ($map as $source => $target) {
            if (!array_key_exists($source, $metadata)) {
                continue;
            }

            $value = $metadata[$source];
            $attributes[$target] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        return $attributes;
    }

    /**
     * @param array<string, bool|string> $metadata
     */
    private function tableCellMetadataHasTypedValue(array $metadata): bool
    {
        foreach (['valueType', 'value', 'currency', 'stringValue', 'dateValue', 'timeValue', 'booleanValue'] as $key) {
            if (array_key_exists($key, $metadata)) {
                return true;
            }
        }

        return false;
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

        $control = self::firstChildElement($frame, 'control', self::DRAW_NS);
        if ($control instanceof \DOMElement) {
            return $this->formControlNode($control, $frame, false);
        }

        $objectOle = $this->frameObjectOleNode($frame, $package, false);
        if ($objectOle instanceof AstNode) {
            return $objectOle;
        }

        $math = $this->frameObjectMathNode($frame, $package);
        if ($math instanceof AstNode) {
            return new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => (string) $math->attr('text', ''),
            ], [$math]);
        }

        $object = $this->frameObjectNode($frame, $package, false);
        if ($object instanceof AstNode) {
            return $object;
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
     * @return array<string, array<string, mixed>>
     */
    private function formControlsFromText(\DOMElement $text): array
    {
        $forms = self::firstChildElement($text, 'forms', self::OFFICE_NS);
        if (!$forms instanceof \DOMElement) {
            return [];
        }

        $controls = [];
        $this->collectFormControls($forms, [], $controls);

        return $controls;
    }

    /**
     * @param array<string, mixed> $formMetadata
     * @param array<string, array<string, mixed>> $controls
     */
    private function collectFormControls(\DOMElement $container, array $formMetadata, array &$controls): void
    {
        if ($this->isElement($container, self::FORM_NS, 'form')) {
            $formMetadata = array_merge($formMetadata, $this->formDefinition($container));
        }

        foreach (self::childElements($container) as $child) {
            if ($this->isElement($child, self::FORM_NS, 'form')) {
                $this->collectFormControls($child, $formMetadata, $controls);
                continue;
            }

            if ($child->namespaceURI !== self::FORM_NS || !in_array($child->localName, self::formControlElementNames(), true)) {
                continue;
            }

            $control = $this->formControlDefinition($child, $formMetadata);
            if ($control !== null) {
                $controls[(string) $control['id']] = $control;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formDefinition(\DOMElement $form): array
    {
        $targetFrame = self::attr($form, self::FORM_NS, 'target-frame');
        if ($targetFrame === '') {
            $targetFrame = self::attr($form, self::OFFICE_NS, 'target-frame-name');
        }

        return self::withoutEmpty([
            'name' => self::nullable(self::attr($form, self::FORM_NS, 'name')),
            'action' => self::nullable($this->formAction($form)),
            'method' => self::nullable(self::attr($form, self::FORM_NS, 'method')),
            'enctype' => self::nullable(self::attr($form, self::FORM_NS, 'enctype')),
            'targetFrame' => self::nullable($targetFrame),
            'command' => self::nullable(self::attr($form, self::FORM_NS, 'command')),
            'commandType' => self::nullable(self::attr($form, self::FORM_NS, 'command-type')),
            'datasource' => self::nullable(self::attr($form, self::FORM_NS, 'datasource')),
            'filter' => self::nullable(self::attr($form, self::FORM_NS, 'filter')),
            'order' => self::nullable(self::attr($form, self::FORM_NS, 'order')),
            'applyFilter' => self::nullableBool(self::attr($form, self::FORM_NS, 'apply-filter')),
            'ignoreResult' => self::nullableBool(self::attr($form, self::FORM_NS, 'ignore-result')),
            'escapeProcessing' => self::nullableBool(self::attr($form, self::FORM_NS, 'escape-processing')),
            'navigationMode' => self::nullable(self::attr($form, self::FORM_NS, 'navigation-mode')),
            'tabCycle' => self::nullable(self::attr($form, self::FORM_NS, 'tab-cycle')),
            'masterFields' => self::nullable(self::attr($form, self::FORM_NS, 'master-fields')),
            'detailFields' => self::nullable(self::attr($form, self::FORM_NS, 'detail-fields')),
            'defaultControlImplementation' => self::nullable(self::attr($form, self::FORM_NS, 'control-implementation')),
            'xlinkType' => self::nullable(self::attr($form, self::XLINK_NS, 'type')),
            'xlinkShow' => self::nullable(self::attr($form, self::XLINK_NS, 'show')),
            'xlinkActuate' => self::nullable(self::attr($form, self::XLINK_NS, 'actuate')),
        ]);
    }

    private function formAction(\DOMElement $form): string
    {
        $href = self::attr($form, self::XLINK_NS, 'href');
        if ($href !== '') {
            return $href;
        }

        $action = self::attr($form, self::FORM_NS, 'action');
        if ($action !== '') {
            return $action;
        }

        return self::attr($form, self::FORM_NS, 'href');
    }

    /**
     * @return ?array<string, mixed>
     */
    private function formControlDefinition(\DOMElement $control, array $formMetadata): ?array
    {
        $id = self::attr($control, self::FORM_NS, 'id');
        if ($id === '') {
            $id = self::attr($control, self::FORM_NS, 'name');
        }
        if ($id === '') {
            return null;
        }

        return self::withoutEmpty(array_merge([
            'id' => $id,
            'type' => $control->localName,
            'name' => self::nullable(self::attr($control, self::FORM_NS, 'name')),
            'label' => self::nullable(self::attr($control, self::FORM_NS, 'label')),
            'implementation' => self::nullable(self::attr($control, self::FORM_NS, 'control-implementation')),
            'value' => self::nullable(self::attr($control, self::FORM_NS, 'value')),
            'currentValue' => self::nullable(self::attr($control, self::FORM_NS, 'current-value')),
            'currentState' => self::nullable(self::attr($control, self::FORM_NS, 'current-state')),
            'linkedCell' => self::nullable(self::attr($control, self::FORM_NS, 'linked-cell')),
            'sourceCellRange' => self::nullable(self::attr($control, self::FORM_NS, 'source-cell-range')),
            'tabIndex' => self::nullableInt(self::attr($control, self::FORM_NS, 'tab-index')),
            'href' => self::nullable(self::attr($control, self::XLINK_NS, 'href')),
            'disabled' => self::nullableBool(self::attr($control, self::FORM_NS, 'disabled')),
            'printable' => self::nullableBool(self::attr($control, self::FORM_NS, 'printable')),
            'formMetadata' => $formMetadata,
        ], $this->prefixedFormMetadata($formMetadata)));
    }

    /**
     * @param array<string, mixed> $formMetadata
     * @return array<string, mixed>
     */
    private function prefixedFormMetadata(array $formMetadata): array
    {
        $prefixed = [];
        foreach ($formMetadata as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $prefixed['form' . ucfirst($name)] = $value;
        }

        return $prefixed;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentDeclarationsFromText(\DOMElement $text): array
    {
        $sequenceDeclarations = [];
        $sequenceDecls = self::firstChildElement($text, 'sequence-decls', self::TEXT_NS);
        if ($sequenceDecls instanceof \DOMElement) {
            foreach (self::childElements($sequenceDecls, 'sequence-decl', self::TEXT_NS) as $decl) {
                $name = self::attr($decl, self::TEXT_NS, 'name');
                if ($name === '') {
                    continue;
                }

                $sequenceDeclarations[$name] = self::withoutEmpty([
                    'name' => $name,
                    'displayOutlineLevel' => self::nullableInt(self::attr($decl, self::TEXT_NS, 'display-outline-level')),
                    'separationCharacter' => self::nullable(self::attr($decl, self::TEXT_NS, 'separation-character')),
                ]);
            }
        }

        $variableDeclarations = [];
        $variableDecls = self::firstChildElement($text, 'variable-decls', self::TEXT_NS);
        if ($variableDecls instanceof \DOMElement) {
            foreach (self::childElements($variableDecls, 'variable-decl', self::TEXT_NS) as $decl) {
                $name = self::attr($decl, self::TEXT_NS, 'name');
                if ($name === '') {
                    continue;
                }

                $variableDeclarations[$name] = self::withoutEmpty([
                    'name' => $name,
                    'valueType' => self::nullable(self::attr($decl, self::OFFICE_NS, 'value-type')),
                ]);
            }
        }

        $userFieldDeclarations = [];
        $userFieldDecls = self::firstChildElement($text, 'user-field-decls', self::TEXT_NS);
        if ($userFieldDecls instanceof \DOMElement) {
            foreach (self::childElements($userFieldDecls, 'user-field-decl', self::TEXT_NS) as $decl) {
                $name = self::attr($decl, self::TEXT_NS, 'name');
                if ($name === '') {
                    continue;
                }

                $dateValue = self::attr($decl, self::OFFICE_NS, 'date-value');
                if ($dateValue === '') {
                    $dateValue = self::attr($decl, self::TEXT_NS, 'date-value');
                }
                $timeValue = self::attr($decl, self::OFFICE_NS, 'time-value');
                if ($timeValue === '') {
                    $timeValue = self::attr($decl, self::TEXT_NS, 'time-value');
                }
                $booleanValue = self::nullableBool(self::attr($decl, self::OFFICE_NS, 'boolean-value'));

                $userFieldDeclarations[$name] = self::withoutEmpty([
                    'name' => $name,
                    'valueType' => self::nullable(self::attr($decl, self::OFFICE_NS, 'value-type')),
                    'value' => self::nullable(self::attr($decl, self::OFFICE_NS, 'value')),
                    'stringValue' => self::nullable(self::attr($decl, self::OFFICE_NS, 'string-value')),
                    'dateValue' => self::nullable($dateValue),
                    'timeValue' => self::nullable($timeValue),
                    'booleanValue' => $booleanValue,
                    'currency' => self::nullable(self::attr($decl, self::OFFICE_NS, 'currency')),
                ]);
            }
        }

        return [
            'sequenceDeclarationCount' => count($sequenceDeclarations),
            'sequenceDeclarations' => $sequenceDeclarations,
            'variableDeclarationCount' => count($variableDeclarations),
            'variableDeclarations' => $variableDeclarations,
            'userFieldDeclarationCount' => count($userFieldDeclarations),
            'userFieldDeclarations' => $userFieldDeclarations,
        ];
    }

    private function formControlNode(\DOMElement $controlReference, ?\DOMElement $frame, bool $inline): ?AstNode
    {
        $controlId = self::attr($controlReference, self::DRAW_NS, 'control');
        if ($controlId === '') {
            $controlId = self::attr($controlReference, self::FORM_NS, 'id');
        }
        if ($controlId === '') {
            return null;
        }

        $definition = $this->formControlsById[$controlId] ?? null;
        $exists = is_array($definition);
        $type = $exists ? (string) ($definition['type'] ?? '') : '';
        $label = $this->formControlLabel($controlId, $definition, $frame);

        $attributes = [
            'data-odf-control-id' => $controlId,
        ];
        if ($type !== '') {
            $attributes['data-odf-control-type'] = $type;
        }
        $attributes['data-odf-control-exists'] = $exists ? 'true' : 'false';

        if (is_array($definition)) {
            foreach ([
                'formName',
                'name',
                'label',
                'implementation',
                'value',
                'currentValue',
                'currentState',
                'linkedCell',
                'sourceCellRange',
                'tabIndex',
                'href',
                'disabled',
                'printable',
                'formAction',
                'formMethod',
                'formEnctype',
                'formTargetFrame',
                'formCommand',
                'formCommandType',
                'formDatasource',
                'formFilter',
                'formOrder',
                'formApplyFilter',
                'formIgnoreResult',
                'formEscapeProcessing',
                'formNavigationMode',
                'formTabCycle',
                'formMasterFields',
                'formDetailFields',
                'formDefaultControlImplementation',
                'formXlinkType',
                'formXlinkShow',
                'formXlinkActuate',
            ] as $name) {
                if (!array_key_exists($name, $definition)) {
                    continue;
                }
                $value = $definition[$name];
                $attributes['data-odf-control-' . self::kebabCase($name)] = is_bool($value)
                    ? ($value ? 'true' : 'false')
                    : (string) $value;
            }
        }

        $frameMetadata = $frame instanceof \DOMElement ? $this->formControlFrameMetadata($frame) : [];
        foreach ($frameMetadata as $name => $value) {
            $attributes['data-odf-control-' . self::kebabCase($name)] = $value;
        }

        $classes = ['odf-form-control'];
        if ($type !== '') {
            $classes[] = 'odf-control-' . self::formControlClassSuffix($type);
        }
        if (!$exists) {
            $classes[] = 'odf-missing-form-control';
        }

        $attrs = [
            'sourceFormat' => 'odt-form-control',
            'controlId' => $controlId,
            'exists' => $exists,
            'classes' => $classes,
            'attributes' => $attributes,
        ];
        if ($type !== '') {
            $attrs['controlType'] = $type;
        }
        if (is_array($definition)) {
            $attrs['formControl'] = $definition;
        }
        foreach ($frameMetadata as $name => $value) {
            $attrs[$name] = $value;
        }

        $text = new AstNode('text', ['text' => $label]);
        if ($inline) {
            return new AstNode('span', $attrs, [$text]);
        }

        return new AstNode('div', $attrs, [
            new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => $label,
            ], [$text]),
        ]);
    }

    /**
     * @param ?array<string, mixed> $definition
     */
    private function formControlLabel(string $controlId, ?array $definition, ?\DOMElement $frame): string
    {
        if (is_array($definition)) {
            foreach (['label', 'currentValue', 'value', 'name'] as $name) {
                $value = (string) ($definition[$name] ?? '');
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if ($frame instanceof \DOMElement) {
            $title = self::firstChildElement($frame, 'title', self::SVG_NS);
            if ($title instanceof \DOMElement && self::normalizedText($title) !== '') {
                return self::normalizedText($title);
            }
            $name = self::attr($frame, self::DRAW_NS, 'name');
            if ($name !== '') {
                return $name;
            }
        }

        return $controlId;
    }

    /**
     * @return array<string, string>
     */
    private function formControlFrameMetadata(\DOMElement $frame): array
    {
        $metadata = [];
        $name = self::attr($frame, self::DRAW_NS, 'name');
        if ($name !== '') {
            $metadata['frameName'] = $name;
        }

        foreach (['width', 'height'] as $dimension) {
            $value = self::attr($frame, self::SVG_NS, $dimension);
            if ($value !== '') {
                $metadata[$dimension] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @return list<string>
     */
    private static function formControlElementNames(): array
    {
        return [
            'button',
            'checkbox',
            'combobox',
            'currency',
            'date',
            'file',
            'fixed-text',
            'formatted-text',
            'generic-control',
            'grid',
            'hidden',
            'image',
            'image-frame',
            'listbox',
            'numeric',
            'password',
            'pattern',
            'radio',
            'text',
            'textarea',
            'time',
            'value-range',
        ];
    }

    private static function formControlClassSuffix(string $type): string
    {
        $suffix = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $type) ?? '');
        $suffix = trim($suffix, '-');

        return $suffix === '' ? 'unknown' : $suffix;
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
            if ($this->isElement($child, self::TEXT_NS, 'ruby')) {
                $ruby = $this->rubyNode($child, $catalog, $package);
                if ($ruby instanceof AstNode) {
                    $nodes[] = $ruby;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'a')) {
                $nodes[] = $this->linkNode($child, $catalog, $package);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 's')) {
                $spaceCount = max(1, self::intAttr($child, self::TEXT_NS, 'c', 1));
                $nodes[] = new AstNode('text', ['text' => str_repeat(' ', $spaceCount)]);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'tab')) {
                $nodes[] = new AstNode('text', ['text' => ' ']);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'line-break')) {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'soft-page-break')) {
                $nodes[] = $this->softPageBreakNode();
                continue;
            }
            if ($this->isIndexMarkStartElement($child)) {
                $markId = self::attr($child, self::TEXT_NS, 'id');
                $range = $markId === '' ? null : $this->indexMarkRange($children, $index, $markId, $this->indexMarkEndElementName($child->localName));
                if ($range !== null) {
                    $inner = $this->coalesceTextNodes($this->inlineNodesFromNodeList($range['nodes'], $catalog, $package));
                    $node = $this->indexMarkNode($child, $catalog, $package, $inner);
                    if ($node instanceof AstNode) {
                        $nodes[] = $node;
                    }
                    $index = $range['endIndex'];
                    continue;
                }
            }
            if ($this->isIndexMarkElement($child)) {
                $indexMark = $this->indexMarkNode($child, $catalog, $package);
                if ($indexMark instanceof AstNode) {
                    $nodes[] = $indexMark;
                }
                continue;
            }
            if ($this->isIndexMarkEndElement($child)) {
                continue;
            }
            if ($this->isTextFieldElement($child)) {
                $field = $this->fieldNode($child, $catalog, $package);
                if ($field instanceof AstNode) {
                    $nodes[] = $field;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'placeholder')) {
                $placeholder = $this->placeholderNode($child, $catalog, $package);
                if ($placeholder instanceof AstNode) {
                    $nodes[] = $placeholder;
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
                $control = self::firstChildElement($child, 'control', self::DRAW_NS);
                if ($control instanceof \DOMElement) {
                    $controlNode = $this->formControlNode($control, $child, true);
                    if ($controlNode instanceof AstNode) {
                        $nodes[] = $controlNode;
                    }
                    continue;
                }
                $textBoxCaptionImage = $this->frameTextBoxCaptionImageNode($child, $catalog, $package);
                if ($textBoxCaptionImage instanceof AstNode) {
                    $nodes[] = $textBoxCaptionImage;
                    continue;
                }
                $image = $this->frameImageNode($child, $package);
                if ($image instanceof AstNode) {
                    $nodes[] = $image;
                    continue;
                }
                $math = $this->frameObjectMathNode($child, $package);
                if ($math instanceof AstNode) {
                    $nodes[] = $math;
                    continue;
                }
                $objectOle = $this->frameObjectOleNode($child, $package, true);
                if ($objectOle instanceof AstNode) {
                    $nodes[] = $objectOle;
                    continue;
                }
                $object = $this->frameObjectNode($child, $package, true);
                if ($object instanceof AstNode) {
                    $nodes[] = $object;
                }
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'control')) {
                $control = $this->formControlNode($child, null, true);
                if ($control instanceof AstNode) {
                    $nodes[] = $control;
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

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function rubyNode(\DOMElement $ruby, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $base = self::firstChildElement($ruby, 'ruby-base', self::TEXT_NS);
        $baseNodes = $base instanceof \DOMElement
            ? $this->coalesceTextNodes($this->inlineNodes($base, $catalog, $package))
            : $this->coalesceTextNodes($this->rubyFallbackBaseNodes($ruby, $catalog, $package));
        if ($baseNodes === []) {
            return null;
        }

        $rubyText = self::firstChildElement($ruby, 'ruby-text', self::TEXT_NS);
        $annotation = '';
        $textStyleName = '';
        if ($rubyText instanceof \DOMElement) {
            $annotationNodes = $this->coalesceTextNodes($this->inlineNodes($rubyText, $catalog, $package));
            $annotation = $this->plainInlineText($annotationNodes);
            if ($annotation === '') {
                $annotation = self::normalizedText($rubyText);
            }
            $textStyleName = self::attr($rubyText, self::TEXT_NS, 'style-name');
        }

        $styleName = self::attr($ruby, self::TEXT_NS, 'style-name');
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => ['odf-ruby'],
            'attributes' => [],
        ];
        if ($annotation !== '') {
            $attrs['rubyText'] = $annotation;
            $attrs['attributes']['data-odf-ruby-text'] = $annotation;
        }
        if ($styleName !== '') {
            $attrs['rubyStyleName'] = $styleName;
            $attrs['attributes']['data-odf-ruby-style-name'] = $styleName;
        }
        if ($textStyleName !== '') {
            $attrs['rubyTextStyleName'] = $textStyleName;
            $attrs['attributes']['data-odf-ruby-text-style-name'] = $textStyleName;
        }

        return new AstNode('span', $attrs, $baseNodes);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function rubyFallbackBaseNodes(\DOMElement $ruby, array $catalog, ?ZipPackage $package): array
    {
        $children = [];
        foreach ($ruby->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isElement($child, self::TEXT_NS, 'ruby-text')) {
                continue;
            }
            $children[] = $child;
        }

        return $this->inlineNodesFromNodeList($children, $catalog, $package);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function linkNode(\DOMElement $link, array $catalog, ?ZipPackage $package): AstNode
    {
        $attrs = [
            'url' => self::fixRelativeLink(self::attr($link, self::XLINK_NS, 'href')),
        ];
        $title = self::attr($link, self::OFFICE_NS, 'title');
        if ($title !== '') {
            $attrs['title'] = $title;
        }

        $metadata = self::withoutEmpty([
            'name' => self::nullable(self::attr($link, self::OFFICE_NS, 'name')),
            'styleName' => self::nullable(self::attr($link, self::TEXT_NS, 'style-name')),
            'visitedStyleName' => self::nullable(self::attr($link, self::TEXT_NS, 'visited-style-name')),
            'targetFrameName' => self::nullable(self::attr($link, self::OFFICE_NS, 'target-frame-name')),
            'type' => self::nullable(self::attr($link, self::XLINK_NS, 'type')),
            'show' => self::nullable(self::attr($link, self::XLINK_NS, 'show')),
            'actuate' => self::nullable(self::attr($link, self::XLINK_NS, 'actuate')),
        ]);
        if ($metadata !== []) {
            $attrs['sourceFormat'] = 'odt';
            $attrs['odfLinkMetadata'] = $metadata;
            $attrs['classes'] = ['odf-link'];
            $attrs['attributes'] = [];
            foreach ($metadata as $name => $value) {
                $attrs['attributes']['data-odf-link-' . self::kebabCase((string) $name)] = (string) $value;
            }
        }

        return new AstNode('link', $attrs, $this->coalesceTextNodes($this->inlineNodes($link, $catalog, $package)));
    }

    private static function fixRelativeLink(string $uri): string
    {
        if (!str_starts_with($uri, '../')) {
            return $uri;
        }

        return substr($uri, 3);
    }

    private function softPageBreakNode(): AstNode
    {
        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'softPageBreak' => true,
            'classes' => ['odf-soft-page-break'],
            'attributes' => [
                'data-odf-soft-page-break' => 'true',
            ],
        ]);
    }

    private function isIndexMarkElement(\DOMElement $element): bool
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return false;
        }

        return in_array($element->localName, [
            'toc-mark',
            'toc-mark-start',
            'alphabetical-index-mark',
            'alphabetical-index-mark-start',
            'user-index-mark',
            'user-index-mark-start',
        ], true);
    }

    private function isIndexMarkStartElement(\DOMElement $element): bool
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return false;
        }

        return in_array($element->localName, [
            'toc-mark-start',
            'alphabetical-index-mark-start',
            'user-index-mark-start',
        ], true);
    }

    private function isIndexMarkEndElement(\DOMElement $element): bool
    {
        if ($element->namespaceURI !== self::TEXT_NS) {
            return false;
        }

        return in_array($element->localName, [
            'toc-mark-end',
            'alphabetical-index-mark-end',
            'user-index-mark-end',
        ], true);
    }

    private function indexMarkEndElementName(string $startElement): string
    {
        return substr($startElement, 0, -strlen('-start')) . '-end';
    }

    /**
     * @param list<\DOMNode> $children
     * @return ?array{nodes:list<\DOMNode>, endIndex:int}
     */
    private function indexMarkRange(array $children, int $startIndex, string $id, string $endElement): ?array
    {
        $range = [];
        for ($index = $startIndex + 1, $count = count($children); $index < $count; $index++) {
            $child = $children[$index];
            if ($child instanceof \DOMElement && $this->isElement($child, self::TEXT_NS, $endElement)) {
                $endId = self::attr($child, self::TEXT_NS, 'id');
                if ($endId === $id) {
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
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @param list<AstNode>|null $children
     */
    private function indexMarkNode(\DOMElement $mark, array $catalog, ?ZipPackage $package, ?array $children = null): ?AstNode
    {
        $children ??= $this->coalesceTextNodes($this->inlineNodes($mark, $catalog, $package));
        if ($children === []) {
            $text = (string) ($this->indexMarkMetadata($mark)['stringValue'] ?? '');
            if ($text === '') {
                $text = self::normalizedText($mark);
            }
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $type = $this->indexMarkType($mark->localName);
        $metadata = $this->indexMarkMetadata($mark);
        $attributes = [
            'data-odf-index-mark-type' => $type,
            'data-odf-index-mark-element' => $mark->localName,
        ];
        foreach ($metadata as $name => $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            $attributes['data-odf-index-mark-' . self::kebabCase((string) $name)] = is_bool($value)
                ? ($value ? 'true' : 'false')
                : (string) $value;
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'indexMarkType' => $type,
            'indexMarkElement' => $mark->localName,
            'indexMarkMetadata' => $metadata,
            'classes' => ['odf-index-mark', 'odf-index-mark-' . $type],
            'attributes' => $attributes,
        ], $children);
    }

    private function indexMarkType(string $element): string
    {
        if (str_starts_with($element, 'toc-mark')) {
            return 'toc';
        }
        if (str_starts_with($element, 'user-index-mark')) {
            return 'user';
        }

        return 'alphabetical';
    }

    /**
     * @return array<string, mixed>
     */
    private function indexMarkMetadata(\DOMElement $mark): array
    {
        return $this->odfElementMetadataAttributes($mark, [self::TEXT_NS]);
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
    private function placeholderNode(\DOMElement $placeholder, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($placeholder, $catalog, $package));
        if ($children === []) {
            $text = self::normalizedText($placeholder);
            if ($text === '') {
                return null;
            }
            $children = [new AstNode('text', ['text' => $text])];
        }

        $metadata = $this->placeholderMetadata($placeholder);
        $placeholderType = (string) ($metadata['type'] ?? 'unknown');
        $attributes = [];
        foreach ($metadata as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $attributes['data-odf-placeholder-' . self::kebabCase((string) $name)] = (string) $value;
        }

        return new AstNode('span', self::withoutEmpty([
            'sourceFormat' => 'odt',
            'placeholderType' => $placeholderType,
            'placeholderMetadata' => $metadata,
            'classes' => ['odf-placeholder', 'odf-placeholder-' . self::placeholderClassSuffix($placeholderType)],
            'attributes' => $attributes,
        ]), $children);
    }

    /**
     * @return array<string, string>
     */
    private function placeholderMetadata(\DOMElement $placeholder): array
    {
        return self::withoutEmpty([
            'type' => self::nullable(self::attr($placeholder, self::TEXT_NS, 'placeholder-type')),
            'description' => self::nullable(self::attr($placeholder, self::TEXT_NS, 'description')),
            'styleName' => self::nullable(self::attr($placeholder, self::TEXT_NS, 'style-name')),
        ]);
    }

    private static function placeholderClassSuffix(string $type): string
    {
        $suffix = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $type) ?? '');
        $suffix = trim($suffix, '-');

        return $suffix === '' ? 'unknown' : $suffix;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function fieldNode(\DOMElement $field, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($field, $catalog, $package));
        $metadata = $this->fieldMetadata($field);
        if ($children === []) {
            $metadata = $this->fieldMetadataWithDeclarations($field, $metadata);
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
     * @return array<string, mixed>
     */
    private function fieldMetadataWithDeclarations(\DOMElement $field, array $metadata): array
    {
        if (!$this->isElement($field, self::TEXT_NS, 'user-field-get')) {
            return $metadata;
        }

        $name = (string) ($metadata['name'] ?? '');
        if ($name === '') {
            return $metadata;
        }

        $userFieldDeclarations = $this->contentDeclarations['userFieldDeclarations'] ?? [];
        if (!is_array($userFieldDeclarations)) {
            return $metadata;
        }

        $declaration = $userFieldDeclarations[$name] ?? null;
        if (!is_array($declaration)) {
            return $metadata;
        }

        foreach (['valueType', 'value', 'stringValue', 'dateValue', 'timeValue', 'booleanValue', 'currency'] as $key) {
            if (!array_key_exists($key, $declaration)) {
                continue;
            }
            if (!array_key_exists($key, $metadata) || $metadata[$key] === null || $metadata[$key] === '') {
                $metadata[$key] = $declaration[$key];
            }
        }
        $metadata['declared'] = true;

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

        foreach (['stringValue', 'value', 'dateValue', 'timeValue', 'booleanValue'] as $name) {
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

        if ($this->isInlineCodeTextStyle($styleName)) {
            return [new AstNode('code', [
                'sourceFormat' => 'odt',
                'text' => $this->plainInlineText($children),
                'styleName' => $styleName,
                'attributes' => [
                    'data-odf-style-name' => $styleName,
                ],
            ])];
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
        if (($properties['superscript'] ?? false) === true) {
            $children = [new AstNode('superscript', [], $children)];
        }
        if (($properties['subscript'] ?? false) === true) {
            $children = [new AstNode('subscript', [], $children)];
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

        $href = self::fixRelativeLink(self::attr($image, self::XLINK_NS, 'href'));
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
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function frameTextBoxCaptionImageNode(\DOMElement $frame, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $textBox = self::firstChildElement($frame, 'text-box', self::DRAW_NS);
        if (!$textBox instanceof \DOMElement) {
            return null;
        }

        foreach (self::childElements($textBox, 'p', self::TEXT_NS) as $paragraph) {
            $nodes = $this->coalesceTextNodes($this->inlineNodes($paragraph, $catalog, $package));
            foreach ($nodes as $index => $node) {
                if (!$node instanceof AstNode || $node->type !== 'image') {
                    continue;
                }

                $captionNodes = array_slice($nodes, $index + 1);
                $caption = trim($this->plainInlineText($captionNodes));

                return $this->captionedTextBoxImageNode(
                    $node,
                    $frame,
                    $caption,
                    $caption === '' ? $node->children : [new AstNode('text', ['text' => $caption])]
                );
            }
        }

        return null;
    }

    /**
     * @param list<AstNode> $children
     */
    private function captionedTextBoxImageNode(AstNode $image, \DOMElement $frame, string $caption, array $children): AstNode
    {
        $attrs = $image->attrs;
        if ($caption !== '') {
            $attrs['alt'] = $caption;
        }

        $title = (string) $image->attr('title', '');
        if ($title !== '' && !str_starts_with($title, 'fig:')) {
            $attrs['title'] = 'fig:' . $title;
        } elseif ($title !== '') {
            $attrs['title'] = $title;
        }

        $classes = $attrs['classes'] ?? [];
        if (!is_array($classes)) {
            $classes = [];
        }
        $classes[] = 'odf-text-box-image-caption';
        $attrs['classes'] = array_values(array_unique(array_filter(
            array_map(static fn (mixed $class): string => (string) $class, $classes),
            static fn (string $class): bool => $class !== ''
        )));

        $attributes = $attrs['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }
        $attributes['data-odf-text-box-caption'] = 'true';
        $frameName = self::attr($frame, self::DRAW_NS, 'name');
        if ($frameName !== '') {
            $attributes['data-odf-text-box-frame-name'] = $frameName;
        }
        $attrs['attributes'] = $attributes;

        return new AstNode('image', $attrs, $children);
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

    private function frameObjectOleNode(\DOMElement $frame, ?ZipPackage $package, bool $inline): ?AstNode
    {
        $object = self::firstChildElement($frame, 'object-ole', self::DRAW_NS);
        if (!$object instanceof \DOMElement) {
            return null;
        }

        $href = self::attr($object, self::XLINK_NS, 'href');
        if ($href === '') {
            return null;
        }

        $part = $this->manifestPackagePart($href);
        $objectPath = rtrim($part, '/');
        if ($objectPath === '') {
            return null;
        }

        $manifestItem = $this->objectManifestItem($part, $objectPath);
        $sourcePart = is_array($manifestItem) && is_string($manifestItem['part'] ?? null)
            ? (string) $manifestItem['part']
            : $part;
        $containedParts = $package instanceof ZipPackage ? $this->objectContainedParts($package, $objectPath) : [];
        $encrypted = is_array($manifestItem) && ($manifestItem['encrypted'] ?? false) === true;
        $mediaType = is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : '';
        $containedByteLength = $encrypted ? null : $this->containedPartsByteLength($package, $containedParts);
        $exists = $containedParts !== [] || ($package instanceof ZipPackage && $package->has($sourcePart) && !str_ends_with($sourcePart, '/'));
        $label = $this->objectLabel($frame, $object, $objectPath);

        $attributes = [
            'data-odf-object-type' => 'ole',
            'data-odf-object-href' => $href,
            'data-odf-object-path' => $objectPath,
            'data-odf-object-source-part' => $sourcePart,
        ];
        if ($mediaType !== '') {
            $attributes['data-odf-object-media-type'] = $mediaType;
        }
        $attributes['data-odf-object-exists'] = $exists ? 'true' : 'false';
        $attributes['data-odf-object-contained-part-count'] = (string) count($containedParts);
        if ($containedByteLength !== null && $containedByteLength > 0) {
            $attributes['data-odf-object-contained-byte-length'] = (string) $containedByteLength;
        }
        $attributes['data-odf-object-can-expose-bytes'] = 'false';
        if ($encrypted) {
            $attributes['data-odf-object-encrypted'] = 'true';
        }

        $attrs = [
            'sourceFormat' => 'odt-object-ole',
            'objectType' => 'ole',
            'href' => $href,
            'objectPath' => $objectPath,
            'sourcePart' => $sourcePart,
            'mediaType' => $mediaType === '' ? null : $mediaType,
            'exists' => $exists,
            'encrypted' => $encrypted,
            'canExposeBytes' => false,
            'containedParts' => $containedParts,
            'containedPartCount' => count($containedParts),
            'containedByteLength' => $containedByteLength,
            'classes' => ['odf-embedded-object', 'odf-object-ole'],
            'attributes' => $attributes,
        ];
        if (is_array($manifestItem)) {
            $attrs['manifestFullPath'] = $manifestItem['fullPath'] ?? null;
            $attrs['declaredSize'] = $manifestItem['declaredSize'] ?? null;
            $attrs['manifestExists'] = $manifestItem['exists'] ?? null;
            $attrs['encryption'] = $manifestItem['encryption'] ?? null;
        }

        $text = new AstNode('text', ['text' => $label]);
        if ($inline) {
            return new AstNode('span', $attrs, [$text]);
        }

        return new AstNode('div', $attrs, [
            new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => $label,
            ], [$text]),
        ]);
    }

    private function frameObjectNode(\DOMElement $frame, ?ZipPackage $package, bool $inline): ?AstNode
    {
        $object = self::firstChildElement($frame, 'object', self::DRAW_NS);
        if (!$object instanceof \DOMElement) {
            return null;
        }

        $href = self::attr($object, self::XLINK_NS, 'href');
        if ($href === '') {
            return null;
        }

        $part = $this->manifestPackagePart($href);
        $objectPath = rtrim($part, '/');
        if ($objectPath === '') {
            return null;
        }

        $manifestItem = $this->objectManifestItem($part, $objectPath);
        $sourcePart = is_array($manifestItem) && is_string($manifestItem['part'] ?? null)
            ? (string) $manifestItem['part']
            : $part;
        $containedParts = $package instanceof ZipPackage ? $this->objectContainedParts($package, $objectPath) : [];
        $encrypted = is_array($manifestItem) && ($manifestItem['encrypted'] ?? false) === true;
        $mediaType = is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : '';
        $objectType = $this->objectTypeForMediaType($mediaType);
        $containedByteLength = $encrypted ? null : $this->containedPartsByteLength($package, $containedParts);
        $exists = $containedParts !== [] || ($package instanceof ZipPackage && $package->has($sourcePart) && !str_ends_with($sourcePart, '/'));
        $label = $this->objectLabel($frame, $object, $objectPath);

        $attributes = [
            'data-odf-object-type' => $objectType,
            'data-odf-object-href' => $href,
            'data-odf-object-path' => $objectPath,
            'data-odf-object-source-part' => $sourcePart,
        ];
        if ($mediaType !== '') {
            $attributes['data-odf-object-media-type'] = $mediaType;
        }
        $attributes['data-odf-object-exists'] = $exists ? 'true' : 'false';
        $attributes['data-odf-object-contained-part-count'] = (string) count($containedParts);
        if ($containedByteLength !== null && $containedByteLength > 0) {
            $attributes['data-odf-object-contained-byte-length'] = (string) $containedByteLength;
        }
        $attributes['data-odf-object-can-expose-bytes'] = 'false';
        if ($encrypted) {
            $attributes['data-odf-object-encrypted'] = 'true';
        }

        $attrs = [
            'sourceFormat' => $objectType === 'object' ? 'odt-object' : 'odt-object-' . $objectType,
            'objectType' => $objectType,
            'href' => $href,
            'objectPath' => $objectPath,
            'sourcePart' => $sourcePart,
            'mediaType' => $mediaType === '' ? null : $mediaType,
            'exists' => $exists,
            'encrypted' => $encrypted,
            'canExposeBytes' => false,
            'containedParts' => $containedParts,
            'containedPartCount' => count($containedParts),
            'containedByteLength' => $containedByteLength,
            'classes' => ['odf-embedded-object', 'odf-object-' . self::objectClassSuffix($objectType)],
            'attributes' => $attributes,
        ];
        if (is_array($manifestItem)) {
            $attrs['manifestFullPath'] = $manifestItem['fullPath'] ?? null;
            $attrs['declaredSize'] = $manifestItem['declaredSize'] ?? null;
            $attrs['manifestExists'] = $manifestItem['exists'] ?? null;
            $attrs['encryption'] = $manifestItem['encryption'] ?? null;
        }

        $text = new AstNode('text', ['text' => $label]);
        if ($inline) {
            return new AstNode('span', $attrs, [$text]);
        }

        return new AstNode('div', $attrs, [
            new AstNode('paragraph', [
                'sourceFormat' => 'odt',
                'text' => $label,
            ], [$text]),
        ]);
    }

    private function objectTypeForMediaType(string $mediaType): string
    {
        $base = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return match ($base) {
            'application/vnd.oasis.opendocument.chart' => 'chart',
            'application/vnd.oasis.opendocument.graphics' => 'graphics',
            'application/vnd.oasis.opendocument.presentation' => 'presentation',
            'application/vnd.oasis.opendocument.spreadsheet' => 'spreadsheet',
            'application/vnd.oasis.opendocument.text' => 'text',
            default => 'object',
        };
    }

    private static function objectClassSuffix(string $type): string
    {
        $suffix = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $type) ?? '');
        $suffix = trim($suffix, '-');

        return $suffix === '' ? 'object' : $suffix;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function objectManifestItem(string $part, string $objectPath): ?array
    {
        $candidates = [$part];
        if (!str_ends_with($part, '/')) {
            $candidates[] = $part . '/';
        }
        $candidates[] = $objectPath;
        $candidates[] = $objectPath . '/';

        foreach (array_unique($candidates) as $candidate) {
            if (isset($this->manifestByPart[$candidate])) {
                return $this->manifestByPart[$candidate];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function objectContainedParts(ZipPackage $package, string $objectPath): array
    {
        $prefix = rtrim($objectPath, '/') . '/';
        $parts = [];
        foreach ($package->names() as $name) {
            if ($name === $objectPath || str_starts_with($name, $prefix)) {
                if (!str_ends_with($name, '/')) {
                    $parts[] = $name;
                }
            }
        }

        sort($parts);

        return $parts;
    }

    /**
     * @param list<string> $parts
     */
    private function containedPartsByteLength(?ZipPackage $package, array $parts): ?int
    {
        if (!$package instanceof ZipPackage || $parts === []) {
            return null;
        }

        $bytes = 0;
        foreach ($parts as $part) {
            if (!$package->has($part)) {
                continue;
            }
            $bytes += $package->entry($part)->uncompressedSize;
        }

        return $bytes;
    }

    private function objectLabel(\DOMElement $frame, \DOMElement $object, string $objectPath): string
    {
        $desc = self::firstChildElement($frame, 'desc', self::SVG_NS)
            ?? self::firstChildElement($object, 'desc', self::SVG_NS);
        if ($desc instanceof \DOMElement && self::normalizedText($desc) !== '') {
            return self::normalizedText($desc);
        }

        $title = self::firstChildElement($frame, 'title', self::SVG_NS)
            ?? self::firstChildElement($object, 'title', self::SVG_NS);
        if ($title instanceof \DOMElement && self::normalizedText($title) !== '') {
            return self::normalizedText($title);
        }

        $name = self::attr($frame, self::DRAW_NS, 'name');
        if ($name !== '') {
            return $name;
        }

        $basename = basename($objectPath);

        return $basename === '' ? 'Embedded ODF object' : $basename;
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
     * @return array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>}
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

        $tableTemplates = [];
        foreach ($root->getElementsByTagNameNS(self::TABLE_NS, 'table-template') as $tableTemplate) {
            if (!$tableTemplate instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($tableTemplate, self::TABLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $tableTemplates[$name] = $this->tableTemplateDefinition($tableTemplate);
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
            'tableTemplates' => $tableTemplates,
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
            $definition['paragraphProperties'] = $this->paragraphProperties($paragraphProperties);
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
    private function paragraphProperties(\DOMElement $properties): array
    {
        $result = self::withoutEmpty([
            'textAlign' => self::nullable(self::attr($properties, self::FO_NS, 'text-align')),
            'breakBefore' => self::nullable(self::attr($properties, self::FO_NS, 'break-before')),
            'breakAfter' => self::nullable(self::attr($properties, self::FO_NS, 'break-after')),
            'keepTogether' => self::nullable(self::attr($properties, self::FO_NS, 'keep-together')),
            'keepWithNext' => self::nullable(self::attr($properties, self::FO_NS, 'keep-with-next')),
            'autoTextIndent' => self::nullableBool(self::attr($properties, self::STYLE_NS, 'auto-text-indent')),
        ]);

        foreach ([
            'textIndent' => [self::FO_NS, 'text-indent'],
            'marginLeft' => [self::FO_NS, 'margin-left'],
        ] as $target => [$namespace, $attribute]) {
            $value = self::attr($properties, $namespace, $attribute);
            if ($value === '') {
                continue;
            }

            $result[$target] = $value;
            if (str_ends_with($value, '%')) {
                $result[$target . 'Percent'] = (float) rtrim($value, '%');
                continue;
            }

            $points = $this->lengthToPoints($value);
            if ($points !== null) {
                $result[$target . 'Points'] = $points;
            }
        }

        return self::withoutEmpty($result);
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
        $position = strtolower(self::attr($properties, self::STYLE_NS, 'text-position'));

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
        if (str_contains($position, 'super')) {
            $result['superscript'] = true;
        } elseif (str_contains($position, 'sub')) {
            $result['subscript'] = true;
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
                'numPrefix' => self::attr($levelStyle, self::STYLE_NS, 'num-prefix'),
                'numSuffix' => self::attr($levelStyle, self::STYLE_NS, 'num-suffix'),
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
     * @return array{name:string, styles:array<string, string>}
     */
    private function tableTemplateDefinition(\DOMElement $tableTemplate): array
    {
        $styles = [];
        foreach ([
            'first-row-start-column' => 'firstRowStartColumn',
            'first-row-end-column' => 'firstRowEndColumn',
            'first-column' => 'firstColumn',
            'last-column' => 'lastColumn',
            'first-row' => 'firstRow',
            'last-row' => 'lastRow',
            'body' => 'body',
            'odd-rows' => 'oddRows',
            'even-rows' => 'evenRows',
            'odd-columns' => 'oddColumns',
            'even-columns' => 'evenColumns',
        ] as $attribute => $name) {
            $value = self::attr($tableTemplate, self::TABLE_NS, $attribute);
            if ($value !== '') {
                $styles[$name] = $value;
            }
        }

        return [
            'name' => self::attr($tableTemplate, self::TABLE_NS, 'name'),
            'styles' => $styles,
        ];
    }

    /**
     * @param array<string, mixed> $template
     * @return list<string>
     */
    private function tableTemplateStyleNames(array $template): array
    {
        $styles = $template['styles'] ?? [];
        if (!is_array($styles)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => (string) $value, $styles),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $target
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>, tableTemplates:array<string, array<string, mixed>>, pageLayouts:array<string, array<string, mixed>>, masterPages:array<string, array<string, mixed>>} $source
     */
    private function mergeStyleCollections(array &$target, array $source): void
    {
        foreach ($source['styles'] as $name => $style) {
            $target['styles'][$name] = $style;
        }
        foreach ($source['listStyles'] as $name => $style) {
            $target['listStyles'][$name] = $style;
        }
        foreach ($source['tableTemplates'] as $name => $template) {
            $target['tableTemplates'][$name] = $template;
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
     * @param array<string, mixed> $style
     */
    private function isBlockquoteParagraphStyle(array $style): bool
    {
        $properties = $style['paragraphProperties'] ?? [];
        if (!is_array($properties)) {
            return false;
        }

        return $this->isQuoteWidth(
            (string) ($properties['textIndent'] ?? ''),
            (string) ($properties['marginLeft'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $style
     */
    private function isPreformattedParagraphStyle(string $styleName, array $style): bool
    {
        if ($style === []) {
            return false;
        }

        return $styleName === 'Preformatted_20_Text'
            || (string) ($style['parentName'] ?? '') === 'Preformatted_20_Text';
    }

    private function isInlineCodeTextStyle(string $styleName): bool
    {
        return $styleName === 'Source_Text' || $styleName === 'Source_20_Text';
    }

    private function isQuoteWidth(string $textIndent, string $marginLeft): bool
    {
        $indent = $this->quoteMeasure($textIndent);
        $margin = $this->quoteMeasure($marginLeft);
        $pointThreshold = 5.0 * 2.83464567;
        $percentThreshold = 5.0;

        if (($indent['unit'] ?? '') === 'pt' && $indent['value'] > $pointThreshold) {
            return true;
        }
        if (($margin['unit'] ?? '') === 'pt' && $margin['value'] > $pointThreshold) {
            return true;
        }
        if (($indent['unit'] ?? '') === 'pt' && ($margin['unit'] ?? '') === 'pt' && $indent['value'] + $margin['value'] > $pointThreshold) {
            return true;
        }
        if (($indent['unit'] ?? '') === 'percent' && $indent['value'] > $percentThreshold) {
            return true;
        }
        if (($margin['unit'] ?? '') === 'percent' && $margin['value'] > $percentThreshold) {
            return true;
        }
        if (($indent['unit'] ?? '') === 'percent' && ($margin['unit'] ?? '') === 'percent' && $indent['value'] + $margin['value'] > $percentThreshold) {
            return true;
        }

        return false;
    }

    /**
     * @return array{unit:'pt'|'percent', value:float}|null
     */
    private function quoteMeasure(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)%$/', $value, $matches) === 1) {
            return [
                'unit' => 'percent',
                'value' => (float) $matches[1],
            ];
        }

        $points = $this->lengthToPoints($value);
        if ($points === null) {
            return null;
        }

        return [
            'unit' => 'pt',
            'value' => $points,
        ];
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

    private function orderedListDelimiter(string $prefix, string $suffix): string
    {
        return match ([$prefix, $suffix]) {
            ['', '.'] => 'period',
            ['', ')'] => 'one_paren',
            ['(', ')'] => 'two_parens',
            default => ($prefix !== '' || $suffix !== '') ? 'default' : '',
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
            if ($mediaType === '' || $this->isXmlMediaType($mediaType)) {
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

    private function isXmlMediaType(string $mediaType): bool
    {
        $base = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return $base === 'text/xml'
            || $base === 'application/xml'
            || str_ends_with($base, '+xml');
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
     * @return array{blockquoteCount:int, noteCount:int, bookmarkCount:int, bookmarkReferenceCount:int, referenceMarkCount:int, referenceReferenceCount:int, indexMarkCount:int, sequenceCount:int, fieldCount:int, placeholderCount:int, rubyCount:int, softPageBreakCount:int, citationCount:int, annotationRangeCount:int, trackedChangeCount:int, mathCount:int, embeddedObjectCount:int, missingEmbeddedObjectCount:int, formControlCount:int, missingFormControlCount:int, sectionCount:int, linkedSectionCount:int, protectedSectionCount:int, tableOfContentsCount:int, generatedIndexCount:int, tableCaptionCount:int, preformattedCodeBlockCount:int, continuedListCount:int, listHeaderCount:int, tableTemplateReferenceCount:int}
     */
    private function contentNodeStats(array $nodes): array
    {
        $stats = [
            'blockquoteCount' => 0,
            'noteCount' => 0,
            'bookmarkCount' => 0,
            'bookmarkReferenceCount' => 0,
            'referenceMarkCount' => 0,
            'referenceReferenceCount' => 0,
            'indexMarkCount' => 0,
            'sequenceCount' => 0,
            'fieldCount' => 0,
            'placeholderCount' => 0,
            'rubyCount' => 0,
            'softPageBreakCount' => 0,
            'citationCount' => 0,
            'annotationRangeCount' => 0,
            'trackedChangeCount' => 0,
            'mathCount' => 0,
            'embeddedObjectCount' => 0,
            'missingEmbeddedObjectCount' => 0,
            'formControlCount' => 0,
            'missingFormControlCount' => 0,
            'sectionCount' => 0,
            'linkedSectionCount' => 0,
            'protectedSectionCount' => 0,
            'tableOfContentsCount' => 0,
            'generatedIndexCount' => 0,
            'tableCaptionCount' => 0,
            'preformattedCodeBlockCount' => 0,
            'continuedListCount' => 0,
            'listHeaderCount' => 0,
            'tableTemplateReferenceCount' => 0,
        ];
        foreach ($nodes as $node) {
            if ($node->type === 'note') {
                $stats['noteCount']++;
            }
            if ($node->type === 'blockquote' && $this->nodeHasClass($node, 'odf-blockquote')) {
                $stats['blockquoteCount']++;
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
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-table-of-contents')) {
                $stats['tableOfContentsCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-generated-index')) {
                $stats['generatedIndexCount']++;
            }
            if ($node->type === 'div' && $this->nodeHasClass($node, 'odf-table-caption')) {
                $stats['tableCaptionCount']++;
            }
            if ($node->type === 'table' && $node->attr('odfCaptionParagraph') === true) {
                $stats['tableCaptionCount']++;
            }
            if ($node->type === 'code_block' && $node->attr('odfPreformatted') === true) {
                $stats['preformattedCodeBlockCount']++;
            }
            if ($node->type === 'table' && (string) $node->attr('templateName', '') !== '') {
                $stats['tableTemplateReferenceCount']++;
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
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-index-mark')) {
                $stats['indexMarkCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-sequence')) {
                $stats['sequenceCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-field')) {
                $stats['fieldCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-placeholder')) {
                $stats['placeholderCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-ruby')) {
                $stats['rubyCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-soft-page-break')) {
                $stats['softPageBreakCount']++;
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
            if (($node->type === 'span' || $node->type === 'div') && $this->nodeHasClass($node, 'odf-embedded-object')) {
                $stats['embeddedObjectCount']++;
                if ($node->attr('exists') !== true) {
                    $stats['missingEmbeddedObjectCount']++;
                }
            }
            if (($node->type === 'span' || $node->type === 'div') && $this->nodeHasClass($node, 'odf-form-control')) {
                $stats['formControlCount']++;
                if ($node->attr('exists') !== true) {
                    $stats['missingFormControlCount']++;
                }
            }
            if (($node->type === 'ordered_list' || $node->type === 'bullet_list') && $node->attr('continued') === true) {
                $stats['continuedListCount']++;
            }
            if ($node->type === 'list_item' && $node->attr('listHeader') === true) {
                $stats['listHeaderCount']++;
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
        $path = rawurldecode($path);
        $path = ltrim($path, '/');
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        if ($path === '') {
            throw new \RuntimeException('ODT package part path must not be empty');
        }
        if (str_contains($path, '..') || str_contains($path, '\\') || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) === 1) {
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
     * @param list<AstNode> $inlines
     * @return array{inlines:list<AstNode>, anchor:?array{id:string, bookmarkName:string}}
     */
    private function extractHeadingBookmarkAnchor(array $inlines): array
    {
        $filtered = [];
        $anchor = null;
        foreach ($inlines as $node) {
            if ($anchor === null && $this->nodeHasClass($node, 'odf-bookmark')) {
                $id = (string) $node->attr('id', '');
                $attributes = $node->attr('attributes', []);
                $bookmarkName = is_array($attributes) ? (string) ($attributes['data-odf-bookmark-name'] ?? '') : '';
                if ($id !== '' && $bookmarkName !== '') {
                    $anchor = [
                        'id' => $id,
                        'bookmarkName' => $bookmarkName,
                    ];
                    continue;
                }
            }

            $filtered[] = $node;
        }

        return [
            'inlines' => $filtered,
            'anchor' => $anchor,
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @param array{id:string, bookmarkName:string} $anchor
     */
    private function addHeadingBookmarkAnchorAttrs(array &$attrs, array $anchor): void
    {
        $attrs['odfHeadingAnchor'] = [
            'source' => 'bookmark',
            'bookmarkName' => $anchor['bookmarkName'],
            'id' => $attrs['id'],
        ];
        $attrs['attributes']['data-odf-heading-anchor-source'] = 'bookmark';
        $attrs['attributes']['data-odf-heading-bookmark-name'] = $anchor['bookmarkName'];
        $attrs['attributes']['data-odf-heading-anchor-id'] = $attrs['id'];
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
            if ($node->type === 'code') {
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

    private static function nullableBool(string $value): ?bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['true', '1', 'yes', 'checked'], true);
    }

    private static function typedOdfAttributeValue(string $value): mixed
    {
        $normalized = strtolower(trim($value));
        if ($normalized === 'true' || $normalized === 'false') {
            return $normalized === 'true';
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $value;
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

    private function uniqueHeadingAnchor(string $text): string
    {
        $base = self::headingAnchorBase($text);

        return $this->uniqueHeadingAnchorFromBase($base);
    }

    private function uniqueHeadingAnchorFromBase(string $base): string
    {
        $base = $base === '' ? 'section' : $base;
        $seen = $this->headingAnchorUses[$base] ?? 0;
        $this->headingAnchorUses[$base] = $seen + 1;

        return $seen === 0 ? $base : $base . '-' . $seen;
    }

    private static function headingAnchorBase(string $text): string
    {
        $id = strtolower(trim($text));
        $id = preg_replace('/[^\pL\pN]+/u', '-', $id) ?? '';
        $id = trim($id, '-');

        return $id === '' ? 'section' : $id;
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
