<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubReader
{
    private const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    private const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    /**
     * @var array<string, string>
     */
    private const DC_METADATA_FIELD_KEYS = [
        'description' => 'description',
        'publisher' => 'publisher',
        'rights' => 'rights',
        'source' => 'source',
        'relation' => 'relation',
        'coverage' => 'coverage',
        'type' => 'type',
        'format' => 'format',
    ];

    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $bytes): AstNode
    {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create temporary EPUB path.');
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new \RuntimeException('Unable to write temporary EPUB package.');
            }

            return $this->readEpubFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function readEpubFile(string $path): AstNode
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('EPUB analysis needs PHP ZipArchive, which is unavailable in this runtime.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException("Unable to open EPUB package '{$path}'.");
        }

        try {
            $container_xml = $zip->getFromName('META-INF/container.xml');
            if (!is_string($container_xml)) {
                throw new \InvalidArgumentException('EPUB package is missing META-INF/container.xml.');
            }

            $rootfile = $this->rootfilePath($container_xml);
            $opf_xml = $zip->getFromName($rootfile);
            if (!is_string($opf_xml)) {
                throw new \InvalidArgumentException("EPUB package is missing OPF rootfile '{$rootfile}'.");
            }

            return $this->readPackage($zip, $rootfile, $opf_xml);
        } finally {
            $zip->close();
        }
    }

    private function readPackage(\ZipArchive $zip, string $rootfile, string $opf_xml): AstNode
    {
        $dom = $this->loadXml($opf_xml, 'EPUB OPF package');
        $package = $dom->documentElement;
        if (!$package instanceof \DOMElement || $package->localName !== 'package') {
            throw new \InvalidArgumentException('EPUB OPF root must be a package element.');
        }

        $base_path = $this->dirname($rootfile);
        $metadata = $this->metadata($package);
        $manifest = $this->manifest($package);
        $spine_items = $this->spineItems($package, $base_path, $manifest);
        $toc = $this->toc($zip, $base_path, $manifest, $this->spineTocId($package));
        $children = [];
        $resources = [];
        $referenced_resources = [];
        $media_bag_resources = [];
        $media_bag_sources = [];
        $image_resources = $this->imageResources($base_path, $manifest);
        $linear_spine_image_hrefs = $this->linearSpineImageHrefs($spine_items, $manifest);
        $spine_filenames = array_map(
            fn (array $spine_item): string => $this->spineFilename((string) ($spine_item['href'] ?? '')),
            array_values(array_filter(
                $spine_items,
                fn (array $spine_item): bool => ($spine_item['linear'] ?? true) === true
            ))
        );

        $cover = $this->coverImageHref($package, $manifest);
        $cover_part = $cover === null ? null : $this->stripUrlQueryAndFragment($cover);
        if ($cover !== null && !in_array($cover_part, $linear_spine_image_hrefs, true)) {
            $cover_resource = $this->recordMediaBagResource($cover, '', $base_path, $media_bag_resources);
            if ($cover_resource !== null) {
                $this->recordMediaBagSource($this->mediaBagSourceUrl($cover), $cover_resource, $media_bag_sources);
            }
            $children[] = new AstNode('paragraph', ['text' => ''], [
                new AstNode('image', [
                    'url' => $this->stripUrlQueryAndFragment($cover),
                    'title' => '',
                    'alt' => '',
                ]),
            ]);
        }

        foreach ($spine_items as $spine_item) {
            if (($spine_item['linear'] ?? true) !== true) {
                continue;
            }
            $idref = $spine_item['idref'];
            if (!isset($manifest[$idref])) {
                continue;
            }
            $item = $manifest[$idref];
            $media_type = $this->mediaTypeBase($item['media-type']);
            if ($this->isAbsoluteUrl($item['href'])) {
                continue;
            }
            $href = $this->packagePartPath($item['href'], $base_path);
            if ($this->isDirectSpineImageMediaType($media_type)) {
                if (!$this->zipEntryExists($zip, $href)) {
                    continue;
                }
                $resources[] = $href;
                $referenced_resources[] = $href;
                $media_bag_resources[] = $href;
                $this->recordMediaBagSource($this->mediaBagSourceUrl($item['href']), $href, $media_bag_sources);
                $children[] = $this->directImageSpineBlock($this->stripUrlQueryAndFragment($item['href']));
                continue;
            }
            if (!$this->isReadablePackageXhtml($href, $media_type)) {
                $children[] = $this->spineMarker($this->spineFilename($item['href']));
                continue;
            }
            $xhtml = $zip->getFromName($href);
            if (!is_string($xhtml)) {
                continue;
            }
            $resources[] = $href;
            $document = (new MarkdownReader([
                'htmlNativeDivs' => true,
                'htmlEpubExtensions' => true,
                'htmlImplicitHeadingIds' => false,
                'htmlPlainInlineBlocks' => true,
                'htmlPreserveSoftBreaks' => true,
            ]))->read($this->contentDocumentMarkup($xhtml));
            $document = $this->fixEpubContentReferences(
                $document,
                $item['href'],
                $base_path,
                $spine_filenames,
                $referenced_resources,
                $media_bag_resources,
                $media_bag_sources
            );
            $children[] = $this->spineMarker($this->spineFilename($item['href']));
            array_push($children, ...$document->children);
        }

        if ($children === []) {
            $children[] = new AstNode('paragraph', ['text' => 'No readable EPUB spine content was found.'], [
                new AstNode('text', ['text' => 'No readable EPUB spine content was found.']),
            ]);
        }

        $metadata['epubRootfile'] = $rootfile;
        $metadata['epubManifestItemCount'] = count($manifest);
        $metadata['epubManifestItems'] = $this->manifestItemsMetadata($base_path, $manifest);
        $metadata['epubSpineItems'] = count($spine_items);
        $metadata['epubSpineItemRefs'] = $spine_items;
        $metadata['epubReadableResources'] = $resources;
        $metadata['epubReferencedResources'] = array_values(array_unique($referenced_resources));
        $metadata['epubImageResources'] = $image_resources;
        $metadata['epubMediaBagResources'] = array_values(array_unique($media_bag_resources));
        $media_bag = $this->readEpubMediaBag($zip, $base_path, $manifest, $media_bag_sources);
        $metadata['epubMediaResourcePolicy'] = 'reader-media-bag-from-emitted-image-resources';
        $metadata['epubMediaResourceDirectory'] = $media_bag['directory'];
        $metadata['epubMediaResourceCount'] = count($media_bag['directory']);
        $metadata['epubMediaResourceDiagnostics'] = $media_bag['diagnostics'];
        $metadata['epubTocResources'] = $toc['resources'];
        $metadata['epubTocEntryCount'] = count($toc['entries']);
        $metadata['epubLandmarkEntryCount'] = count($toc['landmarks']);
        $metadata['epubPageListEntryCount'] = count($toc['pageList']);
        $metadata['epubAuxiliaryNavigationEntryCount'] = count($toc['auxiliary']);
        $metadata['epubNavigationSectionCount'] = count($toc['sections']);
        $metadata['epubNavigationSectionTypes'] = $toc['sectionTypes'];
        if ($toc['entries'] !== []) {
            $metadata['epubTocEntries'] = $toc['entries'];
        }
        if ($toc['landmarks'] !== []) {
            $metadata['epubLandmarkEntries'] = $toc['landmarks'];
        }
        if ($toc['pageList'] !== []) {
            $metadata['epubPageListEntries'] = $toc['pageList'];
        }
        if ($toc['auxiliary'] !== []) {
            $metadata['epubAuxiliaryNavigationEntries'] = $toc['auxiliary'];
        }
        if ($toc['sections'] !== []) {
            $metadata['epubNavigationSections'] = $toc['sections'];
        }

        return new AstNode('document', ['meta' => $metadata], $children);
    }

    private function rootfilePath(string $container_xml): string
    {
        $dom = $this->loadXml($container_xml, 'EPUB container');
        $xpath = new \DOMXPath($dom);
        $rootfiles = $xpath->query('//*[local-name()="rootfile"]');
        if (!$rootfiles instanceof \DOMNodeList) {
            throw new \InvalidArgumentException('EPUB container rootfile list cannot be read.');
        }

        $fallback = '';
        foreach ($rootfiles as $rootfile) {
            if (!$rootfile instanceof \DOMElement) {
                continue;
            }
            $path = trim($rootfile->getAttribute('full-path'));
            if ($path === '') {
                continue;
            }
            if ($fallback === '') {
                $fallback = $path;
            }
            if ($this->mediaTypeBase($rootfile->getAttribute('media-type')) === self::OPF_MEDIA_TYPE) {
                return $this->packagePartPath($path);
            }
        }

        if ($fallback !== '') {
            return $this->packagePartPath($fallback);
        }

        throw new \InvalidArgumentException('EPUB container does not declare an OPF rootfile.');
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(\DOMElement $package): array
    {
        $metadata = null;
        foreach ($package->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'metadata') {
                $metadata = $child;
                break;
            }
        }
        if (!$metadata instanceof \DOMElement) {
            return [];
        }

        $dc_values = [];
        $property_values = [];
        foreach ($metadata->childNodes as $entry) {
            if (!$entry instanceof \DOMElement) {
                continue;
            }

            $text = $this->metadataElementText($entry);
            if ($this->isDublinCoreMetadataElement($entry)) {
                if ($text === '') {
                    continue;
                }
                $dc_values[$entry->localName][] = [
                    'value' => $text,
                    'id' => trim($entry->getAttribute('id')),
                ];
                continue;
            }

            if ($entry->localName !== 'meta' || $text === '') {
                continue;
            }

            $property = trim($entry->getAttribute('property'));
            if ($property !== '') {
                $property_values[$property][] = $text;
            }
        }

        $meta = [];
        $title = $this->firstMetadataValue($dc_values, 'title');
        if ($title !== null) {
            $meta['title'] = $title;
            $meta['titleInlines'] = $this->metadataTextInlines($title);
        }

        $creators = $this->metadataValueList($dc_values, 'creator');
        if ($creators !== []) {
            $meta['author'] = $this->collapseMetadataValueList($creators);
            $meta['authorInlines'] = array_map(fn (string $author): array => $this->metadataTextInlines($author), $creators);
        }

        $date = $this->firstMetadataValue($dc_values, 'date');
        if ($date !== null) {
            $meta['date'] = $date;
            $meta['dateInlines'] = $this->metadataTextInlines($date);
        }

        $languages = $this->metadataValueList($dc_values, 'language');
        if ($languages !== []) {
            $meta['lang'] = $languages[0];
            $meta['language'] = $languages[0];
            if (count($languages) > 1) {
                $meta['languages'] = $languages;
            }
        }

        $identifier = $this->selectedMetadataIdentifier($dc_values['identifier'] ?? [], trim($package->getAttribute('unique-identifier')));
        if ($identifier !== null) {
            $meta['identifier'] = $identifier;
        }

        foreach (self::DC_METADATA_FIELD_KEYS as $dc_name => $meta_key) {
            $values = $this->metadataValueList($dc_values, $dc_name);
            if ($values !== []) {
                $meta[$meta_key] = $this->collapseMetadataValueList($values);
            }
        }

        $subjects = $this->metadataValueList($dc_values, 'subject');
        if ($subjects !== []) {
            $meta['subject'] = $this->collapseMetadataValueList($subjects);
        }

        if (isset($property_values['dcterms:modified'][0])) {
            $meta['modified'] = $property_values['dcterms:modified'][0];
        }
        if ($property_values !== []) {
            ksort($property_values, SORT_STRING);
            $meta['epubProperties'] = $property_values;
        }

        return $meta;
    }

    private function isDublinCoreMetadataElement(\DOMElement $element): bool
    {
        return $element->namespaceURI === self::DC_NAMESPACE
            || array_key_exists($element->localName, self::DC_METADATA_FIELD_KEYS)
            || in_array($element->localName, ['title', 'creator', 'date', 'language', 'identifier', 'subject'], true);
    }

    /**
     * @param array<string, list<array{value: string, id: string}>> $values
     */
    private function firstMetadataValue(array $values, string $name): ?string
    {
        return $values[$name][0]['value'] ?? null;
    }

    /**
     * @param array<string, list<array{value: string, id: string}>> $values
     * @return list<string>
     */
    private function metadataValueList(array $values, string $name): array
    {
        $items = [];
        foreach ($values[$name] ?? [] as $item) {
            $items[] = $item['value'];
        }

        return $items;
    }

    /**
     * @param list<string> $values
     * @return string|list<string>
     */
    private function collapseMetadataValueList(array $values): string|array
    {
        return count($values) === 1 ? $values[0] : $values;
    }

    /**
     * @param list<array{value: string, id: string}> $identifiers
     */
    private function selectedMetadataIdentifier(array $identifiers, string $unique_identifier_id): ?string
    {
        foreach ($identifiers as $identifier) {
            if ($unique_identifier_id !== '' && $identifier['id'] === $unique_identifier_id) {
                return $identifier['value'];
            }
        }

        return $identifiers[0]['value'] ?? null;
    }

    private function metadataElementText(\DOMElement $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
    }

    /**
     * @return list<AstNode>
     */
    private function metadataTextInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @return array<string, array{href: string, media-type: string, properties: list<string>}>
     */
    private function manifest(\DOMElement $package): array
    {
        $items = [];
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'item') {
                continue;
            }
            $id = trim($element->getAttribute('id'));
            $href = trim($element->getAttribute('href'));
            if ($id === '' || $href === '') {
                continue;
            }
            $items[$id] = [
                'href' => html_entity_decode($href, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                'media-type' => trim($element->getAttribute('media-type')),
                'properties' => array_values(array_filter(
                    preg_split('/\s+/', trim($element->getAttribute('properties'))) ?: [],
                    static fn (string $property): bool => $property !== ''
                )),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @return list<string>
     */
    private function imageResources(string $base_path, array $manifest): array
    {
        $resources = [];
        foreach ($manifest as $item) {
            if ($this->isAbsoluteUrl($item['href'])) {
                continue;
            }
            $href = $this->packagePartPath($item['href'], $base_path);
            $media_type = strtolower($item['media-type']);
            if (str_starts_with($media_type, 'image/') || $this->pathLooksLikeImage($href)) {
                $resources[] = $href;
            }
        }

        return array_values(array_unique($resources));
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @return list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>, external: bool, readable: bool, navigation: bool, ncx: bool, coverImage: bool}>
     */
    private function manifestItemsMetadata(string $base_path, array $manifest): array
    {
        $items = [];
        foreach ($manifest as $id => $item) {
            $href = $item['href'];
            $path = $this->rewriteRelativeResourceUrl($href, $base_path);
            $part_path = $this->isAbsoluteUrl($href) ? $path : $this->packagePartPath($href, $base_path);
            $media_type = strtolower($item['media-type']);
            $properties = $item['properties'];
            $lower_properties = array_map('strtolower', $properties);

            $items[] = [
                'id' => $id,
                'href' => $href,
                'path' => $path,
                'mediaType' => $item['media-type'],
                'properties' => $properties,
                'external' => $this->isAbsoluteUrl($href),
                'readable' => !$this->isAbsoluteUrl($href) && $this->isReadableSpineItem($part_path, $media_type),
                'navigation' => in_array('nav', $lower_properties, true),
                'ncx' => str_contains($media_type, 'x-dtbncx') || str_ends_with(strtolower($part_path), '.ncx'),
                'coverImage' => in_array('cover-image', $lower_properties, true),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @return list<array{index: int, id: ?string, idref: string, href: string, path: string, mediaType: string, linear: bool, properties: list<string>, manifestProperties: list<string>, missingManifestItem: bool, external: bool, readable: bool}>
     */
    private function spineItems(\DOMElement $package, string $base_path, array $manifest): array
    {
        $items = [];
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'itemref') {
                continue;
            }
            $idref = trim($element->getAttribute('idref'));
            if ($idref === '') {
                continue;
            }
            $manifest_item = $manifest[$idref] ?? null;
            $href = is_array($manifest_item) ? $manifest_item['href'] : '';
            $path = $href === '' ? '' : $this->rewriteRelativeResourceUrl($href, $base_path);
            $part_path = $href === '' || $this->isAbsoluteUrl($href) ? $path : $this->packagePartPath($href, $base_path);
            $media_type = is_array($manifest_item) ? $manifest_item['media-type'] : '';
            $external = $href !== '' && $this->isAbsoluteUrl($href);
            $linear = !$element->hasAttribute('linear') || $element->getAttribute('linear') === 'yes';

            $items[] = [
                'index' => count($items),
                'id' => trim($element->getAttribute('id')) !== '' ? trim($element->getAttribute('id')) : null,
                'idref' => $idref,
                'href' => $href,
                'path' => $path,
                'mediaType' => $media_type,
                'linear' => $linear,
                'properties' => $this->tokenList($element->getAttribute('properties')),
                'manifestProperties' => is_array($manifest_item) ? $manifest_item['properties'] : [],
                'missingManifestItem' => !is_array($manifest_item),
                'external' => $external,
                'readable' => is_array($manifest_item) && !$external && $this->isReadableSpineItem($part_path, $media_type),
            ];
        }

        return $items;
    }

    private function spineTocId(\DOMElement $package): string
    {
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'spine') {
                continue;
            }

            return trim($element->getAttribute('toc'));
        }

        return '';
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @return array{resources: list<string>, entries: list<array{text: string, href: string, level: int}>, landmarks: list<array{text: string, href: string, level: int, epubTypes: list<string>}>, pageList: list<array{text: string, href: string, level: int}>, auxiliary: list<array{text: string, href: string, level: int, sectionType: string}>, sections: list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}>, sectionTypes: list<string>}
     */
    private function toc(\ZipArchive $zip, string $base_path, array $manifest, string $spine_toc_id): array
    {
        $resources = [];
        $nav_entries = [];
        $ncx_entries = [];
        $landmark_entries = [];
        $page_list_entries = [];
        $auxiliary_entries = [];
        $navigation_sections = [];
        $section_types = [];
        foreach ($manifest as $id => $item) {
            if ($this->isAbsoluteUrl($item['href'])) {
                continue;
            }
            $href = $this->packagePartPath($item['href'], $base_path);
            $media_type = strtolower($item['media-type']);
            $properties = array_map('strtolower', $item['properties']);
            $is_nav = in_array('nav', $properties, true);
            $is_ncx = $id === $spine_toc_id || str_contains($media_type, 'x-dtbncx') || str_ends_with(strtolower($href), '.ncx');
            if (!$is_nav && !$is_ncx) {
                continue;
            }

            $xml = $zip->getFromName($href);
            if (!is_string($xml)) {
                continue;
            }
            $resources[] = $href;
            try {
                if ($is_nav) {
                    $sections = $this->xhtmlNavigationSections($xml, $this->dirname($href), $href);
                    array_push($navigation_sections, ...$sections);
                    foreach ($sections as $section) {
                        foreach ($section['types'] as $type) {
                            $section_types[$type] = true;
                        }
                    }
                    array_push($nav_entries, ...$this->tocEntriesFromNavigationSections($sections));
                    array_push($landmark_entries, ...$this->navigationSectionEntriesByType($sections, 'landmarks'));
                    array_push($page_list_entries, ...$this->navigationSectionEntriesByType($sections, 'page-list'));
                    array_push($auxiliary_entries, ...$this->auxiliaryNavigationSectionEntries($sections));
                }
                if ($is_ncx) {
                    array_push($ncx_entries, ...$this->ncxTocEntries($xml, $this->dirname($href)));
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        $section_types = array_keys($section_types);
        sort($section_types, SORT_STRING);

        return [
            'resources' => array_values(array_unique($resources)),
            'entries' => $nav_entries !== [] ? $nav_entries : $ncx_entries,
            'landmarks' => $landmark_entries,
            'pageList' => $page_list_entries,
            'auxiliary' => $auxiliary_entries,
            'sections' => $navigation_sections,
            'sectionTypes' => $section_types,
        ];
    }

    /**
     * @return list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}>
     */
    private function xhtmlNavigationSections(string $xml, string $base_path, string $resource): array
    {
        $dom = $this->loadXml($xml, 'EPUB nav document');
        $sections = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }

            $types = $this->tokenList($this->epubTypeAttribute($element));
            $type = $this->navigationSectionPrimaryType($types);
            $entries = $type === 'landmarks'
                ? $this->xhtmlLandmarkListEntries($element, $base_path, 1)
                : $this->xhtmlNavListEntries($element, $base_path, 1);
            $sections[] = [
                'type' => $type,
                'types' => $types,
                'label' => $this->xhtmlNavigationSectionLabel($element),
                'resource' => $resource,
                'entryCount' => count($entries),
                'entries' => $entries,
            ];
        }

        return $sections;
    }

    /**
     * @param list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}> $sections
     * @return list<array{text: string, href: string, level: int}>
     */
    private function tocEntriesFromNavigationSections(array $sections): array
    {
        $tocSections = array_values(array_filter(
            $sections,
            static fn (array $section): bool => in_array('toc', $section['types'], true)
        ));
        $source = $tocSections !== [] ? $tocSections : $sections;
        $entries = [];
        foreach ($source as $section) {
            foreach ($section['entries'] as $entry) {
                $entries[] = [
                    'text' => (string) ($entry['text'] ?? ''),
                    'href' => (string) ($entry['href'] ?? ''),
                    'level' => (int) ($entry['level'] ?? 1),
                ];
            }
        }

        return $entries;
    }

    /**
     * @param list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}> $sections
     * @return list<array<string, mixed>>
     */
    private function navigationSectionEntriesByType(array $sections, string $type): array
    {
        $entries = [];
        foreach ($sections as $section) {
            if (!in_array($type, $section['types'], true)) {
                continue;
            }
            array_push($entries, ...$section['entries']);
        }

        return $entries;
    }

    /**
     * @param list<array{type: string, types: list<string>, label: string, resource: string, entryCount: int, entries: list<array<string, mixed>>}> $sections
     * @return list<array{text: string, href: string, level: int, sectionType: string}>
     */
    private function auxiliaryNavigationSectionEntries(array $sections): array
    {
        $entries = [];
        foreach ($sections as $section) {
            foreach ($section['types'] as $type) {
                if (in_array($type, ['toc', 'landmarks', 'page-list'], true)) {
                    continue;
                }
                foreach ($section['entries'] as $entry) {
                    $entries[] = [
                        'text' => (string) ($entry['text'] ?? ''),
                        'href' => (string) ($entry['href'] ?? ''),
                        'level' => (int) ($entry['level'] ?? 1),
                        'sectionType' => $type,
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * @param list<string> $types
     */
    private function navigationSectionPrimaryType(array $types): string
    {
        foreach (['toc', 'landmarks', 'page-list'] as $primary) {
            if (in_array($primary, $types, true)) {
                return $primary;
            }
        }

        return $types[0] ?? '';
    }

    private function xhtmlNavigationSectionLabel(\DOMElement $nav): string
    {
        foreach ($nav->childNodes as $child) {
            if (!$child instanceof \DOMElement || !preg_match('/^h[1-6]$/i', $child->localName)) {
                continue;
            }

            return trim(preg_replace('/\s+/u', ' ', $child->textContent) ?? $child->textContent);
        }

        return '';
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function xhtmlNavListEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'li') {
                $entry = $this->xhtmlNavListItemEntry($child, $base_path, $level);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
                foreach ($child->childNodes as $nested) {
                    if ($nested instanceof \DOMElement && in_array($nested->localName, ['ol', 'ul'], true)) {
                        array_push($entries, ...$this->xhtmlNavListEntries($nested, $base_path, $level + 1));
                    }
                }
                continue;
            }
            if (in_array($child->localName, ['ol', 'ul'], true)) {
                array_push($entries, ...$this->xhtmlNavListEntries($child, $base_path, $level));
                continue;
            }
            array_push($entries, ...$this->xhtmlNavListEntries($child, $base_path, $level));
        }

        return $entries;
    }

    /**
     * @return array{text: string, href: string, level: int}|null
     */
    private function xhtmlNavListItemEntry(\DOMElement $item, string $base_path, int $level): ?array
    {
        foreach ($item->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'a') {
                continue;
            }
            $href = html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $text = trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
            if ($href === '' || $text === '') {
                return null;
            }

            return [
                'text' => $text,
                'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                'level' => $level,
            ];
        }

        return null;
    }

    /**
     * @return list<array{text: string, href: string, level: int, epubTypes: list<string>}>
     */
    private function xhtmlLandmarkListEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'li') {
                $entry = $this->xhtmlLandmarkListItemEntry($child, $base_path, $level);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
                foreach ($child->childNodes as $nested) {
                    if ($nested instanceof \DOMElement && in_array($nested->localName, ['ol', 'ul'], true)) {
                        array_push($entries, ...$this->xhtmlLandmarkListEntries($nested, $base_path, $level + 1));
                    }
                }
                continue;
            }
            if (in_array($child->localName, ['ol', 'ul'], true)) {
                array_push($entries, ...$this->xhtmlLandmarkListEntries($child, $base_path, $level));
                continue;
            }
            array_push($entries, ...$this->xhtmlLandmarkListEntries($child, $base_path, $level));
        }

        return $entries;
    }

    /**
     * @return array{text: string, href: string, level: int, epubTypes: list<string>}|null
     */
    private function xhtmlLandmarkListItemEntry(\DOMElement $item, string $base_path, int $level): ?array
    {
        foreach ($item->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            if (!in_array($element->localName, ['a', 'span'], true)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                return null;
            }
            $href = $element->localName === 'a'
                ? html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8')
                : '';
            $epub_types = $this->tokenList($this->epubTypeAttribute($element));
            if ($epub_types === []) {
                $epub_types = $this->tokenList($this->epubTypeAttribute($item));
            }

            return [
                'text' => $text,
                'href' => $href === '' ? '' : $this->rewriteRelativeResourceUrl($href, $base_path),
                'level' => $level,
                'epubTypes' => $epub_types,
            ];
        }

        return null;
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function ncxTocEntries(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB NCX table of contents');
        $navMap = null;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'navMap') {
                $navMap = $element;
                break;
            }
        }

        return $navMap instanceof \DOMElement ? $this->ncxNavPointEntries($navMap, $base_path, 1) : [];
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function ncxNavPointEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'navPoint') {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $this->firstDescendantText($child, 'text')) ?? $this->firstDescendantText($child, 'text'));
            $content = $this->firstDescendantElement($child, 'content');
            $href = $content instanceof \DOMElement ? html_entity_decode($content->getAttribute('src'), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            if ($text !== '' && $href !== '') {
                $entries[] = [
                    'text' => $text,
                    'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                    'level' => $level,
                ];
            }
            array_push($entries, ...$this->ncxNavPointEntries($child, $base_path, $level + 1));
        }

        return $entries;
    }

    private function contentDocumentMarkup(string $xhtml): string
    {
        $xhtml = preg_replace('/^\xEF\xBB\xBF/', '', $xhtml) ?? $xhtml;
        $xhtml = preg_replace('/^\s*<\?xml[^>]*>\s*/i', '', $xhtml) ?? $xhtml;

        return ltrim($xhtml);
    }

    private function spineMarker(string $filename): AstNode
    {
        return new AstNode('paragraph', ['text' => ''], [
            new AstNode('span', ['id' => $filename], []),
        ]);
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     */
    private function coverImageHref(\DOMElement $package, array $manifest): ?string
    {
        $cover_id = '';
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'meta') {
                continue;
            }
            if (strtolower(trim($element->getAttribute('name'))) === 'cover') {
                $cover_id = trim($element->getAttribute('content'));
                break;
            }
        }

        foreach ($manifest as $id => $item) {
            $properties = array_map('strtolower', $item['properties']);
            if ($id === $cover_id || in_array('cover-image', $properties, true)) {
                return $item['href'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $spine_filenames
     * @param list<string> $referenced_resources
     * @param list<string> $media_bag_resources
     */
    private function fixEpubContentReferences(
        AstNode $document,
        string $content_path,
        string $package_base_path,
        array $spine_filenames,
        array &$referenced_resources,
        array &$media_bag_resources,
        array &$media_bag_sources
    ): AstNode {
        $content_part = $this->stripUrlQueryAndFragment($content_path);
        $filename = $this->spineFilename($content_part);
        $content_dir = $this->dirname($content_part);

        return $this->fixEpubNode($document, $filename, $content_dir, $package_base_path, $spine_filenames, $referenced_resources, $media_bag_resources, $media_bag_sources);
    }

    /**
     * @param list<string> $spine_filenames
     * @param list<string> $referenced_resources
     * @param list<string> $media_bag_resources
     * @param array<string, string> $media_bag_sources
     */
    private function fixEpubNode(
        AstNode $node,
        string $filename,
        string $content_dir,
        string $package_base_path,
        array $spine_filenames,
        array &$referenced_resources,
        array &$media_bag_resources,
        array &$media_bag_sources
    ): AstNode {
        $attrs = in_array($node->type, ['blockquote', 'definition_list'], true)
            ? []
            : $this->fixEpubNodeAttrs($node->attrs, $filename);
        if ($node->type === 'list_item') {
            unset($attrs['text']);
        }
        if ($node->type === 'link') {
            $url = (string) ($attrs['url'] ?? '');
            if ($url !== '') {
                $this->recordReferencedResource($url, $content_dir, $package_base_path, $referenced_resources);
                $attrs['url'] = $this->fixEpubLinkUrl($url, $filename, $spine_filenames);
            }
        } elseif ($node->type === 'image') {
            $url = (string) ($attrs['url'] ?? '');
            if ($url !== '') {
                $this->recordReferencedResource($url, $content_dir, $package_base_path, $referenced_resources);
                $resource = $this->recordMediaBagResource($url, $content_dir, $package_base_path, $media_bag_resources);
                $attrs['url'] = $this->fixEpubImageUrl($url, $content_dir);
                if ($resource !== null) {
                    $this->recordMediaBagSource($this->mediaBagSourceUrl($attrs['url']), $resource, $media_bag_sources);
                }
            }
        }

        $children = [];
        foreach ($node->children as $child) {
            $children[] = $this->fixEpubNode($child, $filename, $content_dir, $package_base_path, $spine_filenames, $referenced_resources, $media_bag_resources, $media_bag_sources);
        }
        $children = $this->trimTextBeforeInlineImages($children);

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function trimTextBeforeInlineImages(array $children): array
    {
        foreach ($children as $index => $child) {
            if ($child->type !== 'image' || $index === 0) {
                continue;
            }

            $previous = $children[$index - 1] ?? null;
            if (!$previous instanceof AstNode || $previous->type !== 'text') {
                continue;
            }

            $text = rtrim((string) $previous->attr('text', ''));
            if ($text === '') {
                unset($children[$index - 1]);
                continue;
            }
            if ($text !== $previous->attr('text', '')) {
                $children[$index - 1] = new AstNode('text', array_merge($previous->attrs, ['text' => $text]), $previous->children);
            }
        }

        return array_values($children);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixEpubNodeAttrs(array $attrs, string $filename): array
    {
        unset($attrs['htmlAttributes']);

        $attributes = [];
        if (isset($attrs['attributes']) && is_array($attrs['attributes'])) {
            foreach ($attrs['attributes'] as $name => $value) {
                $name = (string) $name;
                if (str_starts_with($name, 'epub:')) {
                    foreach ($this->tokenList((string) $value) as $epub_type) {
                        $attrs['classes'][] = $epub_type;
                    }
                    continue;
                }
                if ($name === 'xml:lang') {
                    $name = 'lang';
                }
                $attributes[$name] = $value;
            }
        }
        if ($attributes === []) {
            unset($attrs['attributes']);
        } else {
            $attrs['attributes'] = $attributes;
        }

        if (isset($attrs['id']) && is_string($attrs['id']) && $attrs['id'] !== '') {
            $attrs['id'] = $this->prefixedEpubId($filename, $attrs['id']);
        }

        if (isset($attrs['classes']) && is_array($attrs['classes'])) {
            $classes = [];
            foreach ($attrs['classes'] as $class) {
                $class = trim((string) $class);
                if ($class !== '' && !in_array($class, $classes, true)) {
                    $classes[] = $class;
                }
            }
            if ($classes === []) {
                unset($attrs['classes']);
            } else {
                $attrs['classes'] = $classes;
            }
        }

        if (isset($attrs['html']) && is_string($attrs['html']) && $attrs['html'] !== '') {
            $attrs['format'] ??= 'html';
            $attrs['text'] ??= $attrs['html'];
        }
        if (array_key_exists('loose', $attrs)) {
            unset($attrs['loose']);
        }

        return $attrs;
    }

    /**
     * @param list<string> $spine_filenames
     */
    private function fixEpubLinkUrl(string $url, string $filename, array $spine_filenames): string
    {
        if (
            $this->isAbsoluteUrl($url)
            || str_starts_with(strtolower($url), 'data:')
            || str_starts_with(strtolower($url), 'mailto:')
        ) {
            return $url;
        }

        [$path, $query, $fragment] = $this->splitUrlSuffix($url);
        if ($fragment !== null && $fragment !== '') {
            $target = $path === '' ? $filename : $this->spineFilename($path);
            $url = $this->prefixedEpubId($target, $fragment);
        } elseif ($query !== null && $path !== '') {
            $target = $this->spineFilename($path);
            if (in_array($target, $spine_filenames, true)) {
                return '#' . $target;
            }
        }

        foreach ($spine_filenames as $spine_filename) {
            if ($spine_filename !== '' && str_starts_with($url, $spine_filename)) {
                return '#' . $url;
            }
        }

        return $url;
    }

    private function fixEpubImageUrl(string $url, string $content_dir): string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return $url;
        }

        [$path, , $fragment] = $this->splitUrlSuffix($url);
        $normalized = $this->normalizeZipPath($content_dir . '/' . $path);

        return $fragment === null || $fragment === '' ? $normalized : $normalized . '#' . $fragment;
    }

    private function directImageSpineBlock(string $href): AstNode
    {
        return new AstNode('paragraph', ['text' => ''], [
            new AstNode('image', [
                'url' => $this->normalizeZipPath($href),
                'title' => '',
                'alt' => '',
            ]),
        ]);
    }

    /**
     * @param list<string> $referenced_resources
     */
    private function recordReferencedResource(string $url, string $content_dir, string $package_base_path, array &$referenced_resources): void
    {
        $this->recordPackageRelativeResource($url, $content_dir, $package_base_path, $referenced_resources, true);
    }

    /**
     * @param list<string> $media_bag_resources
     */
    private function recordMediaBagResource(string $url, string $content_dir, string $package_base_path, array &$media_bag_resources): ?string
    {
        return $this->recordPackageRelativeResource($url, $content_dir, $package_base_path, $media_bag_resources, false);
    }

    /**
     * @param array<string, string> $media_bag_sources
     */
    private function recordMediaBagSource(string $source, string $resource, array &$media_bag_sources): void
    {
        $source = $this->mediaBagSourceUrl($source);
        if ($source === '' || $resource === '') {
            return;
        }

        $media_bag_sources[$source] ??= $resource;
    }

    /**
     * @param list<string> $resources
     */
    private function recordPackageRelativeResource(
        string $url,
        string $content_dir,
        string $package_base_path,
        array &$resources,
        bool $include_fragment
    ): ?string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return null;
        }

        [$path, , $fragment] = $this->splitUrlSuffix($url);
        if ($path === '') {
            return null;
        }

        $resource = $this->normalizeZipPath($package_base_path . '/' . $content_dir . '/' . $path);
        if ($include_fragment && $fragment !== null && $fragment !== '') {
            $resource .= '#' . $fragment;
        }
        $resources[] = $resource;

        return $resource;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @param array<string, string> $media_bag_sources
     * @return array{directory: list<array<string, mixed>>, diagnostics: list<string>}
     */
    private function readEpubMediaBag(\ZipArchive $zip, string $base_path, array $manifest, array $media_bag_sources): array
    {
        $bag = new MediaBag();
        $diagnostics = [];
        $media_types = $this->manifestMediaTypesByResourcePath($base_path, $manifest);

        foreach ($media_bag_sources as $source => $resource) {
            $bytes = $zip->getFromName($resource);
            if (!is_string($bytes)) {
                $diagnostics[] = 'epub-media-resource-missing:' . $resource;
                continue;
            }

            $bag->insertMedia($source, $media_types[$resource] ?? null, $bytes);
            $diagnostics[] = 'epub-media-resource-loaded:' . $resource;
        }

        return [
            'directory' => $this->epubMediaResourceDirectory($bag->directory(), $media_bag_sources, $base_path),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @return array<string, string>
     */
    private function manifestMediaTypesByResourcePath(string $base_path, array $manifest): array
    {
        $media_types = [];
        foreach ($manifest as $item) {
            $href = $item['href'];
            if ($this->isAbsoluteUrl($href)) {
                continue;
            }
            $path = $this->packagePartPath($href, $base_path);
            $media_type = $this->mediaTypeBase($item['media-type']);
            if ($path !== '' && $media_type !== '') {
                $media_types[$path] = $media_type;
            }
        }

        return $media_types;
    }

    /**
     * @param list<array<string, mixed>> $directory
     * @param array<string, string> $media_bag_sources
     * @return list<array<string, mixed>>
     */
    private function epubMediaResourceDirectory(array $directory, array $media_bag_sources, string $base_path): array
    {
        $entries = [];
        foreach ($directory as $entry) {
            $source = is_string($entry['source'] ?? null) ? $entry['source'] : '';
            $zip_entry = $media_bag_sources[$source] ?? $source;
            $entry['zipEntry'] = $zip_entry;
            $entry['path'] = $this->pathRelativeToPackageBase($zip_entry, $base_path);
            $entries[] = $entry;
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => [
                (string) ($left['path'] ?? ''),
                (string) ($left['source'] ?? ''),
            ] <=> [
                (string) ($right['path'] ?? ''),
                (string) ($right['source'] ?? ''),
            ]
        );

        return $entries;
    }

    private function mediaBagSourceUrl(string $url): string
    {
        $source = str_replace('\\', '/', $url);
        $query = strpos($source, '?');
        $fragment = strpos($source, '#');
        $positions = array_filter(
            [$query, $fragment],
            static fn (int|false $position): bool => $position !== false
        );
        if ($positions !== []) {
            $source = substr($source, 0, min($positions));
        }

        return $source;
    }

    private function pathRelativeToPackageBase(string $path, string $base_path): string
    {
        $path = $this->normalizeZipPath($path);
        $base_path = $this->normalizeZipPath($base_path);
        if ($base_path !== '' && str_starts_with($path, $base_path . '/')) {
            return substr($path, strlen($base_path) + 1);
        }

        return $path;
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function splitUrlSuffix(string $url): array
    {
        $hash = strpos($url, '#');
        $without_fragment = $hash === false ? $url : substr($url, 0, $hash);
        $fragment = $hash === false ? null : substr($url, $hash + 1);
        $query_offset = strpos($without_fragment, '?');
        $path = $query_offset === false ? $without_fragment : substr($without_fragment, 0, $query_offset);
        $query = $query_offset === false ? null : substr($without_fragment, $query_offset + 1);

        return [$path, $query, $fragment];
    }

    private function appendUrlSuffix(string $path, ?string $query, ?string $fragment): string
    {
        if ($query !== null) {
            $path .= '?' . $query;
        }
        if ($fragment !== null) {
            $path .= '#' . $fragment;
        }

        return $path;
    }

    private function stripUrlQueryAndFragment(string $url): string
    {
        return $this->splitUrlSuffix($url)[0];
    }

    private function packagePartPath(string $url, string $base_path = ''): string
    {
        $path = $this->stripUrlQueryAndFragment($url);
        if ($path === '') {
            return '';
        }

        return $this->normalizeZipPath($base_path === '' ? $path : $base_path . '/' . $path);
    }

    private function prefixedEpubId(string $filename, string $id): string
    {
        return $id === '' ? '' : $filename . '_' . $id;
    }

    private function spineFilename(string $path): string
    {
        $path = $this->stripUrlQueryAndFragment($path);
        $filename = basename(str_replace('\\', '/', $path));

        return str_replace('%2F', '/', rawurlencode($filename));
    }

    private function rewriteRelativeResourceUrl(string $url, string $base_path): string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return $url;
        }

        [$path, $query, $fragment] = $this->splitUrlSuffix($url);
        if ($path === '') {
            return $url;
        }

        return $this->appendUrlSuffix($this->normalizeZipPath($base_path . '/' . $path), $query, $fragment);
    }

    private function isPackageRelativeResourceUrl(string $url): bool
    {
        return !$this->isAbsoluteUrl($url)
            && !str_starts_with($url, '#')
            && !str_starts_with(strtolower($url), 'data:')
            && !str_starts_with(strtolower($url), 'mailto:');
    }

    private function isReadablePackageXhtml(string $path, string $media_type): bool
    {
        $path = strtolower($path);
        $media_type = $this->mediaTypeBase($media_type);

        return str_contains($media_type, 'html')
            || str_ends_with($path, '.xhtml')
            || str_ends_with($path, '.html');
    }

    private function isReadableSpineItem(string $path, string $media_type): bool
    {
        return $this->isReadablePackageXhtml($path, $media_type)
            || $this->isDirectSpineImageMediaType($media_type);
    }

    private function isDirectSpineImageMediaType(string $media_type): bool
    {
        return in_array($this->mediaTypeBase($media_type), ['image/gif', 'image/jpeg', 'image/png'], true);
    }

    private function mediaTypeBase(string $media_type): string
    {
        return strtolower(trim(explode(';', $media_type, 2)[0]));
    }

    private function zipEntryExists(\ZipArchive $zip, string $path): bool
    {
        return $zip->statName($path) !== false;
    }

    /**
     * @param list<array<string, mixed>> $spine_items
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @return list<string>
     */
    private function linearSpineImageHrefs(array $spine_items, array $manifest): array
    {
        $hrefs = [];
        foreach ($spine_items as $spine_item) {
            if (($spine_item['linear'] ?? true) !== true) {
                continue;
            }
            $idref = is_string($spine_item['idref'] ?? null) ? $spine_item['idref'] : '';
            $item = $manifest[$idref] ?? null;
            if (!is_array($item)) {
                continue;
            }
            $href = $item['href'];
            if (!$this->isAbsoluteUrl($href) && $this->isDirectSpineImageMediaType($item['media-type'])) {
                $hrefs[] = $this->stripUrlQueryAndFragment($href);
            }
        }

        return array_values(array_unique($hrefs));
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '//');
    }

    private function pathLooksLikeImage(string $path): bool
    {
        return (bool) preg_match('/\.(?:apng|avif|bmp|gif|ico|jpe?g|png|svgz?|tiff?|webp)$/i', $path);
    }

    private function loadXml(string $xml, string $label): \DOMDocument
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException($label . ' needs DOMDocument, which is unavailable in this runtime.');
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new \InvalidArgumentException($label . ' is not valid XML.');
        }

        return $dom;
    }

    private function attributeByLocalName(\DOMElement $element, string $name): string
    {
        if ($element->hasAttribute($name)) {
            return $element->getAttribute($name);
        }
        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === $name) {
                return $attribute->value;
            }
        }

        return '';
    }

    private function epubTypeAttribute(\DOMElement $element): string
    {
        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr || $attribute->localName !== 'type') {
                continue;
            }
            if ($attribute->prefix === 'epub' || $attribute->namespaceURI === 'http://www.idpf.org/2007/ops') {
                return $attribute->value;
            }
        }

        return $this->attributeByLocalName($element, 'type');
    }

    /**
     * @return list<string>
     */
    private function tokenList(string $value): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', strtolower(trim($value))) ?: [],
            static fn (string $token): bool => $token !== ''
        ));
    }

    private function hasToken(string $value, string $token): bool
    {
        return in_array(strtolower($token), $this->tokenList($value), true);
    }

    private function firstDescendantText(\DOMElement $element, string $localName): string
    {
        $descendant = $this->firstDescendantElement($element, $localName);

        return $descendant instanceof \DOMElement ? $descendant->textContent : '';
    }

    private function firstDescendantElement(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement && $descendant->localName === $localName) {
                return $descendant;
            }
        }

        return null;
    }

    private function dirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));
        return $dir === '.' ? '' : $dir;
    }

    private function normalizeZipPath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
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
}
