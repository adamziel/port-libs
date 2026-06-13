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
        $tocReport = $this->tocReport($root, $toc, $package['spine'], $package['manifest']);
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
        $idrefItems = [];
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
            if (($item['idref'] ?? '') !== '') {
                $idrefItems[(string) $item['idref']][] = [
                    'index' => $index,
                    'idref' => (string) $item['idref'],
                    'href' => (string) ($item['href'] ?? ''),
                    'target' => (string) ($item['target'] ?? ''),
                    'path' => (string) ($item['path'] ?? ''),
                    'linear' => (bool) ($item['linear'] ?? true),
                    'readable' => (bool) ($item['readable'] ?? false),
                ];
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
        }

        $duplicateIdrefItems = [];
        foreach ($idrefItems as $idref => $items) {
            if (count($items) <= 1) {
                continue;
            }

            $indexes = array_column($items, 'index');
            $duplicate = [
                'idref' => $idref,
                'indexes' => $indexes,
                'paths' => array_values(array_unique(array_column($items, 'path'))),
                'targets' => array_values(array_unique(array_column($items, 'target'))),
                'items' => $items,
            ];
            $duplicateIdrefItems[] = $duplicate;
            $diagnostics[] = [
                'index' => $indexes[1],
                'type' => 'duplicate-spine-idref',
                'idref' => $idref,
                'indexes' => $indexes,
                'paths' => $duplicate['paths'],
                'targets' => $duplicate['targets'],
            ];
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
            'duplicateIdrefGroupCount' => count($duplicateIdrefItems),
            'duplicateIdrefItems' => $duplicateIdrefItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $toc
     * @param list<array<string, mixed>> $spine
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function tocReport(string $root, array $toc, array $spine, array $manifest): array
    {
        $flatEntries = $this->flattenedTocEntries($toc);
        $typeCounts = [];
        $pageListEntries = [];

        foreach ($flatEntries as $flat) {
            $entry = $flat['entry'];
            $type = is_string($entry['type'] ?? null) ? $entry['type'] : '';
            if ($type === '') {
                continue;
            }

            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            if ($type === 'page-list') {
                $pageListEntries[] = $flat;
            }
        }

        $pageListReport = $this->pageListTargetReport($root, $pageListEntries, $spine, $manifest);
        $diagnostics = [];
        foreach ($pageListReport['diagnostics'] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $diagnostics[] = ['section' => 'page-list'] + $diagnostic;
        }

        return [
            'entryCount' => count($flatEntries),
            'typeCounts' => $typeCounts,
            'tocEntryCount' => $typeCounts['toc'] ?? 0,
            'landmarksEntryCount' => $typeCounts['landmarks'] ?? 0,
            'pageListEntryCount' => $typeCounts['page-list'] ?? 0,
            'pageList' => $pageListReport,
            'issueCount' => count($diagnostics),
            'issues' => $diagnostics,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array{index:int, depth:int, entry:array<string, mixed>}> $pageListEntries
     * @param list<array<string, mixed>> $spine
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function pageListTargetReport(string $root, array $pageListEntries, array $spine, array $manifest): array
    {
        $manifestByPath = $this->manifestByPath($manifest);
        $spineByPath = $this->spineByPath($spine);
        $targetCounts = [];
        $firstTargetIndexes = [];

        foreach ($pageListEntries as $pageListIndex => $flat) {
            $target = $this->tocEntryTarget($flat['entry']);
            if ($target === '') {
                continue;
            }

            $targetCounts[$target] = ($targetCounts[$target] ?? 0) + 1;
            $firstTargetIndexes[$target] ??= $pageListIndex;
        }

        $items = [];
        $itemsByTarget = [];
        $itemsBySpineIndex = [];
        $diagnostics = [];
        $seenTargets = [];
        $mappedItemCount = 0;
        $linearTargetCount = 0;
        $nonlinearTargetCount = 0;
        $missingManifestTargetCount = 0;
        $missingPackagePartTargetCount = 0;
        $outsideSpineTargetCount = 0;
        $duplicateSpineIdrefTargetCount = 0;
        $repeatedTargetCount = 0;
        $repeatedTargetGroups = 0;

        foreach ($pageListEntries as $flat) {
            $entry = $flat['entry'];
            $index = count($items);
            $href = is_string($entry['href'] ?? null) ? $entry['href'] : '';
            $path = is_string($entry['path'] ?? null) ? $entry['path'] : '';
            $fragment = is_string($entry['fragment'] ?? null) ? $entry['fragment'] : '';
            $target = $this->tocEntryTarget($entry);
            $external = $href !== '' && $this->isExternalHref($href);
            $manifestItem = (!$external && $path !== '') ? ($manifestByPath[$path] ?? null) : null;
            $exists = false;
            if (!$external && is_array($manifestItem)) {
                $exists = (bool) ($manifestItem['exists'] ?? false);
            } elseif (!$external && $path !== '') {
                $exists = $this->packagePathExists($root, $path);
            }

            $spineMatches = (!$external && $path !== '') ? ($spineByPath[$path] ?? []) : [];
            $spineIndexes = array_column($spineMatches, 'index');
            $spineIdrefs = array_column($spineMatches, 'idref');
            $linearSpineIndexes = array_values(array_map(
                static fn (array $item): int => (int) $item['index'],
                array_filter($spineMatches, static fn (array $item): bool => ($item['linear'] ?? false) === true)
            ));
            $nonlinearSpineIndexes = array_values(array_map(
                static fn (array $item): int => (int) $item['index'],
                array_filter($spineMatches, static fn (array $item): bool => ($item['linear'] ?? false) !== true)
            ));
            $primarySpineItem = $spineMatches[0] ?? null;
            $duplicateSpineIdref = count($spineIdrefs) > count(array_unique($spineIdrefs));
            $repeatedTarget = $target !== '' && ($targetCounts[$target] ?? 0) > 1;
            $targetOccurrence = 0;
            if ($target !== '') {
                $seenTargets[$target] = ($seenTargets[$target] ?? 0) + 1;
                $targetOccurrence = $seenTargets[$target];
            }

            if ($spineMatches !== []) {
                ++$mappedItemCount;
            }
            if ($linearSpineIndexes !== []) {
                ++$linearTargetCount;
            }
            if ($spineMatches !== [] && $linearSpineIndexes === []) {
                ++$nonlinearTargetCount;
            }
            if ($duplicateSpineIdref) {
                ++$duplicateSpineIdrefTargetCount;
            }
            if ($repeatedTarget) {
                ++$repeatedTargetCount;
            }

            $itemDiagnostics = [];
            $addDiagnostic = static function (array $diagnostic) use (&$itemDiagnostics, &$diagnostics, $index): void {
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = ['index' => $index] + $diagnostic;
            };

            if ($href === '' || $target === '') {
                $addDiagnostic([
                    'type' => 'missing-page-list-target',
                    'href' => $href,
                ]);
            } elseif ($external) {
                $addDiagnostic([
                    'type' => 'external-page-list-reference',
                    'target' => $target,
                ]);
            } elseif (!is_array($manifestItem)) {
                ++$missingManifestTargetCount;
                $addDiagnostic([
                    'type' => 'missing-page-list-manifest-target',
                    'target' => $target,
                    'path' => $path,
                ]);
            } elseif (!$exists) {
                ++$missingPackagePartTargetCount;
                $addDiagnostic([
                    'type' => 'missing-page-list-reference',
                    'target' => $target,
                    'path' => $path,
                    'manifestId' => $manifestItem['id'],
                ]);
            } elseif ($spineMatches === []) {
                ++$outsideSpineTargetCount;
                $addDiagnostic([
                    'type' => 'page-list-target-outside-spine',
                    'target' => $target,
                    'path' => $path,
                    'manifestId' => $manifestItem['id'],
                ]);
            } elseif ($linearSpineIndexes === []) {
                $addDiagnostic([
                    'type' => 'page-list-target-nonlinear',
                    'target' => $target,
                    'path' => $path,
                    'spineIndexes' => $spineIndexes,
                    'spineIdrefs' => $spineIdrefs,
                ]);
            }

            if ($duplicateSpineIdref) {
                $addDiagnostic([
                    'type' => 'page-list-target-duplicate-spine-idref',
                    'target' => $target,
                    'path' => $path,
                    'spineIndexes' => $spineIndexes,
                    'spineIdrefs' => $spineIdrefs,
                ]);
            }

            if ($repeatedTarget && $targetOccurrence > 1) {
                if ($targetOccurrence === 2) {
                    ++$repeatedTargetGroups;
                }
                $addDiagnostic([
                    'type' => 'repeated-page-list-target',
                    'target' => $target,
                    'firstIndex' => $firstTargetIndexes[$target] ?? null,
                    'occurrence' => $targetOccurrence,
                    'occurrenceCount' => $targetCounts[$target] ?? 0,
                ]);
            }

            $item = [
                'index' => $index,
                'tocIndex' => (int) $flat['index'],
                'depth' => (int) $flat['depth'],
                'label' => is_string($entry['label'] ?? null) ? $entry['label'] : '',
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'external' => $external,
                'exists' => $exists,
                'manifestId' => is_array($manifestItem) ? (string) $manifestItem['id'] : '',
                'mediaType' => is_array($manifestItem) ? (string) $manifestItem['mediaType'] : '',
                'spineIndexes' => $spineIndexes,
                'spineIdrefs' => $spineIdrefs,
                'linearSpineIndexes' => $linearSpineIndexes,
                'nonlinearSpineIndexes' => $nonlinearSpineIndexes,
                'primarySpineIndex' => is_array($primarySpineItem) ? (int) $primarySpineItem['index'] : null,
                'primarySpineIdref' => is_array($primarySpineItem) ? (string) $primarySpineItem['idref'] : null,
                'linear' => is_array($primarySpineItem) ? (bool) $primarySpineItem['linear'] : null,
                'readable' => is_array($primarySpineItem) ? (bool) $primarySpineItem['readable'] : null,
                'duplicateSpineIdref' => $duplicateSpineIdref,
                'repeatedTarget' => $repeatedTarget,
                'repeatedTargetCount' => $target !== '' ? ($targetCounts[$target] ?? 0) : 0,
                'diagnostics' => $itemDiagnostics,
            ];
            $items[] = $item;

            if ($target !== '') {
                $itemsByTarget[$target][] = $item;
            }
            foreach ($spineIndexes as $spineIndex) {
                $itemsBySpineIndex[$spineIndex][] = $item;
            }
        }
        ksort($itemsBySpineIndex);

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'mappedItemCount' => $mappedItemCount,
            'linearTargetCount' => $linearTargetCount,
            'nonlinearTargetCount' => $nonlinearTargetCount,
            'missingManifestTargetCount' => $missingManifestTargetCount,
            'missingPackagePartTargetCount' => $missingPackagePartTargetCount,
            'outsideSpineTargetCount' => $outsideSpineTargetCount,
            'duplicateSpineIdrefTargetCount' => $duplicateSpineIdrefTargetCount,
            'repeatedTargetCount' => $repeatedTargetCount,
            'repeatedTargetGroupCount' => $repeatedTargetGroups,
            'items' => $items,
            'itemsByTarget' => $itemsByTarget,
            'itemsBySpineIndex' => $itemsBySpineIndex,
            'issueCount' => count($diagnostics),
            'issues' => $diagnostics,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array{index:int, depth:int, entry:array<string, mixed>}>
     */
    private function flattenedTocEntries(array $entries): array
    {
        $flat = [];
        $this->appendFlattenedTocEntries($entries, 0, $flat);

        return $flat;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array{index:int, depth:int, entry:array<string, mixed>}> $flat
     */
    private function appendFlattenedTocEntries(array $entries, int $depth, array &$flat): void
    {
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $flat[] = [
                'index' => count($flat),
                'depth' => $depth,
                'entry' => $entry,
            ];
            $children = is_array($entry['children'] ?? null) ? $entry['children'] : [];
            if ($children !== []) {
                $this->appendFlattenedTocEntries($children, $depth + 1, $flat);
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, array<string, mixed>>
     */
    private function manifestByPath(array $manifest): array
    {
        $manifestByPath = [];
        foreach ($manifest as $item) {
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $item;
            }
        }

        return $manifestByPath;
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @return array<string, list<array<string, mixed>>>
     */
    private function spineByPath(array $spine): array
    {
        $spineByPath = [];
        foreach ($spine as $index => $item) {
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            if ($path === '') {
                continue;
            }

            $spineByPath[$path][] = [
                'index' => $index,
                'idref' => (string) ($item['idref'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'target' => (string) ($item['target'] ?? ''),
                'path' => $path,
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'linear' => (bool) ($item['linear'] ?? true),
                'readable' => (bool) ($item['readable'] ?? false),
            ];
        }

        return $spineByPath;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function tocEntryTarget(array $entry): string
    {
        $path = is_string($entry['path'] ?? null) ? $entry['path'] : '';
        $fragment = is_string($entry['fragment'] ?? null) ? $entry['fragment'] : '';
        if ($path === '') {
            return '';
        }

        return $path . ($fragment === '' ? '' : '#' . $fragment);
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
     * @return list<array{label:string, href:string, path:string, fragment:string, type:string, children:list<array<string, mixed>>}>
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

            foreach ($this->readNavList($ol, $navDir, $type) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
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
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);
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
