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
        $navigation = $this->readNavigationDocument($root, $package);
        $toc = $navigation['entries'];
        $tocReport = $this->tocReport($toc, $package['manifest'], $package['spine']);
        $ncx = $this->readNcxDocument($root, $package);
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
                'tocReport' => $tocReport,
                'navReport' => $navigation['report'],
                'ncx' => $ncx,
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
        $this->annotateDuplicateSpineItemrefs($spine);
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
     */
    private function annotateDuplicateSpineItemrefs(array &$spine): void
    {
        $indexesByIdref = [];
        foreach ($spine as $index => $item) {
            $idref = (string) ($item['idref'] ?? '');
            if ($idref === '') {
                continue;
            }

            $indexesByIdref[$idref][] = $index;
        }

        foreach ($indexesByIdref as $idref => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                if (!isset($spine[$index]['diagnostics']) || !is_array($spine[$index]['diagnostics'])) {
                    $spine[$index]['diagnostics'] = [];
                }
                $spine[$index]['diagnostics'][] = [
                    'type' => 'duplicate-spine-itemref-idref',
                    'idref' => $idref,
                    'count' => count($indexes),
                    'indexes' => $indexes,
                ];
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @return array<string, mixed>
     */
    private function spineReport(array $spine): array
    {
        $idrefCounts = [];
        foreach ($spine as $item) {
            $idref = (string) ($item['idref'] ?? '');
            if ($idref === '') {
                continue;
            }

            $idrefCounts[$idref] = ($idrefCounts[$idref] ?? 0) + 1;
        }
        $duplicateIdrefs = array_keys(array_filter(
            $idrefCounts,
            static fn (int $count): bool => $count > 1
        ));
        $duplicateIdrefLookup = array_fill_keys($duplicateIdrefs, true);
        $linearItemCount = 0;
        $readableItemCount = 0;
        $externalItems = [];
        $missingPackagePartItems = [];
        $missingManifestItems = [];
        $duplicateIdrefItems = [];
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
            if (isset($duplicateIdrefLookup[(string) ($item['idref'] ?? '')])) {
                $duplicateIdrefItems[] = $item;
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
            'duplicateIdrefCount' => count($duplicateIdrefs),
            'duplicateIdrefs' => $duplicateIdrefs,
            'duplicateIdrefItemCount' => count($duplicateIdrefItems),
            'duplicateIdrefItems' => $duplicateIdrefItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $toc
     * @param array<string, array<string, mixed>> $manifest
     * @param list<array<string, mixed>> $spine
     * @return array<string, mixed>
     */
    private function tocReport(array $toc, array $manifest, array $spine): array
    {
        $items = $this->flattenTocEntries($toc);
        $typeCounts = [];
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            if ($type === '') {
                continue;
            }

            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }

        $pageListReport = $this->pageListReport($items, $manifest, $spine);

        return [
            'itemCount' => count($items),
            'typeCounts' => $typeCounts,
            'pageListItemCount' => $pageListReport['itemCount'],
            'pageList' => $pageListReport,
            'diagnosticCount' => $pageListReport['diagnosticCount'],
            'diagnostics' => $pageListReport['diagnostics'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $toc
     * @return list<array<string, mixed>>
     */
    private function flattenTocEntries(array $toc, int $depth = 0): array
    {
        $items = [];
        foreach ($toc as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $children = is_array($entry['children'] ?? null) ? $entry['children'] : [];
            $item = $entry;
            $item['depth'] = $depth;
            $item['tocIndex'] = count($items);
            unset($item['children']);
            $items[] = $item;

            foreach ($this->flattenTocEntries($children, $depth + 1) as $child) {
                $child['tocIndex'] = count($items);
                $items[] = $child;
            }
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $tocItems
     * @param array<string, array<string, mixed>> $manifest
     * @param list<array<string, mixed>> $spine
     * @return array<string, mixed>
     */
    private function pageListReport(array $tocItems, array $manifest, array $spine): array
    {
        $manifestByPath = [];
        foreach ($manifest as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path === '' || ($item['external'] ?? false) === true || isset($manifestByPath[$path])) {
                continue;
            }

            $manifestByPath[$path] = $item;
        }

        $spineByPath = [];
        $readingSpineByPath = [];
        foreach ($spine as $index => $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $spineItem = ['index' => $index] + $item;
            $spineByPath[$path][] = $spineItem;
            if (($item['linear'] ?? false) === true) {
                $readingSpineByPath[$path][] = $spineItem;
            }
        }

        $pageListItems = [];
        $targetItems = [];
        foreach ($tocItems as $tocItem) {
            if (($tocItem['type'] ?? '') !== 'page-list') {
                continue;
            }

            $pageListIndex = count($pageListItems);
            $pageListItems[] = $tocItem;
            $href = (string) ($tocItem['href'] ?? '');
            $path = (string) ($tocItem['path'] ?? '');
            if ($href === '' || $path === '' || $this->isExternalHref($href)) {
                continue;
            }

            $fragment = (string) ($tocItem['fragment'] ?? '');
            $target = $path . ($fragment === '' ? '' : '#' . $fragment);
            $targetItems[$target][] = [
                'index' => $pageListIndex,
                'href' => $href,
                'path' => $path,
                'fragment' => $fragment,
            ];
        }

        $duplicateTargets = [];
        $duplicateTargetByTarget = [];
        $duplicatePageTargetItemCount = 0;
        foreach ($targetItems as $target => $targetGroup) {
            if (count($targetGroup) < 2) {
                continue;
            }

            $group = [
                'target' => $target,
                'path' => (string) ($targetGroup[0]['path'] ?? ''),
                'fragment' => (string) ($targetGroup[0]['fragment'] ?? ''),
                'count' => count($targetGroup),
                'indexes' => array_column($targetGroup, 'index'),
                'hrefs' => array_column($targetGroup, 'href'),
            ];
            $duplicateTargets[] = $group;
            $duplicateTargetByTarget[$target] = $group;
            $duplicatePageTargetItemCount += count($targetGroup);
        }

        $items = [];
        $diagnostics = [];
        $duplicateSpineTargetsByPath = [];
        $duplicateSpineTargetItemCount = 0;
        $manifestTargetCount = 0;
        $spineReadingOrderTargetCount = 0;
        $missingManifestTargetCount = 0;
        $outsideSpineTargetCount = 0;
        $externalTargetCount = 0;
        $unresolvedTargetCount = 0;

        foreach ($pageListItems as $tocItem) {
            $href = (string) ($tocItem['href'] ?? '');
            $path = (string) ($tocItem['path'] ?? '');
            $fragment = (string) ($tocItem['fragment'] ?? '');
            $target = $path . ($fragment === '' ? '' : '#' . $fragment);
            $external = $href !== '' && $this->isExternalHref($href);
            $manifestItem = $manifestByPath[$path] ?? null;
            $spineItems = $spineByPath[$path] ?? [];
            $readingSpineItems = $readingSpineByPath[$path] ?? [];
            $spineIndexes = array_map(static fn (array $item): int => (int) $item['index'], $spineItems);
            $readingSpineIndexes = array_map(static fn (array $item): int => (int) $item['index'], $readingSpineItems);
            $duplicateTarget = $target !== '' ? ($duplicateTargetByTarget[$target] ?? null) : null;
            $itemDiagnostics = [];

            if ($href === '' || $path === '') {
                ++$unresolvedTargetCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-page-list-target',
                    'href' => $href,
                    'label' => (string) ($tocItem['label'] ?? ''),
                ];
            } elseif ($external) {
                ++$externalTargetCount;
                $itemDiagnostics[] = [
                    'type' => 'external-page-list-reference',
                    'href' => $href,
                    'target' => $path,
                ];
            } elseif (!is_array($manifestItem)) {
                ++$missingManifestTargetCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-page-list-manifest-item',
                    'href' => $href,
                    'path' => $path,
                ];
            } else {
                ++$manifestTargetCount;
                if ($readingSpineItems !== []) {
                    ++$spineReadingOrderTargetCount;
                } else {
                    ++$outsideSpineTargetCount;
                    $diagnostic = [
                        'type' => 'page-list-target-outside-spine-reading-order',
                        'href' => $href,
                        'path' => $path,
                        'manifestId' => (string) ($manifestItem['id'] ?? ''),
                        'reason' => $spineItems !== [] ? 'nonlinear-spine-item' : 'not-in-spine',
                    ];
                    if ($spineIndexes !== []) {
                        $diagnostic['spineIndex'] = $spineIndexes[0];
                    }
                    $itemDiagnostics[] = $diagnostic;
                }
            }

            if (is_array($duplicateTarget)) {
                $itemDiagnostics[] = [
                    'type' => 'duplicate-page-list-target',
                    'href' => $href,
                    'target' => $duplicateTarget['target'],
                    'path' => $path,
                    'fragment' => $fragment,
                    'count' => $duplicateTarget['count'],
                    'indexes' => $duplicateTarget['indexes'],
                ];
            }

            if (is_array($manifestItem) && count($spineItems) > 1) {
                $duplicateSpineTargetItemCount++;
                if (!isset($duplicateSpineTargetsByPath[$path])) {
                    $duplicateSpineTargetsByPath[$path] = [
                        'path' => $path,
                        'count' => count($spineItems),
                        'spineIndexes' => $spineIndexes,
                        'idrefs' => array_map(static fn (array $item): string => (string) ($item['idref'] ?? ''), $spineItems),
                    ];
                }
                $itemDiagnostics[] = [
                    'type' => 'page-list-target-duplicate-spine-itemref',
                    'href' => $href,
                    'path' => $path,
                    'manifestId' => (string) ($manifestItem['id'] ?? ''),
                    'count' => count($spineItems),
                    'spineIndexes' => $spineIndexes,
                    'idrefs' => $duplicateSpineTargetsByPath[$path]['idrefs'],
                ];
            }

            $itemIndex = count($items);
            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $itemIndex,
                    'tocIndex' => $tocItem['tocIndex'] ?? $itemIndex,
                ] + $diagnostic;
            }

            $firstSpineItem = $spineItems[0] ?? null;
            $items[] = [
                'index' => $itemIndex,
                'tocIndex' => $tocItem['tocIndex'] ?? $itemIndex,
                'label' => (string) ($tocItem['label'] ?? ''),
                'href' => $href,
                'path' => $path,
                'fragment' => $fragment,
                'manifestId' => is_array($manifestItem) ? (string) ($manifestItem['id'] ?? '') : '',
                'mediaType' => is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : '',
                'spineIndex' => is_array($firstSpineItem) ? $firstSpineItem['index'] : null,
                'spineIndexes' => $spineIndexes,
                'readingSpineIndexes' => $readingSpineIndexes,
                'spineLinear' => is_array($firstSpineItem) ? (bool) ($firstSpineItem['linear'] ?? false) : null,
                'inSpineReadingOrder' => $readingSpineItems !== [],
                'duplicatePageTarget' => is_array($duplicateTarget),
                'duplicatePageTargetCount' => is_array($duplicateTarget) ? $duplicateTarget['count'] : 0,
                'duplicateSpineTarget' => count($spineItems) > 1,
                'duplicateSpineTargetCount' => count($spineItems),
                'external' => $external,
                'diagnostics' => $itemDiagnostics,
            ];
        }

        return [
            'itemCount' => count($items),
            'manifestTargetCount' => $manifestTargetCount,
            'spineReadingOrderTargetCount' => $spineReadingOrderTargetCount,
            'missingManifestTargetCount' => $missingManifestTargetCount,
            'outsideSpineTargetCount' => $outsideSpineTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'unresolvedTargetCount' => $unresolvedTargetCount,
            'duplicatePageTargetCount' => count($duplicateTargets),
            'duplicatePageTargetItemCount' => $duplicatePageTargetItemCount,
            'duplicatePageTargets' => $duplicateTargets,
            'duplicateSpineTargetCount' => count($duplicateSpineTargetsByPath),
            'duplicateSpineTargetItemCount' => $duplicateSpineTargetItemCount,
            'duplicateSpineTargets' => array_values($duplicateSpineTargetsByPath),
            'items' => $items,
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
     * @param array{manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>, spine:list<array{idref:string}>} $package
     * @return array{entries:list<array{label:string, href:string, path:string, fragment:string, type:string, children:list<array<string, mixed>>>}, report:array<string, mixed>}
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
            return [
                'entries' => [],
                'report' => $this->navigationReport([], '', ''),
            ];
        }

        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $navItem['path']));
        $navDir = $this->relativeDirname($navItem['path']);
        $entries = [];
        $sections = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }

            $type = $this->epubType($element);
            if ($type !== 'toc' && $type !== 'landmarks' && $type !== 'page-list') {
                continue;
            }

            $ol = $this->firstDirectChild($element, 'ol');
            $sectionEntries = $ol instanceof \DOMElement ? $this->readNavList($ol, $navDir, $type) : [];
            $sections[] = [
                'index' => count($sections),
                'type' => $type,
                'id' => trim($element->getAttribute('id')),
                'entries' => $sectionEntries,
            ];

            foreach ($sectionEntries as $entry) {
                $entries[] = $entry;
            }
        }

        return [
            'entries' => $entries,
            'report' => $this->navigationReport($sections, $navItem['path'], $navDir),
        ];
    }

    /**
     * @param list<array{index:int, type:string, id:string, entries:list<array<string, mixed>>}> $sections
     * @return array<string, mixed>
     */
    private function navigationReport(array $sections, string $navPath, string $navDir): array
    {
        $reportSections = [];
        $diagnostics = [];
        $normalizedCollisionDiagnostics = [];
        $crossSectionTargets = [];
        $itemCount = 0;
        $targetedItemCount = 0;
        $localTargetCount = 0;
        $externalTargetCount = 0;
        $fragmentTargetCount = 0;
        $fragmentOnlyTargetCount = 0;
        $unsafeTargetCount = 0;
        $packageRootEscapeTargetCount = 0;
        $normalizedCollisionItemCount = 0;

        foreach ($sections as $section) {
            $flatItems = $this->flattenNavEntries(is_array($section['entries'] ?? null) ? $section['entries'] : []);
            $sectionDiagnostics = [];
            $targets = [];
            $sectionTargetedItemCount = 0;
            $sectionLocalTargetCount = 0;
            $sectionExternalTargetCount = 0;
            $sectionFragmentTargetCount = 0;
            $sectionFragmentOnlyTargetCount = 0;
            $sectionUnsafeTargetCount = 0;
            $sectionPackageRootEscapeTargetCount = 0;

            foreach ($flatItems as $flat) {
                $entry = is_array($flat['entry'] ?? null) ? $flat['entry'] : [];
                $itemIndex = (int) ($flat['index'] ?? 0);
                $href = is_string($entry['href'] ?? null) ? trim($entry['href']) : '';
                ++$itemCount;

                if ($href === '') {
                    continue;
                }

                ++$targetedItemCount;
                ++$sectionTargetedItemCount;
                $target = $this->navTargetReview($navDir, $navPath, $href);
                $baseDiagnostic = [
                    'sectionIndex' => (int) $section['index'],
                    'sectionType' => (string) $section['type'],
                    'sectionId' => (string) $section['id'],
                    'itemIndex' => $itemIndex,
                    'depth' => (int) ($flat['depth'] ?? 0),
                    'label' => is_string($entry['label'] ?? null) ? $entry['label'] : '',
                    'href' => $href,
                ];

                if ($target['external']) {
                    ++$externalTargetCount;
                    ++$sectionExternalTargetCount;
                    $sectionDiagnostics[] = $baseDiagnostic + [
                        'type' => 'external-nav-href-target',
                        'target' => $target['target'],
                        'message' => 'EPUB navigation target uses an external URI',
                    ];
                    continue;
                }

                if ($target['unsafe']) {
                    ++$unsafeTargetCount;
                    ++$packageRootEscapeTargetCount;
                    ++$sectionUnsafeTargetCount;
                    ++$sectionPackageRootEscapeTargetCount;
                    $sectionDiagnostics[] = $baseDiagnostic + [
                        'type' => 'unsafe-nav-href-target',
                        'target' => $target['target'],
                        'message' => 'EPUB navigation target escapes the package root after href normalization',
                    ];
                    continue;
                }

                ++$localTargetCount;
                ++$sectionLocalTargetCount;
                if ($target['hasFragment']) {
                    ++$fragmentTargetCount;
                    ++$sectionFragmentTargetCount;
                    $sectionDiagnostics[] = $baseDiagnostic + [
                        'type' => $target['fragmentOnly'] ? 'fragment-only-nav-href-target' : 'fragment-nav-href-target',
                        'target' => $target['target'],
                        'fragment' => $target['fragment'],
                        'message' => $target['fragmentOnly']
                            ? 'EPUB navigation target resolves to a fragment in the navigation document'
                            : 'EPUB navigation target includes a fragment component',
                    ];
                }
                if ($target['fragmentOnly']) {
                    ++$fragmentOnlyTargetCount;
                    ++$sectionFragmentOnlyTargetCount;
                }

                if ($target['normalizedTarget'] !== '') {
                    $targetDiagnostic = $baseDiagnostic + [
                        'target' => $target['target'],
                        'normalizedTarget' => $target['normalizedTarget'],
                        'path' => $target['path'],
                        'fragment' => $target['fragment'],
                        'fragmentOnly' => $target['fragmentOnly'],
                    ];
                    $targets[$target['normalizedTarget']][] = $targetDiagnostic;
                    $crossSectionTargets[$target['normalizedTarget']][] = $targetDiagnostic;
                }
            }

            $sectionCollisionDiagnostics = [];
            foreach ($targets as $normalizedTarget => $matches) {
                $rawHrefs = array_values(array_unique(array_map(
                    static fn (array $match): string => (string) ($match['href'] ?? ''),
                    $matches
                )));
                if (count($matches) <= 1 || count($rawHrefs) <= 1) {
                    continue;
                }

                $diagnostic = [
                    'type' => 'normalized-nav-target-collision',
                    'sectionIndex' => (int) $section['index'],
                    'sectionType' => (string) $section['type'],
                    'sectionId' => (string) $section['id'],
                    'normalizedTarget' => $normalizedTarget,
                    'itemCount' => count($matches),
                    'rawHrefCount' => count($rawHrefs),
                    'itemIndexes' => array_column($matches, 'itemIndex'),
                    'hrefs' => $rawHrefs,
                    'targets' => array_values(array_unique(array_map(
                        static fn (array $match): string => (string) ($match['target'] ?? ''),
                        $matches
                    ))),
                    'labels' => array_values(array_filter(
                        array_column($matches, 'label'),
                        static fn (mixed $label): bool => is_string($label) && $label !== ''
                    )),
                    'collisionKinds' => $this->navCollisionKinds($matches),
                    'message' => 'EPUB navigation section contains distinct hrefs that normalize to the same target',
                ];
                $sectionCollisionDiagnostics[] = $diagnostic;
            }
            $sectionCollisionDiagnostics = $this->sortNavCollisionDiagnostics($sectionCollisionDiagnostics);

            foreach ($sectionCollisionDiagnostics as $diagnostic) {
                $normalizedCollisionItemCount += (int) ($diagnostic['itemCount'] ?? 0);
                $sectionDiagnostics[] = $diagnostic;
                $normalizedCollisionDiagnostics[] = $diagnostic;
            }
            array_push($diagnostics, ...$sectionDiagnostics);

            $reportSections[] = [
                'index' => (int) $section['index'],
                'type' => (string) $section['type'],
                'id' => (string) $section['id'],
                'itemCount' => count($flatItems),
                'targetedItemCount' => $sectionTargetedItemCount,
                'localTargetCount' => $sectionLocalTargetCount,
                'externalTargetCount' => $sectionExternalTargetCount,
                'fragmentTargetCount' => $sectionFragmentTargetCount,
                'fragmentOnlyTargetCount' => $sectionFragmentOnlyTargetCount,
                'unsafeTargetCount' => $sectionUnsafeTargetCount,
                'packageRootEscapeTargetCount' => $sectionPackageRootEscapeTargetCount,
                'normalizedCollisionGroupCount' => count($sectionCollisionDiagnostics),
                'normalizedCollisionItemCount' => array_sum(array_map(
                    static fn (array $diagnostic): int => (int) ($diagnostic['itemCount'] ?? 0),
                    $sectionCollisionDiagnostics
                )),
                'diagnosticCount' => count($sectionDiagnostics),
                'diagnostics' => $sectionDiagnostics,
            ];
        }

        $normalizedCollisionDiagnostics = $this->sortNavCollisionDiagnostics($normalizedCollisionDiagnostics);
        $crossSectionCollisionDiagnostics = $this->crossSectionNavCollisionDiagnostics($crossSectionTargets);
        $crossSectionCollisionItemCount = array_sum(array_map(
            static fn (array $diagnostic): int => (int) ($diagnostic['itemCount'] ?? 0),
            $crossSectionCollisionDiagnostics
        ));
        $diagnostics = $this->sortNavDiagnostics(array_merge($diagnostics, $crossSectionCollisionDiagnostics));
        $diagnosticTypes = [];
        foreach ($diagnostics as $diagnostic) {
            $type = is_string($diagnostic['type'] ?? null) ? $diagnostic['type'] : '';
            if ($type === '') {
                continue;
            }
            $diagnosticTypes[$type] = ($diagnosticTypes[$type] ?? 0) + 1;
        }

        return [
            'present' => $sections !== [],
            'navPath' => $navPath,
            'sectionCount' => count($sections),
            'itemCount' => $itemCount,
            'targetedItemCount' => $targetedItemCount,
            'localTargetCount' => $localTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'fragmentTargetCount' => $fragmentTargetCount,
            'fragmentOnlyTargetCount' => $fragmentOnlyTargetCount,
            'unsafeTargetCount' => $unsafeTargetCount,
            'packageRootEscapeTargetCount' => $packageRootEscapeTargetCount,
            'normalizedCollisionGroupCount' => count($normalizedCollisionDiagnostics),
            'normalizedCollisionItemCount' => $normalizedCollisionItemCount,
            'normalizedCollisionDiagnostics' => $normalizedCollisionDiagnostics,
            'crossSectionCollisionGroupCount' => count($crossSectionCollisionDiagnostics),
            'crossSectionCollisionItemCount' => $crossSectionCollisionItemCount,
            'crossSectionCollisionDiagnostics' => $crossSectionCollisionDiagnostics,
            'sections' => $reportSections,
            'diagnosticTypes' => $diagnosticTypes,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array{index:int, depth:int, entry:array<string, mixed>}>
     */
    private function flattenNavEntries(array $entries): array
    {
        $flat = [];
        $index = 0;
        $this->flattenNavEntriesInto($entries, 0, $index, $flat);

        return $flat;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array{index:int, depth:int, entry:array<string, mixed>}> $flat
     */
    private function flattenNavEntriesInto(array $entries, int $depth, int &$index, array &$flat): void
    {
        foreach ($entries as $entry) {
            $currentIndex = $index++;
            $flat[] = [
                'index' => $currentIndex,
                'depth' => $depth,
                'entry' => $entry,
            ];

            $children = is_array($entry['children'] ?? null) ? $entry['children'] : [];
            if ($children !== []) {
                $this->flattenNavEntriesInto($children, $depth + 1, $index, $flat);
            }
        }
    }

    /**
     * @return array{
     *     path:string,
     *     fragment:string,
     *     target:string,
     *     normalizedTarget:string,
     *     hasFragment:bool,
     *     fragmentOnly:bool,
     *     external:bool,
     *     unsafe:bool
     * }
     */
    private function navTargetReview(string $navDir, string $navPath, string $href): array
    {
        $href = trim($href);
        $suffix = $this->hrefSuffix($href);
        $external = $this->isExternalHref($href);
        $fragmentOnly = str_starts_with($href, '#');
        $split = $this->safeSplitResolvedHref($navDir, $href);
        $unsafe = (bool) $split['unsafe'];
        $path = (string) $split['path'];
        $fragment = (string) $split['fragment'];

        if ($external) {
            return [
                'path' => $path,
                'fragment' => $fragment,
                'target' => $href,
                'normalizedTarget' => '',
                'hasFragment' => $suffix['hasFragment'],
                'fragmentOnly' => false,
                'external' => true,
                'unsafe' => false,
            ];
        }

        if ($unsafe) {
            return [
                'path' => $path,
                'fragment' => $fragment,
                'target' => $href,
                'normalizedTarget' => '',
                'hasFragment' => $suffix['hasFragment'],
                'fragmentOnly' => $fragmentOnly,
                'external' => false,
                'unsafe' => true,
            ];
        }

        $targetPath = $fragmentOnly ? $navPath : $path;
        $target = $targetPath
            . (($suffix['hasFragment'] && $fragment !== '') ? '#' . $fragment : ($suffix['hasFragment'] ? '#' : ''));

        return [
            'path' => $path,
            'fragment' => $fragment,
            'target' => $target,
            'normalizedTarget' => $this->normalizedNavTarget($targetPath, $suffix['hasFragment'], $fragment),
            'hasFragment' => $suffix['hasFragment'],
            'fragmentOnly' => $fragmentOnly,
            'external' => false,
            'unsafe' => false,
        ];
    }

    private function normalizedNavTarget(string $path, bool $hasFragment, string $fragment): string
    {
        if ($path === '') {
            return '';
        }

        $target = strtolower($path);
        if ($hasFragment) {
            $target .= '#' . strtolower(rawurldecode($fragment));
        }

        return $target;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<string>
     */
    private function navCollisionKinds(array $matches): array
    {
        $hrefs = array_values(array_unique(array_map(
            static fn (array $match): string => (string) ($match['href'] ?? ''),
            $matches
        )));
        $targets = array_values(array_unique(array_map(
            static fn (array $match): string => (string) ($match['target'] ?? ''),
            $matches
        )));
        $kinds = [];

        foreach ($hrefs as $href) {
            if (preg_match('/%[0-9A-Fa-f]{2}/', $href) === 1) {
                $kinds['percent-encoding'] = 'percent-encoding';
            }
            if (preg_match('~(?:^|/)\.{1,2}(?:/|$)~', explode('#', explode('?', $href, 2)[0], 2)[0]) === 1) {
                $kinds['dot-segment'] = 'dot-segment';
            }
            if (strpos($href, '#') !== false) {
                $kinds['fragment'] = 'fragment';
            }
        }

        if (count(array_unique(array_map('strtolower', $hrefs))) < count($hrefs)
            || count(array_unique(array_map('strtolower', $targets))) < count($targets)
        ) {
            $kinds['case'] = 'case';
        }

        if ($kinds === []) {
            $kinds['normalized-target'] = 'normalized-target';
        }

        return array_values($kinds);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $targets
     * @return list<array<string, mixed>>
     */
    private function crossSectionNavCollisionDiagnostics(array $targets): array
    {
        $diagnostics = [];
        foreach ($targets as $normalizedTarget => $matches) {
            $sectionKeys = [];
            foreach ($matches as $match) {
                $sectionKeys[((int) ($match['sectionIndex'] ?? 0)) . "\0" . ((string) ($match['sectionType'] ?? ''))] = true;
            }
            if (count($matches) <= 1 || count($sectionKeys) <= 1) {
                continue;
            }

            $matches = $this->sortNavTargetMatches($matches);
            $sectionTypes = [];
            $sectionIndexes = [];
            $sectionIds = [];
            $itemRefs = [];
            foreach ($matches as $match) {
                $sectionType = (string) ($match['sectionType'] ?? '');
                $sectionIndex = (int) ($match['sectionIndex'] ?? 0);
                $sectionId = (string) ($match['sectionId'] ?? '');
                $sectionTypes[$sectionType] = $sectionType;
                $sectionIndexes[(string) $sectionIndex] = $sectionIndex;
                if ($sectionId !== '') {
                    $sectionIds[$sectionId] = $sectionId;
                }
                $itemRefs[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionType' => $sectionType,
                    'sectionId' => $sectionId,
                    'itemIndex' => (int) ($match['itemIndex'] ?? 0),
                    'depth' => (int) ($match['depth'] ?? 0),
                    'href' => (string) ($match['href'] ?? ''),
                    'label' => (string) ($match['label'] ?? ''),
                ];
            }

            $rawHrefs = $this->uniqueNavMatchValues($matches, 'href');
            $diagnostics[] = [
                'type' => 'cross-section-normalized-nav-target-collision',
                'normalizedTarget' => $normalizedTarget,
                'sectionCount' => count($sectionKeys),
                'sectionTypes' => array_values($sectionTypes),
                'sectionIndexes' => array_values($sectionIndexes),
                'sectionIds' => array_values($sectionIds),
                'itemCount' => count($matches),
                'rawHrefCount' => count($rawHrefs),
                'itemRefs' => $itemRefs,
                'hrefs' => $rawHrefs,
                'targets' => $this->uniqueNavMatchValues($matches, 'target'),
                'labels' => array_values(array_filter(
                    $this->uniqueNavMatchValues($matches, 'label'),
                    static fn (string $label): bool => $label !== ''
                )),
                'collisionKinds' => $this->navCollisionKinds($matches),
                'message' => 'EPUB navigation sections contain targets that normalize to the same package target',
            ];
        }

        return $this->sortNavCrossSectionCollisionDiagnostics($diagnostics);
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function sortNavCollisionDiagnostics(array $diagnostics): array
    {
        usort($diagnostics, function (array $left, array $right): int {
            return $this->compareNavDiagnostics($left, $right);
        });

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function sortNavCrossSectionCollisionDiagnostics(array $diagnostics): array
    {
        usort($diagnostics, function (array $left, array $right): int {
            $leftRefs = is_array($left['itemRefs'] ?? null) ? $left['itemRefs'] : [];
            $rightRefs = is_array($right['itemRefs'] ?? null) ? $right['itemRefs'] : [];
            $leftFirst = is_array($leftRefs[0] ?? null) ? $leftRefs[0] : [];
            $rightFirst = is_array($rightRefs[0] ?? null) ? $rightRefs[0] : [];

            return $this->compareNavDiagnostics($leftFirst, $rightFirst)
                ?: strcmp((string) ($left['normalizedTarget'] ?? ''), (string) ($right['normalizedTarget'] ?? ''));
        });

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<array<string, mixed>>
     */
    private function sortNavDiagnostics(array $diagnostics): array
    {
        usort($diagnostics, function (array $left, array $right): int {
            return $this->compareNavDiagnostics($left, $right)
                ?: strcmp((string) ($left['type'] ?? ''), (string) ($right['type'] ?? ''))
                ?: strcmp((string) ($left['normalizedTarget'] ?? ''), (string) ($right['normalizedTarget'] ?? ''));
        });

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<array<string, mixed>>
     */
    private function sortNavTargetMatches(array $matches): array
    {
        usort($matches, function (array $left, array $right): int {
            return $this->compareNavDiagnostics($left, $right)
                ?: strcmp((string) ($left['href'] ?? ''), (string) ($right['href'] ?? ''));
        });

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<string>
     */
    private function uniqueNavMatchValues(array $matches, string $key): array
    {
        $values = [];
        foreach ($matches as $match) {
            $value = (string) ($match[$key] ?? '');
            if (!in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function compareNavDiagnostics(array $left, array $right): int
    {
        $leftType = (string) ($left['sectionType'] ?? '');
        $rightType = (string) ($right['sectionType'] ?? '');

        return $this->navSectionTypeRank($leftType) <=> $this->navSectionTypeRank($rightType)
            ?: ((int) ($left['sectionIndex'] ?? 0) <=> (int) ($right['sectionIndex'] ?? 0))
            ?: ($this->navDiagnosticFirstItemIndex($left) <=> $this->navDiagnosticFirstItemIndex($right))
            ?: ((int) ($left['depth'] ?? 0) <=> (int) ($right['depth'] ?? 0));
    }

    /**
     * @param array<string, mixed> $diagnostic
     */
    private function navDiagnosticFirstItemIndex(array $diagnostic): int
    {
        if (is_int($diagnostic['itemIndex'] ?? null)) {
            return (int) $diagnostic['itemIndex'];
        }
        if (is_array($diagnostic['itemIndexes'] ?? null) && isset($diagnostic['itemIndexes'][0])) {
            return (int) $diagnostic['itemIndexes'][0];
        }

        return PHP_INT_MAX;
    }

    private function navSectionTypeRank(string $type): int
    {
        return match ($type) {
            'toc' => 0,
            'landmarks' => 1,
            'page-list' => 2,
            default => 3,
        };
    }


    /**
     * @param array{manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>, spine:list<array{idref:string}>} $package
     * @return list<array{label:string, href:string, path:string, fragment:string, playOrder:int, children:list<array<string, mixed>>}>
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
     * @return list<array{label:string, href:string, path:string, fragment:string, type:string, children:list<array<string, mixed>>}>
     */
    private function readNavList(\DOMElement $ol, string $baseDir, string $type): array
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
            $target = $this->safeSplitResolvedHref($baseDir, $href);
            $path = $target['path'];
            $fragment = $target['fragment'];
            $children = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'ol') {
                    $children = $this->readNavList($child, $baseDir, $type);
                    break;
                }
            }

            $entries[] = [
                'label' => $this->normalizedText($link->textContent),
                'href' => $href,
                'path' => $path,
                'fragment' => $fragment,
                'type' => $type,
                'children' => $children,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{label:string, href:string, path:string, fragment:string, playOrder:int, children:list<array<string, mixed>>}>
     */
    private function readNcxPoints(\DOMElement $parent, string $baseDir): array
    {
        $points = [];
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'navPoint') {
                continue;
            }

            $label = $this->firstChildPathText($node, ['navLabel', 'text']);
            $content = $this->firstDirectChild($node, 'content');
            $href = $content instanceof \DOMElement ? trim($content->getAttribute('src')) : '';
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);
            $points[] = [
                'label' => $label,
                'href' => $href,
                'path' => $path,
                'fragment' => $fragment,
                'playOrder' => is_numeric($node->getAttribute('playOrder')) ? (int) $node->getAttribute('playOrder') : 0,
                'children' => $this->readNcxPoints($node, $baseDir),
            ];
        }

        return $points;
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
        $value = trim($element->getAttributeNS(self::EPUB_TYPE_NS, 'type'));
        if ($value === '') {
            $value = trim($element->getAttribute('epub:type'));
        }
        if ($value === '') {
            $value = trim($element->getAttribute('type'));
        }

        foreach ($this->tokens($value) as $token) {
            if ($token === 'toc' || $token === 'landmarks' || $token === 'page-list') {
                return $token;
            }
        }

        return '';
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

    /**
     * @return array{path:string, fragment:string, unsafe:bool}
     */
    private function safeSplitResolvedHref(string $baseDir, string $href): array
    {
        try {
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);

            return [
                'path' => $path,
                'fragment' => $fragment,
                'unsafe' => false,
            ];
        } catch (\RuntimeException) {
            $parts = explode('#', $href, 2);

            return [
                'path' => '',
                'fragment' => $parts[1] ?? '',
                'unsafe' => true,
            ];
        }
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
