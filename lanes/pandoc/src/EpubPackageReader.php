<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubPackageReader
{
    private const EPUB_TYPE_NS = 'http://www.idpf.org/2007/ops';

    public function readDirectory(string $directory): AstNode
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException('EPUB package directory does not exist: ' . $directory);
        }

        $rootfile = $this->readContainerRootfile($root);
        $opfPath = $this->resolveExistingPackagePath($root, $rootfile);
        $package = $this->readPackageDocument($root, $opfPath, $rootfile);
        $toc = $this->readNavigationDocument($root, $package);
        $ncx = $this->readNcxDocument($root, $package);
        $navReport = $this->navReport($toc, $package);
        $ncxReport = $this->ncxReport($ncx);
        $children = [];

        foreach ($package['spine'] as $spineItem) {
            if (($spineItem['readable'] ?? false) !== true) {
                continue;
            }

            $path = (string) ($spineItem['path'] ?? '');
            if ($path === '') {
                continue;
            }

            array_push($children, ...$this->readXhtmlDocument($root, $path));
        }

        return new AstNode('document', [
            'meta' => $package['metadata'],
            'epub' => [
                'containerRootfile' => $rootfile,
                'packageVersion' => $package['version'],
                'uniqueIdentifierId' => $package['uniqueIdentifierId'],
                'metadataProperties' => $package['metadataProperties'],
                'metadataLinks' => $package['metadataLinks'],
                'manifest' => array_values($package['manifest']),
                'manifestById' => $package['manifest'],
                'manifestReport' => $package['manifestReport'],
                'spine' => $package['spine'],
                'spineReport' => $package['spineReport'],
                'guide' => $package['guide'],
                'toc' => $toc,
                'tocReport' => $navReport,
                'navReport' => $navReport,
                'ncx' => $ncx,
                'ncxReport' => $ncxReport,
            ],
        ], $children);
    }

    private function readContainerRootfile(string $root): string
    {
        $path = $root . DIRECTORY_SEPARATOR . 'META-INF' . DIRECTORY_SEPARATOR . 'container.xml';
        $document = $this->loadXmlFile($path);
        $xpath = new \DOMXPath($document);
        $rootfile = $xpath->query('/*[local-name()="container"]/*[local-name()="rootfiles"]/*[local-name()="rootfile"][1]');
        $element = $rootfile instanceof \DOMNodeList ? $rootfile->item(0) : null;
        if (!$element instanceof \DOMElement) {
            throw new \RuntimeException('EPUB container.xml does not contain a rootfile');
        }

        $fullPath = trim($element->getAttribute('full-path'));
        if ($fullPath === '') {
            throw new \RuntimeException('EPUB rootfile is missing a full-path');
        }

        return $this->normalizeRelativePath($fullPath);
    }

    /**
     * @return array{
     *     version:string,
     *     uniqueIdentifierId:string,
     *     metadata:array<string, mixed>,
     *     metadataProperties:list<array{property:string, value:string, refines:string}>,
     *     metadataLinks:list<array{id:string, rel:list<string>, href:string, path:string, fragment:string, mediaType:string, properties:list<string>, refines:string, external:bool}>,
     *     manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>,
     *     spine:list<array{idref:string, href:string, path:string, mediaType:string, linear:bool, properties:list<string>}>,
     *     guide:array<string, mixed>
     * }
     */
    private function readPackageDocument(string $root, string $opfPath, string $rootfile): array
    {
        $document = $this->loadXmlFile($opfPath);
        $xpath = new \DOMXPath($document);
        $package = $xpath->query('/*[local-name()="package"][1]');
        $packageElement = $package instanceof \DOMNodeList ? $package->item(0) : null;
        if (!$packageElement instanceof \DOMElement) {
            throw new \RuntimeException('OPF package document is missing package element');
        }

        $opfDir = $this->relativeDirname($rootfile);
        $metadata = [
            'title' => '',
            'creators' => [],
            'language' => '',
            'identifier' => '',
            'date' => '',
            'publisher' => '',
        ];
        $metadataProperties = [];
        $metadataLinks = [];
        $metadataNodes = $xpath->query('./*[local-name()="metadata"]/*', $packageElement);
        if ($metadataNodes instanceof \DOMNodeList) {
            foreach ($metadataNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $name = $node->localName;
                if ($name === 'link') {
                    $href = trim($node->getAttribute('href'));
                    [$path, $fragment] = $this->splitResolvedHref($opfDir, $href);
                    $metadataLinks[] = [
                        'id' => trim($node->getAttribute('id')),
                        'rel' => $this->tokens($node->getAttribute('rel')),
                        'href' => $href,
                        'path' => $path,
                        'fragment' => $fragment,
                        'mediaType' => trim($node->getAttribute('media-type')),
                        'properties' => $this->tokens($node->getAttribute('properties')),
                        'refines' => trim($node->getAttribute('refines')),
                        'external' => $href !== '' && preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1,
                    ];
                    continue;
                }

                $value = $this->normalizedText($node->textContent);
                if ($value === '') {
                    continue;
                }
                if ($name === 'title' && $metadata['title'] === '') {
                    $metadata['title'] = $value;
                } elseif ($name === 'creator') {
                    $metadata['creators'][] = $value;
                } elseif ($name === 'language' && $metadata['language'] === '') {
                    $metadata['language'] = $value;
                } elseif ($name === 'identifier' && $metadata['identifier'] === '') {
                    $metadata['identifier'] = $value;
                } elseif ($name === 'date' && $metadata['date'] === '') {
                    $metadata['date'] = $value;
                } elseif ($name === 'publisher' && $metadata['publisher'] === '') {
                    $metadata['publisher'] = $value;
                } elseif ($name === 'meta') {
                    $metadataProperties[] = [
                        'property' => trim($node->getAttribute('property')),
                        'value' => $value,
                        'refines' => trim($node->getAttribute('refines')),
                    ];
                }
            }
        }

        $manifest = [];
        $manifestNodes = $xpath->query('./*[local-name()="manifest"]/*[local-name()="item"]', $packageElement);
        if ($manifestNodes instanceof \DOMNodeList) {
            foreach ($manifestNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $id = trim($node->getAttribute('id'));
                $href = trim($node->getAttribute('href'));
                if ($id === '' || $href === '') {
                    continue;
                }
                $external = $this->isExternalHref($href);
                $path = $this->resolvePackageHref($opfDir, $href);
                $suffix = $this->hrefSuffix($href);
                $exists = !$external && $path !== '' && $this->packagePathExists($root, $path);
                $diagnostics = [];
                if ($external) {
                    $diagnostics[] = [
                        'type' => 'external-manifest-href-target',
                        'href' => $href,
                        'target' => $href,
                    ];
                } elseif ($path !== '' && !$exists) {
                    $diagnostics[] = [
                        'type' => 'missing-manifest-href-target',
                        'href' => $href,
                        'path' => $path,
                    ];
                }
                if ($suffix['hasQuery']) {
                    $diagnostics[] = [
                        'type' => 'manifest-href-query-component',
                        'href' => $href,
                        'query' => $suffix['query'],
                    ];
                }
                if ($suffix['hasFragment']) {
                    $diagnostics[] = [
                        'type' => 'manifest-href-fragment-component',
                        'href' => $href,
                        'fragment' => $suffix['fragment'],
                    ];
                }
                $manifest[$id] = [
                    'id' => $id,
                    'href' => $href,
                    'target' => $external ? $href : $this->targetWithSuffix($path, $suffix),
                    'path' => $path,
                    'external' => $external,
                    'exists' => $exists,
                    'hrefHasQuery' => $suffix['hasQuery'],
                    'hrefQuery' => $suffix['query'],
                    'hrefHasFragment' => $suffix['hasFragment'],
                    'hrefFragment' => $suffix['fragment'],
                    'mediaType' => trim($node->getAttribute('media-type')),
                    'properties' => $this->tokens($node->getAttribute('properties')),
                    'diagnostics' => $diagnostics,
                ];
            }
        }
        $manifestReport = $this->manifestReport($manifest);

        $spine = [];
        $spineNodes = $xpath->query('./*[local-name()="spine"]/*[local-name()="itemref"]', $packageElement);
        if ($spineNodes instanceof \DOMNodeList) {
            foreach ($spineNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $idref = trim($node->getAttribute('idref'));
                $item = $manifest[$idref] ?? null;
                $linear = strtolower(trim($node->getAttribute('linear'))) !== 'no';
                $mediaType = is_array($item) ? $item['mediaType'] : '';
                $external = is_array($item) && ($item['external'] ?? false) === true;
                $exists = is_array($item) && ($item['exists'] ?? false) === true;
                $readable = $linear && $mediaType === 'application/xhtml+xml' && !$external && $exists;
                $diagnostics = [];
                if (!is_array($item)) {
                    $diagnostics[] = [
                        'type' => 'missing-spine-manifest-item',
                        'idref' => $idref,
                    ];
                } elseif ($external) {
                    $diagnostics[] = [
                        'type' => 'external-spine-item',
                        'idref' => $idref,
                        'target' => $item['target'],
                    ];
                } elseif (!$exists && ($item['path'] ?? '') !== '') {
                    $diagnostics[] = [
                        'type' => 'missing-spine-item-package-part',
                        'idref' => $idref,
                        'path' => $item['path'],
                    ];
                }
                $spine[] = [
                    'idref' => $idref,
                    'href' => is_array($item) ? $item['href'] : '',
                    'target' => is_array($item) ? $item['target'] : '',
                    'path' => is_array($item) ? $item['path'] : '',
                    'mediaType' => $mediaType,
                    'linear' => $linear,
                    'properties' => $this->tokens($node->getAttribute('properties')),
                    'external' => $external,
                    'exists' => $exists,
                    'readable' => $readable,
                    'diagnostics' => $diagnostics,
                ];
            }
        }
        $spineReport = $this->spineReport($spine);

        $guide = $this->readGuideReferences($root, $opfDir, $manifest, $packageElement);

        return [
            'version' => trim($packageElement->getAttribute('version')),
            'uniqueIdentifierId' => trim($packageElement->getAttribute('unique-identifier')),
            'metadata' => $metadata,
            'metadataProperties' => $metadataProperties,
            'metadataLinks' => $metadataLinks,
            'manifest' => $manifest,
            'manifestReport' => $manifestReport,
            'spine' => $spine,
            'spineReport' => $spineReport,
            'guide' => $guide,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function manifestReport(array $manifest): array
    {
        $externalItems = [];
        $missingItems = [];
        $hrefSuffixItems = [];
        $diagnostics = [];
        $index = 0;

        foreach ($manifest as $item) {
            if (($item['external'] ?? false) === true) {
                $externalItems[] = $item;
            }
            if (($item['external'] ?? false) !== true && ($item['exists'] ?? true) !== true) {
                $missingItems[] = $item;
            }
            if (($item['hrefHasQuery'] ?? false) === true || ($item['hrefHasFragment'] ?? false) === true) {
                $hrefSuffixItems[] = [
                    'id' => $item['id'],
                    'href' => $item['href'],
                    'target' => $item['target'],
                    'path' => $item['path'],
                    'query' => $item['hrefQuery'],
                    'fragment' => $item['hrefFragment'],
                ];
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = ['index' => $index, 'id' => $item['id']] + $diagnostic;
            }
            ++$index;
        }

        return [
            'itemCount' => count($manifest),
            'externalItemCount' => count($externalItems),
            'externalItems' => $externalItems,
            'missingItemCount' => count($missingItems),
            'missingItems' => $missingItems,
            'hrefSuffixCount' => count($hrefSuffixItems),
            'hrefSuffixItems' => $hrefSuffixItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @return array<string, mixed>
     */
    private function spineReport(array $spine): array
    {
        $linearItemCount = 0;
        $readableItemCount = 0;
        $externalItems = [];
        $missingPackagePartItems = [];
        $missingManifestItems = [];
        $diagnostics = [];

        foreach ($spine as $index => $item) {
            if (($item['linear'] ?? false) === true) {
                ++$linearItemCount;
            }
            if (($item['readable'] ?? false) === true) {
                ++$readableItemCount;
            }
            if (($item['external'] ?? false) === true) {
                $externalItems[] = $item;
            }
            if (($item['idref'] ?? '') !== '' && ($item['href'] ?? '') === '') {
                $missingManifestItems[] = $item;
            }
            if (($item['external'] ?? false) !== true && ($item['path'] ?? '') !== '' && ($item['exists'] ?? true) !== true) {
                $missingPackagePartItems[] = $item;
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
        }

        return [
            'itemCount' => count($spine),
            'linearItemCount' => $linearItemCount,
            'nonlinearItemCount' => count($spine) - $linearItemCount,
            'readableItemCount' => $readableItemCount,
            'skippedItemCount' => count($spine) - $readableItemCount,
            'externalItemCount' => count($externalItems),
            'externalItems' => $externalItems,
            'missingManifestItemCount' => count($missingManifestItems),
            'missingManifestItems' => $missingManifestItems,
            'missingPackagePartItemCount' => count($missingPackagePartItems),
            'missingPackagePartItems' => $missingPackagePartItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}> $manifest
     * @return array{
     *     present:bool,
     *     itemCount:int,
     *     typedItemCount:int,
     *     missingTypeCount:int,
     *     types:list<string>,
     *     typeCounts:array<string, int>,
     *     items:list<array<string, mixed>>,
     *     itemsByType:array<string, list<array<string, mixed>>>,
     *     diagnosticCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function readGuideReferences(string $root, string $opfDir, array $manifest, \DOMElement $packageElement): array
    {
        $guide = $this->firstDirectChild($packageElement, 'guide');
        if (!$guide instanceof \DOMElement) {
            return [
                'present' => false,
                'itemCount' => 0,
                'typedItemCount' => 0,
                'missingTypeCount' => 0,
                'types' => [],
                'typeCounts' => [],
                'items' => [],
                'itemsByType' => [],
                'diagnosticCount' => 0,
                'diagnostics' => [],
            ];
        }

        $manifestByPath = [];
        foreach ($manifest as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $item;
            }
        }

        $items = [];
        $itemsByType = [];
        $typeCounts = [];
        $typedItemCount = 0;
        $missingTypeCount = 0;
        $diagnostics = [];
        $index = 0;
        foreach ($guide->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'reference') {
                continue;
            }

            $href = trim($node->getAttribute('href'));
            [$path, $fragment] = $this->splitResolvedHref($opfDir, $href);
            $external = $href !== '' && preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1;
            $exists = !$external && $path !== '' && $this->packagePathExists($root, $path);
            $typeRaw = trim($node->getAttribute('type'));
            $types = $this->tokens($typeRaw);
            $itemDiagnostics = [];

            if ($types === []) {
                ++$missingTypeCount;
                $diagnostic = [
                    'type' => 'missing-guide-reference-type',
                    'href' => $href,
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            } else {
                ++$typedItemCount;
            }

            if (!$external && $path !== '' && !$exists) {
                $diagnostic = [
                    'type' => 'missing-guide-reference',
                    'href' => $href,
                    'path' => $path,
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            $manifestItem = $manifestByPath[$path] ?? null;
            $item = [
                'index' => $index,
                'type' => $types[0] ?? '',
                'typeRaw' => $typeRaw,
                'types' => $types,
                'title' => trim($node->getAttribute('title')),
                'href' => $href,
                'path' => $path,
                'fragment' => $fragment,
                'external' => $external,
                'exists' => $exists,
                'manifestId' => is_array($manifestItem) ? $manifestItem['id'] : '',
                'mediaType' => is_array($manifestItem) ? $manifestItem['mediaType'] : '',
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($types as $type) {
                $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
                $itemsByType[$type][] = $item;
            }
            $items[] = $item;
            ++$index;
        }

        return [
            'present' => true,
            'itemCount' => count($items),
            'typedItemCount' => $typedItemCount,
            'missingTypeCount' => $missingTypeCount,
            'types' => array_keys($typeCounts),
            'typeCounts' => $typeCounts,
            'items' => $items,
            'itemsByType' => $itemsByType,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array{manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>} $package
     * @return list<array{label:string, href:string, path:string, fragment:string, type:string, labelProvenance:array<string, mixed>, children:list<array<string, mixed>>}>
     */
    private function readNavigationDocument(string $root, array $package): array
    {
        $navItem = null;
        foreach ($package['manifest'] as $item) {
            if (in_array('nav', $item['properties'], true)) {
                $navItem = $item;
                break;
            }
        }
        if (!is_array($navItem)) {
            return [];
        }

        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $navItem['path']));
        $navDir = $this->relativeDirname($navItem['path']);
        $entries = [];
        $sectionIndex = 0;
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }

            $type = $this->epubType($element);
            if ($type !== 'toc' && $type !== 'landmarks' && $type !== 'page-list') {
                continue;
            }

            $ol = $this->firstDirectChild($element, 'ol');
            if (!$ol instanceof \DOMElement) {
                continue;
            }

            foreach ($this->readNavList(
                $ol,
                $navDir,
                $type,
                $sectionIndex,
                $this->nullableAttribute($element, 'id'),
                $this->navSectionLabel($element),
                $root
            ) as $entry) {
                $entries[] = $entry;
            }
            ++$sectionIndex;
        }

        return $entries;
    }

    /**
     * @param array{manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>, spine:list<array{idref:string}>} $package
     * @return list<array{label:string, href:string, path:string, fragment:string, playOrder:int, labelProvenance:array<string, mixed>, children:list<array<string, mixed>>}>
     */
    private function readNcxDocument(string $root, array $package): array
    {
        $ncxItem = null;
        foreach ($package['manifest'] as $item) {
            if ($item['mediaType'] === 'application/x-dtbncx+xml') {
                $ncxItem = $item;
                break;
            }
        }
        if (!is_array($ncxItem)) {
            return [];
        }

        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $ncxItem['path']));
        $xpath = new \DOMXPath($document);
        $navMap = $xpath->query('/*[local-name()="ncx"]/*[local-name()="navMap"][1]');
        $navMapElement = $navMap instanceof \DOMNodeList ? $navMap->item(0) : null;
        if (!$navMapElement instanceof \DOMElement) {
            return [];
        }

        return $this->readNcxPoints($navMapElement, $this->relativeDirname($ncxItem['path']));
    }

    /**
     * @param list<array<string, mixed>> $points
     * @return array<string, mixed>
     */
    private function ncxReport(array $points): array
    {
        $flat = [];
        $this->flattenNcxPoints($points, $flat);
        $diagnostics = [];
        $seenTargets = [];
        $previousPositivePlayOrder = null;
        $playOrderCount = 0;
        $missingPlayOrderCount = 0;
        $nonIncreasingPlayOrderCount = 0;
        $duplicateTargetCount = 0;

        foreach ($flat as $index => $point) {
            $playOrder = (int) ($point['playOrder'] ?? 0);
            if ($playOrder <= 0) {
                ++$missingPlayOrderCount;
            } else {
                ++$playOrderCount;
                if ($previousPositivePlayOrder !== null && $playOrder <= $previousPositivePlayOrder) {
                    ++$nonIncreasingPlayOrderCount;
                    $diagnostics[] = [
                        'type' => 'non-increasing-ncx-play-order',
                        'index' => $index,
                        'label' => (string) ($point['label'] ?? ''),
                        'playOrder' => $playOrder,
                        'previousPlayOrder' => $previousPositivePlayOrder,
                        'path' => (string) ($point['path'] ?? ''),
                        'fragment' => (string) ($point['fragment'] ?? ''),
                    ];
                }
                $previousPositivePlayOrder = $playOrder;
            }

            $target = $this->ncxPointTarget($point);
            if ($target === '') {
                continue;
            }
            if (isset($seenTargets[$target])) {
                ++$duplicateTargetCount;
                $diagnostics[] = [
                    'type' => 'duplicate-ncx-target',
                    'index' => $index,
                    'firstIndex' => $seenTargets[$target]['index'],
                    'label' => (string) ($point['label'] ?? ''),
                    'firstLabel' => $seenTargets[$target]['label'],
                    'target' => $target,
                    'path' => (string) ($point['path'] ?? ''),
                    'fragment' => (string) ($point['fragment'] ?? ''),
                ];
                continue;
            }

            $seenTargets[$target] = [
                'index' => $index,
                'label' => (string) ($point['label'] ?? ''),
            ];
        }

        return [
            'present' => $flat !== [],
            'itemCount' => count($flat),
            'topLevelItemCount' => count($points),
            'playOrderCount' => $playOrderCount,
            'hierarchy' => $this->hierarchySummary($flat, count($points)),
            'items' => $flat,
            'pointCount' => count($flat),
            'topLevelPointCount' => count($points),
            'maxDepth' => $this->maxNcxDepth($points),
            'missingPlayOrderCount' => $missingPlayOrderCount,
            'nonIncreasingPlayOrderCount' => $nonIncreasingPlayOrderCount,
            'duplicateTargetCount' => $duplicateTargetCount,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function navReport(array $entries, array $package): array
    {
        $flat = $this->flattenNavigationEntries($entries);
        $pageListReport = $this->pageListReport($flat);
        $sections = [];
        $sectionKeys = [];
        $typeCounts = [];

        foreach ($flat as $item) {
            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = (string) $sectionIndex;
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== '') {
                $typeCounts[$sectionType] = ($typeCounts[$sectionType] ?? 0) + 1;
            }

            if (!isset($sectionKeys[$sectionKey])) {
                $sectionKeys[$sectionKey] = count($sections);
                $sections[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionLabel' => is_string($item['sectionLabel'] ?? null) ? $item['sectionLabel'] : '',
                    'type' => $sectionType,
                    'itemCount' => 0,
                ];
            }
            ++$sections[$sectionKeys[$sectionKey]]['itemCount'];
        }

        return [
            'present' => $flat !== [],
            'itemCount' => count($flat),
            'topLevelItemCount' => count($entries),
            'sectionCount' => count($sections),
            'types' => array_keys($typeCounts),
            'typeCounts' => $typeCounts,
            'sections' => $sections,
            'hierarchy' => $this->hierarchySummary($flat, count($entries)),
            'landmarks' => $this->landmarkReport($flat, $package),
            'pageListItemCount' => $pageListReport['itemCount'],
            'pageBreakItemCount' => $pageListReport['pageBreakItemCount'],
            'diagnosticCount' => $pageListReport['diagnosticCount'],
            'diagnostics' => $pageListReport['diagnostics'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function pageListReport(array $flat): array
    {
        $pageListItems = [];
        $pageBreakItemCount = 0;
        $diagnostics = [];
        $seenPageListHrefs = [];
        $seenPageListLabels = [];

        foreach ($flat as $item) {
            $type = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if (($item['pageBreakProvenance']['present'] ?? false) === true) {
                ++$pageBreakItemCount;
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'navType' => $type,
                ] + $diagnostic;
            }

            if ($type !== 'page-list') {
                continue;
            }

            $pageListItems[] = $item;
            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            if ($href !== '') {
                if (isset($seenPageListHrefs[$href])) {
                    $diagnostics[] = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'duplicate-page-list-href',
                        'navType' => $type,
                        'source' => 'href',
                        'href' => $href,
                        'target' => is_string($item['target'] ?? null) ? $item['target'] : '',
                        'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                        'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : '',
                        'firstIndex' => is_int($seenPageListHrefs[$href]['index'] ?? null) ? $seenPageListHrefs[$href]['index'] : 0,
                        'firstTarget' => is_string($seenPageListHrefs[$href]['target'] ?? null) ? $seenPageListHrefs[$href]['target'] : '',
                    ];
                } else {
                    $seenPageListHrefs[$href] = $item;
                }
            }

            $label = is_string($item['label'] ?? null) ? $item['label'] : '';
            if ($label !== '') {
                if (isset($seenPageListLabels[$label])) {
                    $diagnostics[] = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'duplicate-page-list-label',
                        'navType' => $type,
                        'source' => 'label',
                        'label' => $label,
                        'href' => $href,
                        'target' => is_string($item['target'] ?? null) ? $item['target'] : '',
                        'firstIndex' => is_int($seenPageListLabels[$label]['index'] ?? null) ? $seenPageListLabels[$label]['index'] : 0,
                        'firstHref' => is_string($seenPageListLabels[$label]['href'] ?? null) ? $seenPageListLabels[$label]['href'] : '',
                    ];
                } else {
                    $seenPageListLabels[$label] = $item;
                }
            }
        }

        return [
            'itemCount' => count($pageListItems),
            'pageBreakItemCount' => $pageBreakItemCount,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function landmarkReport(array $flat, array $package): array
    {
        $manifestByPath = [];
        foreach (is_array($package['manifest'] ?? null) ? $package['manifest'] : [] as $manifestItem) {
            if (!is_array($manifestItem)) {
                continue;
            }
            $path = is_string($manifestItem['path'] ?? null) ? $manifestItem['path'] : '';
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $manifestItem;
            }
        }

        $spineByPath = [];
        foreach (is_array($package['spine'] ?? null) ? $package['spine'] : [] as $index => $spineItem) {
            if (!is_array($spineItem)) {
                continue;
            }
            $path = is_string($spineItem['path'] ?? null) ? $spineItem['path'] : '';
            if ($path !== '' && !isset($spineByPath[$path])) {
                $spineByPath[$path] = ['index' => $index] + $spineItem;
            }
        }

        $guideByPath = [];
        $guide = is_array($package['guide'] ?? null) ? $package['guide'] : [];
        foreach (is_array($guide['items'] ?? null) ? $guide['items'] : [] as $guideItem) {
            if (!is_array($guideItem)) {
                continue;
            }
            $path = is_string($guideItem['path'] ?? null) ? $guideItem['path'] : '';
            if ($path !== '') {
                $guideByPath[$path][] = $guideItem;
            }
        }

        $items = [];
        $diagnostics = [];
        $typedItemCount = 0;
        $missingTypeCount = 0;
        $targetedItemCount = 0;
        $externalTargetCount = 0;
        $missingReferenceCount = 0;
        $outsideSpineTargetCount = 0;
        $manifestMappedCount = 0;
        $spineMappedCount = 0;
        $guideRelationCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'landmarks') {
                continue;
            }

            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $external = (bool) ($item['external'] ?? false);
            $exists = (bool) ($item['exists'] ?? false);
            $semanticTypes = is_array($item['semanticTypes'] ?? null) ? array_values($item['semanticTypes']) : [];
            $manifestItem = $manifestByPath[$path] ?? null;
            $spineItem = $spineByPath[$path] ?? null;
            $guideItems = $guideByPath[$path] ?? [];
            $itemDiagnostics = [];
            $landmarkIndex = count($items);

            if ($semanticTypes === []) {
                ++$missingTypeCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-landmark-nav-type',
                    'message' => 'EPUB nav landmark item is missing an epub:type value for import classification',
                ];
            } else {
                ++$typedItemCount;
            }

            if ($href === '') {
                $itemDiagnostics[] = [
                    'type' => 'missing-landmark-target',
                    'message' => 'EPUB nav landmark item has no href target',
                ];
            } else {
                ++$targetedItemCount;
                if ($external) {
                    ++$externalTargetCount;
                    $itemDiagnostics[] = [
                        'type' => 'external-landmark-target',
                        'target' => $target,
                        'message' => 'EPUB nav landmark target points outside the package and was not fetched',
                    ];
                } elseif (!$exists) {
                    ++$missingReferenceCount;
                    $itemDiagnostics[] = [
                        'type' => 'missing-landmark-reference',
                        'path' => $path,
                        'message' => 'EPUB nav landmark target is missing from the package',
                    ];
                } elseif (!is_array($spineItem)) {
                    ++$outsideSpineTargetCount;
                    $itemDiagnostics[] = [
                        'type' => 'landmark-target-outside-spine',
                        'path' => $path,
                        'message' => 'EPUB nav landmark target exists but is not part of the resolved spine handoff',
                    ];
                }
            }

            if (is_array($manifestItem)) {
                ++$manifestMappedCount;
            }
            if (is_array($spineItem)) {
                ++$spineMappedCount;
            }
            if ($guideItems !== []) {
                ++$guideRelationCount;
            }

            $reportedItem = [
                'index' => $landmarkIndex,
                'sourceIndex' => $sourceIndex,
                'sectionIndex' => is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : null,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'labelProvenance' => is_array($item['labelProvenance'] ?? null) ? $item['labelProvenance'] : [],
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : '',
                'external' => $external,
                'exists' => $exists,
                'semanticType' => is_string($item['semanticType'] ?? null) ? $item['semanticType'] : null,
                'semanticTypes' => $semanticTypes,
                'itemTypes' => is_array($item['itemTypes'] ?? null) ? array_values($item['itemTypes']) : [],
                'labelTypes' => is_array($item['labelTypes'] ?? null) ? array_values($item['labelTypes']) : [],
                'typeSource' => is_string($item['typeSource'] ?? null) ? $item['typeSource'] : null,
                'typeSources' => is_array($item['typeSources'] ?? null) ? array_values($item['typeSources']) : [],
                'manifestId' => is_array($manifestItem) && is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                'mediaType' => is_array($manifestItem) && is_string($manifestItem['mediaType'] ?? null) ? $manifestItem['mediaType'] : null,
                'spineIndex' => is_array($spineItem) && is_int($spineItem['index'] ?? null) ? $spineItem['index'] : null,
                'spineIdref' => is_array($spineItem) && is_string($spineItem['idref'] ?? null) ? $spineItem['idref'] : null,
                'guideTypes' => array_values(array_filter(
                    array_map(static fn (array $guideItem): string => is_string($guideItem['type'] ?? null) ? $guideItem['type'] : '', $guideItems),
                    static fn (string $type): bool => $type !== ''
                )),
                'guideTitles' => array_values(array_filter(
                    array_map(static fn (array $guideItem): string => is_string($guideItem['title'] ?? null) ? $guideItem['title'] : '', $guideItems),
                    static fn (string $title): bool => $title !== ''
                )),
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $landmarkIndex,
                    'sourceIndex' => $sourceIndex,
                    'sectionIndex' => $reportedItem['sectionIndex'],
                    'sectionId' => $reportedItem['sectionId'],
                    'label' => $reportedItem['label'],
                    'href' => $href,
                    'target' => $target,
                ] + $diagnostic;
            }

            $items[] = $reportedItem;
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'typedItemCount' => $typedItemCount,
            'missingTypeCount' => $missingTypeCount,
            'targetedItemCount' => $targetedItemCount,
            'externalTargetCount' => $externalTargetCount,
            'missingReferenceCount' => $missingReferenceCount,
            'outsideSpineTargetCount' => $outsideSpineTargetCount,
            'manifestMappedCount' => $manifestMappedCount,
            'spineMappedCount' => $spineMappedCount,
            'guideRelationCount' => $guideRelationCount,
            'diagnosticCount' => count($diagnostics),
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function flattenNavigationEntries(array $entries): array
    {
        $flat = [];
        $this->appendFlatNavigationEntries($entries, $flat);

        return $flat;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $flat
     */
    private function appendFlatNavigationEntries(array $entries, array &$flat): void
    {
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $copy = $entry;
            $children = is_array($copy['children'] ?? null) ? array_values($copy['children']) : [];
            unset($copy['children']);
            $copy['index'] = count($flat);
            $copy['childCount'] = count($children);
            $flat[] = $copy;
            if ($children !== []) {
                $this->appendFlatNavigationEntries($children, $flat);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array{topLevelItemCount:int, branchItemCount:int, leafItemCount:int, maxDepth:int}
     */
    private function hierarchySummary(array $flat, int $topLevelItemCount): array
    {
        $branchItemCount = 0;
        $maxDepth = 0;

        foreach ($flat as $item) {
            $childCount = is_int($item['childCount'] ?? null) ? $item['childCount'] : 0;
            if ($childCount > 0) {
                ++$branchItemCount;
            }
            if (is_int($item['depth'] ?? null)) {
                $maxDepth = max($maxDepth, $item['depth']);
            }
        }

        return [
            'topLevelItemCount' => $topLevelItemCount,
            'branchItemCount' => $branchItemCount,
            'leafItemCount' => count($flat) - $branchItemCount,
            'maxDepth' => $maxDepth,
        ];
    }

    private function navSectionLabel(\DOMElement $nav): string
    {
        foreach ($nav->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if (in_array($child->localName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                return $this->normalizedText($child->textContent);
            }
        }

        return '';
    }

    /**
     * @return list<AstNode>
     */
    private function readXhtmlDocument(string $root, string $path): array
    {
        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $path));
        $body = $this->firstElementByLocalName($document, 'body');
        if (!$body instanceof \DOMElement) {
            return [];
        }

        return $this->blockNodesFromChildren($body, $this->relativeDirname($path));
    }

    /**
     * @return list<AstNode>
     */
    private function blockNodesFromChildren(\DOMNode $parent, string $baseDir): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = $this->normalizedText($child->wholeText);
                if ($text !== '') {
                    $blocks[] = new AstNode('paragraph', ['text' => $text], [new AstNode('text', ['text' => $text])]);
                }
                continue;
            }
            if ($child instanceof \DOMElement) {
                array_push($blocks, ...$this->blockNodesFromElement($child, $baseDir));
            }
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function blockNodesFromElement(\DOMElement $element, string $baseDir): array
    {
        $name = $element->localName;
        if (preg_match('/^h([1-6])$/', $name, $matches) === 1) {
            $children = $this->inlineNodesFromChildren($element, $baseDir);
            return [new AstNode('heading', [
                'level' => (int) $matches[1],
                'text' => $this->plainInlineText($children),
                'id' => trim($element->getAttribute('id')),
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $children)];
        }

        if ($name === 'p') {
            $children = $this->inlineNodesFromChildren($element, $baseDir);
            return [new AstNode('paragraph', [
                'text' => $this->plainInlineText($children),
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $children)];
        }

        if ($name === 'ul' || $name === 'ol') {
            return [$this->listNode($element, $baseDir, $name === 'ol')];
        }

        if ($name === 'dl') {
            return [$this->definitionListNode($element, $baseDir)];
        }

        if ($name === 'blockquote') {
            return [new AstNode('blockquote', [], $this->blockNodesFromChildren($element, $baseDir))];
        }

        if ($name === 'pre') {
            $code = $this->firstDescendantByLocalName($element, 'code');
            return [new AstNode('code_block', [
                'text' => $code instanceof \DOMElement ? $code->textContent : $element->textContent,
                'classes' => $code instanceof \DOMElement ? $this->classList($code) : $this->classList($element),
            ])];
        }

        if ($name === 'figure') {
            $image = $this->firstDescendantByLocalName($element, 'img');
            if ($image instanceof \DOMElement) {
                $caption = $this->firstDescendantByLocalName($element, 'figcaption');
                $imageNode = $this->imageNode($image, $baseDir);
                return [new AstNode('figure', [
                    'caption' => $caption instanceof \DOMElement ? $this->normalizedText($caption->textContent) : (string) $imageNode->attr('alt', ''),
                    'htmlAttributes' => $this->htmlAttributes($element),
                ], [$imageNode])];
            }
        }

        if ($name === 'section' || $name === 'article' || $name === 'main' || $name === 'body') {
            return $this->blockNodesFromChildren($element, $baseDir);
        }

        if ($name === 'img') {
            $image = $this->imageNode($element, $baseDir);
            return [new AstNode('paragraph', ['text' => (string) $image->attr('alt', '')], [$image])];
        }

        $children = $this->inlineNodesFromChildren($element, $baseDir);
        if ($children !== []) {
            return [new AstNode('paragraph', ['text' => $this->plainInlineText($children)], $children)];
        }

        return $this->blockNodesFromChildren($element, $baseDir);
    }

    private function listNode(\DOMElement $element, string $baseDir, bool $ordered): AstNode
    {
        $items = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'li') {
                continue;
            }

            $blocks = $this->blockNodesFromChildren($child, $baseDir);
            if ($blocks === []) {
                $children = $this->inlineNodesFromChildren($child, $baseDir);
                $blocks = [new AstNode('paragraph', ['text' => $this->plainInlineText($children)], $children)];
            }
            $items[] = new AstNode('list_item', [], $blocks);
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', [
            'start' => $ordered && is_numeric($element->getAttribute('start')) ? (int) $element->getAttribute('start') : 1,
            'style' => $ordered ? $this->orderedListStyle($element) : 'default',
        ], $items);
    }

    private function definitionListNode(\DOMElement $element, string $baseDir): AstNode
    {
        $items = [];
        $termInlines = [];
        $termTexts = [];
        $definitions = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'dt') {
                if ($termInlines !== [] && $definitions !== []) {
                    $this->flushDefinitionListItem($items, $termInlines, $termTexts, $definitions);
                } elseif ($termInlines !== []) {
                    $termInlines[] = new AstNode('linebreak');
                }

                $inlines = $this->inlineNodesFromChildren($child, $baseDir);
                array_push($termInlines, ...$inlines);
                $termTexts[] = $this->plainInlineText($inlines);
                continue;
            }

            if ($child->localName === 'dd' && $termInlines !== []) {
                $blocks = $this->definitionBlocksFromElement($child, $baseDir);
                if ($blocks === []) {
                    $inlines = $this->inlineNodesFromChildren($child, $baseDir);
                    $blocks = [new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines)];
                }

                $definitions[] = new AstNode('definition', [
                    'loose' => count($blocks) > 1,
                ], $blocks);
            }
        }

        $this->flushDefinitionListItem($items, $termInlines, $termTexts, $definitions);
        $listText = $this->normalizedText(implode(' ', array_map(
            static fn (AstNode $item): string => (string) $item->attr('term', ''),
            $items
        )));

        return new AstNode('definition_list', [
            'text' => $listText,
            'htmlAttributes' => $this->htmlAttributes($element),
        ], $items);
    }

    /**
     * @return list<AstNode>
     */
    private function definitionBlocksFromElement(\DOMElement $element, string $baseDir): array
    {
        $blocks = [];
        $inlines = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isBlockContentElement($child)) {
                $this->flushInlineParagraph($inlines, $blocks);
                array_push($blocks, ...$this->blockNodesFromElement($child, $baseDir));
                continue;
            }

            array_push($inlines, ...$this->inlineNodesFromNode($child, $baseDir));
        }

        $this->flushInlineParagraph($inlines, $blocks);

        return $blocks;
    }

    private function isBlockContentElement(\DOMElement $element): bool
    {
        $name = $element->localName;

        return $name === 'p'
            || $name === 'ul'
            || $name === 'ol'
            || $name === 'dl'
            || $name === 'blockquote'
            || $name === 'pre'
            || $name === 'figure'
            || $name === 'section'
            || $name === 'article'
            || $name === 'main'
            || $name === 'div'
            || preg_match('/^h[1-6]$/', $name) === 1;
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $blocks
     */
    private function flushInlineParagraph(array &$inlines, array &$blocks): void
    {
        if ($inlines === []) {
            return;
        }

        $blocks[] = new AstNode('paragraph', [
            'text' => $this->plainInlineText($inlines),
        ], $inlines);
        $inlines = [];
    }

    /**
     * @param list<AstNode> $items
     * @param list<AstNode> $termInlines
     * @param list<string> $termTexts
     * @param list<AstNode> $definitions
     */
    private function flushDefinitionListItem(
        array &$items,
        array &$termInlines,
        array &$termTexts,
        array &$definitions
    ): void {
        if ($termInlines === []) {
            $termTexts = [];
            $definitions = [];
            return;
        }

        $termText = $this->normalizedText(implode("\n", $termTexts));
        $items[] = new AstNode('definition_item', [
            'term' => $termText,
        ], [
            new AstNode('term', ['text' => $termText], $termInlines),
            ...$definitions,
        ]);

        $termInlines = [];
        $termTexts = [];
        $definitions = [];
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodesFromChildren(\DOMNode $parent, string $baseDir): array
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            array_push($nodes, ...$this->inlineNodesFromNode($child, $baseDir));
        }

        return $this->normalizeInlineTextNodes($nodes);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodesFromNode(\DOMNode $node, string $baseDir): array
    {
        if ($node instanceof \DOMText) {
            $text = preg_replace('/\s+/u', ' ', $node->wholeText) ?? $node->wholeText;
            return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
        }
        if (!$node instanceof \DOMElement) {
            return [];
        }

        $name = $node->localName;
        $children = $this->inlineNodesFromChildren($node, $baseDir);

        return match ($name) {
            'em', 'i' => [new AstNode('emph', [], $children)],
            'strong', 'b' => [new AstNode('strong', [], $children)],
            'code' => [new AstNode('code', ['text' => $node->textContent, 'classes' => $this->classList($node)])],
            'br' => [new AstNode('linebreak')],
            'a' => [new AstNode('link', [
                'url' => $this->resolveContentHref($baseDir, $node->getAttribute('href')),
                'title' => trim($node->getAttribute('title')),
                'htmlAttributes' => $this->htmlAttributes($node),
            ], $children === [] ? [new AstNode('text', ['text' => $this->normalizedText($node->textContent)])] : $children)],
            'img' => [$this->imageNode($node, $baseDir)],
            'sup' => [new AstNode('superscript', [], $children)],
            'sub' => [new AstNode('subscript', [], $children)],
            'span' => [new AstNode('span', ['htmlAttributes' => $this->htmlAttributes($node)], $children)],
            default => $children,
        };
    }

    private function imageNode(\DOMElement $element, string $baseDir): AstNode
    {
        $alt = trim($element->getAttribute('alt'));

        return new AstNode('image', [
            'url' => $this->resolveContentHref($baseDir, $element->getAttribute('src')),
            'alt' => $alt,
            'title' => trim($element->getAttribute('title')),
            'htmlAttributes' => $this->htmlAttributes($element),
        ], $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function normalizeInlineTextNodes(array $nodes): array
    {
        $normalized = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $normalized !== [] && end($normalized)->type === 'text') {
                $previous = array_pop($normalized);
                $normalized[] = new AstNode('text', [
                    'text' => (string) $previous->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }

            $normalized[] = $node;
        }

        if ($normalized !== [] && $normalized[0]->type === 'text') {
            $text = ltrim((string) $normalized[0]->attr('text', ''));
            if ($text === '') {
                array_shift($normalized);
            } else {
                $normalized[0] = new AstNode('text', ['text' => $text]);
            }
        }

        $last = count($normalized) - 1;
        if ($last >= 0 && $normalized[$last]->type === 'text') {
            $text = rtrim((string) $normalized[$last]->attr('text', ''));
            if ($text === '') {
                array_pop($normalized);
            } else {
                $normalized[$last] = new AstNode('text', ['text' => $text]);
            }
        }

        return $normalized;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'linebreak', 'softbreak' => "\n",
                'image' => (string) $node->attr('alt', ''),
                default => $this->plainInlineText($node->children),
            };
        }

        return $this->normalizedText($text);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNavList(
        \DOMElement $ol,
        string $baseDir,
        string $type,
        int $sectionIndex,
        ?string $sectionId,
        string $sectionLabel,
        string $root,
        int $depth = 0
    ): array
    {
        $entries = [];
        foreach ($ol->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'li') {
                continue;
            }

            $link = $this->firstDirectChild($node, 'a') ?? $this->firstDirectChild($node, 'span');
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $href = $link->localName === 'a' ? trim($link->getAttribute('href')) : '';
            $external = $this->isExternalHref($href);
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);
            $target = $external ? $href : $this->targetWithSuffix($path, $this->hrefSuffix($href));
            $exists = !$external && $path !== '' && $this->packagePathExists($root, $path);
            $typeReport = $this->navItemTypeReport($node, $link);
            $label = $this->normalizedText($link->textContent);
            $linkTypes = $this->epubTypes($link);
            $hasPageBreakType = in_array('pagebreak', $linkTypes, true);
            $itemDiagnostics = [];
            if ($type === 'page-list') {
                if ($href === '') {
                    $itemDiagnostics[] = [
                        'type' => 'missing-page-list-href',
                        'source' => $link->localName . '@href',
                        'linkElement' => $link->localName,
                        'label' => $label,
                        'pageBreak' => $hasPageBreakType,
                    ];
                }
                if ($label === '') {
                    $itemDiagnostics[] = [
                        'type' => 'missing-page-list-label',
                        'source' => 'textContent',
                        'linkElement' => $link->localName,
                        'href' => $href,
                        'path' => $path,
                        'fragment' => $fragment,
                        'pageBreak' => $hasPageBreakType,
                    ];
                }
                if (!$hasPageBreakType) {
                    $itemDiagnostics[] = [
                        'type' => 'missing-pagebreak-type',
                        'source' => 'epub:type',
                        'linkElement' => $link->localName,
                        'href' => $href,
                        'label' => $label,
                    ];
                }
            }
            $children = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'ol') {
                    $children = $this->readNavList(
                        $child,
                        $baseDir,
                        $type,
                        $sectionIndex,
                        $sectionId,
                        $sectionLabel,
                        $root,
                        $depth + 1
                    );
                    break;
                }
            }

            $labelProvenance = $this->labelProvenance(
                $link,
                match ($type) {
                    'landmarks' => $link->localName === 'a' ? 'anchor' : 'span',
                    'page-list' => $label === '' ? 'missing' : 'textContent',
                    default => 'xhtml-nav',
                },
                $baseDir
            );
            $labelProvenance['itemId'] = $this->nullableAttribute($node, 'id');
            $labelProvenance['labelId'] = $this->nullableAttribute($link, 'id');
            $labelProvenance['linkElement'] = $link->localName;

            $entries[] = [
                'label' => $label,
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'type' => $type,
                'sectionType' => $type,
                'sectionIndex' => $sectionIndex,
                'sectionId' => $sectionId,
                'sectionLabel' => $sectionLabel,
                'id' => $this->nullableAttribute($link, 'id') ?? $this->nullableAttribute($node, 'id'),
                'itemId' => $this->nullableAttribute($node, 'id'),
                'labelId' => $this->nullableAttribute($link, 'id'),
                'labelElement' => $link->localName,
                'linkElement' => $link->localName,
                'epubTypes' => $linkTypes,
                'labelProvenance' => $labelProvenance,
                'pageBreakProvenance' => [
                    'present' => $hasPageBreakType,
                    'source' => $hasPageBreakType ? 'epub:type' : ($type === 'page-list' ? 'missing' : ''),
                    'linkElement' => $link->localName,
                    'epubTypes' => $linkTypes,
                ],
                'external' => $external,
                'exists' => $exists,
                'semanticType' => $typeReport['type'],
                'semanticTypes' => $typeReport['types'],
                'itemTypes' => $typeReport['itemTypes'],
                'labelTypes' => $typeReport['labelTypes'],
                'typeSource' => $typeReport['typeSource'],
                'typeSources' => $typeReport['typeSources'],
                'depth' => $depth,
                'childCount' => count($children),
                'diagnostics' => $itemDiagnostics,
                'children' => $children,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNcxPoints(\DOMElement $parent, string $baseDir, int $depth = 0): array
    {
        $points = [];
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'navPoint') {
                continue;
            }

            $labelContainer = $this->firstDirectChild($node, 'navLabel');
            $label = $labelContainer instanceof \DOMElement
                ? $this->firstChildPathText($node, ['navLabel', 'text'])
                : '';
            $content = $this->firstDirectChild($node, 'content');
            $href = $content instanceof \DOMElement ? trim($content->getAttribute('src')) : '';
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);
            $children = $this->readNcxPoints($node, $baseDir, $depth + 1);
            $labelProvenance = $this->ncxLabelProvenance($labelContainer);
            $labelProvenance['itemId'] = $this->nullableAttribute($node, 'id');
            $points[] = [
                'id' => $this->nullableAttribute($node, 'id'),
                'label' => $label,
                'href' => $href,
                'target' => $this->targetWithSuffix($path, $this->hrefSuffix($href)),
                'path' => $path,
                'fragment' => $fragment,
                'playOrder' => is_numeric($node->getAttribute('playOrder')) ? (int) $node->getAttribute('playOrder') : 0,
                'labelProvenance' => $labelProvenance,
                'depth' => $depth,
                'childCount' => count($children),
                'children' => $children,
            ];
        }

        return $points;
    }

    /**
     * @param list<array<string, mixed>> $points
     * @param list<array<string, mixed>> $flat
     */
    private function flattenNcxPoints(array $points, array &$flat, int $depth = 0): void
    {
        foreach ($points as $point) {
            $entry = $point;
            $entry['depth'] = $depth;
            unset($entry['children']);
            $flat[] = $entry;
            $children = is_array($point['children'] ?? null) ? $point['children'] : [];
            $this->flattenNcxPoints($children, $flat, $depth + 1);
        }
    }

    /**
     * @param list<array<string, mixed>> $points
     */
    private function maxNcxDepth(array $points, int $depth = 1): int
    {
        $maxDepth = $points === [] ? 0 : $depth;
        foreach ($points as $point) {
            $children = is_array($point['children'] ?? null) ? $point['children'] : [];
            $maxDepth = max($maxDepth, $this->maxNcxDepth($children, $depth + 1));
        }

        return $maxDepth;
    }

    /**
     * @param array<string, mixed> $point
     */
    private function ncxPointTarget(array $point): string
    {
        $path = (string) ($point['path'] ?? '');
        if ($path === '') {
            return '';
        }

        $fragment = (string) ($point['fragment'] ?? '');

        return $path . ($fragment === '' ? '' : '#' . $fragment);
    }

    /**
     * @return array<string, mixed>
     */
    private function labelProvenance(\DOMElement $element, string $source, string $baseDir): array
    {
        $imageLabels = [];
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if (!$descendant instanceof \DOMElement || $descendant->localName !== 'img') {
                continue;
            }

            $src = trim($descendant->getAttribute('src'));
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $src);
            $imageLabels[] = [
                'src' => $src,
                'path' => $path,
                'fragment' => $fragment,
                'alt' => trim($descendant->getAttribute('alt')),
                'title' => trim($descendant->getAttribute('title')),
                'attributes' => $this->htmlAttributes($descendant),
            ];
        }

        return [
            'source' => $source,
            'element' => $element->localName,
            'text' => $this->normalizedText($element->textContent),
            'language' => $this->elementLanguage($element),
            'direction' => trim($element->getAttribute('dir')),
            'attributes' => $this->htmlAttributes($element),
            'epubTypes' => $this->epubTypes($element),
            'imageLabels' => $imageLabels,
            'imageLabelCount' => count($imageLabels),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxLabelProvenance(?\DOMElement $labelContainer): array
    {
        $textElement = $labelContainer instanceof \DOMElement
            ? $this->firstDescendantByLocalName($labelContainer, 'text')
            : null;

        return [
            'source' => 'ncx-navLabel',
            'element' => $labelContainer instanceof \DOMElement ? $labelContainer->localName : null,
            'text' => $textElement instanceof \DOMElement ? $this->normalizedText($textElement->textContent) : '',
            'labelId' => $labelContainer instanceof \DOMElement ? $this->nullableAttribute($labelContainer, 'id') : null,
            'textId' => $textElement instanceof \DOMElement ? $this->nullableAttribute($textElement, 'id') : null,
            'language' => $labelContainer instanceof \DOMElement ? $this->elementLanguage($labelContainer) : '',
            'direction' => $labelContainer instanceof \DOMElement ? trim($labelContainer->getAttribute('dir')) : '',
            'attributes' => $labelContainer instanceof \DOMElement ? $this->htmlAttributes($labelContainer) : [],
            'textAttributes' => $textElement instanceof \DOMElement ? $this->htmlAttributes($textElement) : [],
            'epubTypes' => [],
            'imageLabels' => [],
            'imageLabelCount' => 0,
        ];
    }

    /**
     * @param list<string> $path
     */
    private function firstChildPathText(\DOMElement $element, array $path): string
    {
        $current = $element;
        foreach ($path as $name) {
            $next = $this->firstDirectChild($current, $name);
            if (!$next instanceof \DOMElement) {
                return '';
            }
            $current = $next;
        }

        return $this->normalizedText($current->textContent);
    }

    private function firstDirectChild(\DOMNode $node, string $localName): ?\DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function firstDescendantByLocalName(\DOMElement $element, string $name): ?\DOMElement
    {
        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                return $child;
            }
        }

        return null;
    }

    private function firstElementByLocalName(\DOMDocument $document, string $name): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === $name) {
                return $element;
            }
        }

        return null;
    }

    private function nullableAttribute(\DOMElement $element, string $name): ?string
    {
        $value = trim($element->getAttribute($name));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function htmlAttributes(\DOMElement $element): array
    {
        $attrs = [];
        foreach ($element->attributes ?? [] as $attr) {
            if (!$attr instanceof \DOMAttr) {
                continue;
            }
            $name = $attr->name;
            if (str_starts_with($name, 'xmlns')) {
                continue;
            }
            $attrs[$name] = $attr->value;
        }

        return $attrs;
    }

    /**
     * @return list<string>
     */
    private function classList(\DOMElement $element): array
    {
        return $this->tokens($element->getAttribute('class'));
    }

    private function orderedListStyle(\DOMElement $element): string
    {
        return match ($element->getAttribute('type')) {
            'a' => 'lower_alpha',
            'A' => 'upper_alpha',
            'i' => 'lower_roman',
            'I' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function epubType(\DOMElement $element): string
    {
        foreach ($this->epubTypes($element) as $token) {
            if ($token === 'toc' || $token === 'landmarks' || $token === 'page-list') {
                return $token;
            }
        }

        return '';
    }

    /**
     * @return array{
     *     type:?string,
     *     types:list<string>,
     *     itemTypes:list<string>,
     *     labelTypes:list<string>,
     *     typeSource:?string,
     *     typeSources:list<array{type:string, source:string, element:string}>
     * }
     */
    private function navItemTypeReport(\DOMElement $item, ?\DOMElement $label): array
    {
        $itemTypes = $this->epubTypes($item);
        $labelTypes = $label instanceof \DOMElement ? $this->epubTypes($label) : [];
        $types = [];
        $typeSources = [];
        $sourceByType = [];

        $addTypes = static function (array $sourceTypes, string $source, string $element) use (&$types, &$typeSources, &$sourceByType): void {
            foreach ($sourceTypes as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }

                if (!in_array($type, $types, true)) {
                    $types[] = $type;
                    $sourceByType[$type] = $source;
                }

                $typeSources[] = [
                    'type' => $type,
                    'source' => $source,
                    'element' => $element,
                ];
            }
        };

        $addTypes($labelTypes, 'label', $label instanceof \DOMElement ? $label->localName : '');
        $addTypes($itemTypes, 'item', $item->localName);
        $type = $types[0] ?? null;

        return [
            'type' => $type,
            'types' => $types,
            'itemTypes' => $itemTypes,
            'labelTypes' => $labelTypes,
            'typeSource' => $type === null ? null : ($sourceByType[$type] ?? null),
            'typeSources' => $typeSources,
        ];
    }

    /**
     * @return list<string>
     */
    private function epubTypes(\DOMElement $element): array
    {
        $value = trim($element->getAttributeNS(self::EPUB_TYPE_NS, 'type'));
        if ($value === '') {
            $value = trim($element->getAttribute('epub:type'));
        }
        if ($value === '') {
            $value = trim($element->getAttribute('type'));
        }

        return $this->tokens($value);
    }

    private function elementLanguage(\DOMElement $element): string
    {
        $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($element->getAttribute('xml:lang'));
        }
        if ($language === '') {
            $language = trim($element->getAttribute('lang'));
        }

        return $language;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $tokens = preg_split('/\s+/', trim($value)) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function isExternalHref(string $href): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', trim($href)) === 1;
    }

    /**
     * @return array{hasQuery:bool, query:?string, hasFragment:bool, fragment:?string}
     */
    private function hrefSuffix(string $href): array
    {
        $fragment = null;
        $beforeFragment = $href;
        $hashPosition = strpos($href, '#');
        if ($hashPosition !== false) {
            $fragment = substr($href, $hashPosition + 1);
            $beforeFragment = substr($href, 0, $hashPosition);
        }

        $query = null;
        $queryPosition = strpos($beforeFragment, '?');
        if ($queryPosition !== false) {
            $query = substr($beforeFragment, $queryPosition + 1);
        }

        return [
            'hasQuery' => $query !== null,
            'query' => $query,
            'hasFragment' => $fragment !== null,
            'fragment' => $fragment,
        ];
    }

    /**
     * @param array{hasQuery:bool, query:?string, hasFragment:bool, fragment:?string} $suffix
     */
    private function targetWithSuffix(string $path, array $suffix): string
    {
        if ($path === '') {
            return '';
        }

        return $path
            . (($suffix['hasQuery'] && $suffix['query'] !== null) ? '?' . $suffix['query'] : '')
            . (($suffix['hasFragment'] && $suffix['fragment'] !== null) ? '#' . $suffix['fragment'] : '');
    }

    private function loadXmlFile(string $path): \DOMDocument
    {
        if (!is_file($path)) {
            throw new \RuntimeException('EPUB XML asset does not exist: ' . $path);
        }

        $xml = file_get_contents($path);
        if ($xml === false) {
            throw new \RuntimeException('Unable to read EPUB XML asset: ' . $path);
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
            throw new \RuntimeException('Unable to parse EPUB XML asset ' . $path . ': ' . $message);
        }

        return $document;
    }

    private function resolveExistingPackagePath(string $root, string $relative): string
    {
        $normalized = $this->normalizeRelativePath($relative);
        $absolute = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
        if ($absolute === false || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('EPUB package path escapes the package root: ' . $relative);
        }

        return $absolute;
    }

    private function packagePathExists(string $root, string $relative): bool
    {
        $normalized = $this->normalizeRelativePath($relative);
        $absolute = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));

        return $absolute !== false && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR) && is_file($absolute);
    }

    private function resolvePackageHref(string $baseDir, string $href): string
    {
        $withoutFragment = explode('#', $href, 2)[0];
        $withoutQuery = explode('?', $withoutFragment, 2)[0];
        if ($withoutQuery === '' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $withoutQuery) === 1) {
            return $withoutQuery;
        }

        return $this->normalizeRelativePath($baseDir . '/' . rawurldecode($withoutQuery));
    }

    private function resolveContentHref(string $baseDir, string $href): string
    {
        $href = trim($href);
        if ($href === '' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1 || str_starts_with($href, '#')) {
            return $href;
        }

        [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);

        return $path . ($fragment === '' ? '' : '#' . $fragment);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitResolvedHref(string $baseDir, string $href): array
    {
        $parts = explode('#', $href, 2);
        $path = $parts[0] === '' ? '' : $this->resolvePackageHref($baseDir, $parts[0]);

        return [$path, $parts[1] ?? ''];
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new \RuntimeException('EPUB relative path escapes package root: ' . $path);
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function relativeDirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));

        return $dir === '.' ? '' : $dir;
    }

    private function normalizedText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
