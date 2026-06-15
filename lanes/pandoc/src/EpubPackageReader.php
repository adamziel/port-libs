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
        $navReport = $this->navReport($toc, $package, $root);
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
                'spineMetadata' => $package['spineMetadata'],
                'spineReport' => $package['spineReport'],
                'guide' => $package['guide'],
                'toc' => $toc,
                'tocReport' => $navReport,
                'navReport' => $navReport,
                'navigationReport' => $navReport,
                'pageListReport' => $navReport['pageList'],
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
        $manifestOccurrences = [];
        $manifestIndex = 0;
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
                $item = [
                    'index' => $manifestIndex,
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
                    'fallback' => trim($node->getAttribute('fallback')),
                    'fallbackStyle' => trim($node->getAttribute('fallback-style')),
                    'mediaOverlay' => trim($node->getAttribute('media-overlay')),
                    'diagnostics' => $diagnostics,
                ];
                $manifestOccurrences[$id][] = $item;
                $manifest[$id] = $item;
                ++$manifestIndex;
            }
        }
        $manifestReport = $this->manifestReport($manifest, $manifestOccurrences);

        $spineElement = $this->firstDirectChild($packageElement, 'spine');
        $spineMetadata = $this->spineMetadataReport($spineElement);
        $spine = [];
        $spineIndex = 0;
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
                    'index' => $spineIndex,
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
                ++$spineIndex;
            }
        }
        $spineReport = $this->spineReport($spine, $spineMetadata);

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
            'spineMetadata' => $spineMetadata,
            'spineReport' => $spineReport,
            'guide' => $guide,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spineMetadataReport(?\DOMElement $spineElement): array
    {
        $diagnostics = [];
        $rawDirection = $spineElement instanceof \DOMElement
            ? trim($spineElement->getAttribute('page-progression-direction'))
            : '';
        $specified = $rawDirection !== '';
        $normalized = strtolower($rawDirection);
        $direction = 'default';
        $valid = true;

        if ($specified) {
            if (in_array($normalized, ['ltr', 'rtl', 'default'], true)) {
                $direction = $normalized;
            } else {
                $valid = false;
                $diagnostics[] = [
                    'type' => 'invalid-spine-page-progression-direction',
                    'value' => $rawDirection,
                    'message' => 'EPUB spine page-progression-direction must be ltr, rtl, or default',
                ];
            }
        }

        return [
            'present' => $spineElement instanceof \DOMElement,
            'id' => $spineElement instanceof \DOMElement ? $this->nullableAttribute($spineElement, 'id') : null,
            'toc' => $spineElement instanceof \DOMElement ? $this->nullableAttribute($spineElement, 'toc') : null,
            'pageProgressionDirection' => $direction,
            'pageProgressionDirectionRaw' => $specified ? $rawDirection : null,
            'pageProgressionDirectionSpecified' => $specified,
            'pageProgressionDirectionValid' => $valid,
            'rightToLeft' => $direction === 'rtl',
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, list<array<string, mixed>>> $manifestOccurrences
     * @return array<string, mixed>
     */
    private function manifestReport(array $manifest, array $manifestOccurrences): array
    {
        $externalItems = [];
        $missingItems = [];
        $hrefSuffixItems = [];
        $diagnostics = [];

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
                $diagnostics[] = ['index' => (int) ($item['index'] ?? 0), 'id' => $item['id']] + $diagnostic;
            }
        }

        $duplicateIdItems = [];
        $duplicateIdDiagnostics = [];
        foreach ($manifestOccurrences as $id => $items) {
            if (count($items) < 2) {
                continue;
            }

            $indexes = array_map(static fn (array $item): int => (int) ($item['index'] ?? 0), $items);
            $hrefs = array_map(static fn (array $item): string => (string) ($item['href'] ?? ''), $items);
            $targets = array_map(static fn (array $item): string => (string) ($item['target'] ?? ''), $items);
            $duplicateIdItems[] = [
                'id' => $id,
                'itemCount' => count($items),
                'indexes' => $indexes,
                'hrefs' => $hrefs,
                'targets' => $targets,
                'items' => array_values($items),
            ];
            foreach ($items as $item) {
                $duplicateIdDiagnostics[] = [
                    'type' => 'duplicate-manifest-id',
                    'index' => (int) ($item['index'] ?? 0),
                    'id' => $id,
                    'indexes' => $indexes,
                    'hrefs' => $hrefs,
                    'targets' => $targets,
                    'message' => 'EPUB OPF manifest contains multiple item elements with the same id',
                ];
            }
        }

        $referenceReport = $this->manifestItemReferenceReport($manifest);
        $diagnostics = array_merge($diagnostics, $duplicateIdDiagnostics, $referenceReport['manifestItemReferenceDiagnostics']);

        return [
            'itemCount' => count($manifest),
            'externalItemCount' => count($externalItems),
            'externalItems' => $externalItems,
            'missingItemCount' => count($missingItems),
            'missingItems' => $missingItems,
            'duplicateManifestIdCount' => count($duplicateIdItems),
            'duplicateManifestItemCount' => array_sum(array_map(
                static fn (array $item): int => (int) $item['itemCount'],
                $duplicateIdItems
            )),
            'duplicateManifestIds' => array_map(
                static fn (array $item): string => (string) $item['id'],
                $duplicateIdItems
            ),
            'duplicateManifestIdItems' => $duplicateIdItems,
            'duplicateManifestIdDiagnostics' => $duplicateIdDiagnostics,
            'hrefSuffixCount' => count($hrefSuffixItems),
            'hrefSuffixItems' => $hrefSuffixItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ] + $referenceReport;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @return array<string, mixed>
     */
    private function manifestItemReferenceReport(array $manifest): array
    {
        $fallbackReferences = [];
        $fallbackDiagnostics = [];
        $fallbackStyleReferences = [];
        $fallbackStyleDiagnostics = [];
        $mediaOverlayReferences = [];
        $mediaOverlayDiagnostics = [];

        foreach ($manifest as $item) {
            $this->appendManifestReference(
                $item,
                $manifest,
                'fallback',
                'fallback',
                null,
                [
                    'missing' => 'missing-manifest-fallback-item',
                    'external' => 'external-manifest-fallback-target',
                    'missingPart' => 'missing-manifest-fallback-part',
                ],
                $fallbackReferences,
                $fallbackDiagnostics
            );
            $this->appendManifestReference(
                $item,
                $manifest,
                'fallbackStyle',
                'fallback-style',
                'text/css',
                [
                    'missing' => 'missing-manifest-fallback-style-item',
                    'external' => 'external-manifest-fallback-style-target',
                    'missingPart' => 'missing-manifest-fallback-style-part',
                    'unexpectedType' => 'non-css-manifest-fallback-style',
                ],
                $fallbackStyleReferences,
                $fallbackStyleDiagnostics
            );
            $this->appendManifestReference(
                $item,
                $manifest,
                'mediaOverlay',
                'media-overlay',
                'application/smil+xml',
                [
                    'missing' => 'missing-manifest-media-overlay-item',
                    'external' => 'external-manifest-media-overlay-target',
                    'missingPart' => 'missing-manifest-media-overlay-part',
                    'unexpectedType' => 'unexpected-manifest-media-overlay-type',
                ],
                $mediaOverlayReferences,
                $mediaOverlayDiagnostics
            );
        }

        $allDiagnostics = array_merge($fallbackDiagnostics, $fallbackStyleDiagnostics, $mediaOverlayDiagnostics);

        return [
            'manifestItemReferenceCount' => count($fallbackReferences) + count($fallbackStyleReferences) + count($mediaOverlayReferences),
            'manifestItemReferenceDiagnosticCount' => count($allDiagnostics),
            'manifestItemReferenceDiagnostics' => $allDiagnostics,
            'fallbackReferenceCount' => count($fallbackReferences),
            'fallbackReferences' => $fallbackReferences,
            'fallbackReferenceDiagnosticCount' => count($fallbackDiagnostics),
            'fallbackReferenceDiagnostics' => $fallbackDiagnostics,
            'fallbackStyleReferenceCount' => count($fallbackStyleReferences),
            'fallbackStyleReferences' => $fallbackStyleReferences,
            'fallbackStyleReferenceDiagnosticCount' => count($fallbackStyleDiagnostics),
            'fallbackStyleReferenceDiagnostics' => $fallbackStyleDiagnostics,
            'mediaOverlayReferenceCount' => count($mediaOverlayReferences),
            'mediaOverlayReferences' => $mediaOverlayReferences,
            'mediaOverlayReferenceDiagnosticCount' => count($mediaOverlayDiagnostics),
            'mediaOverlayReferenceDiagnostics' => $mediaOverlayDiagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, string> $diagnosticTypes
     * @param list<array<string, mixed>> $references
     * @param list<array<string, mixed>> $diagnostics
     */
    private function appendManifestReference(
        array $item,
        array $manifest,
        string $field,
        string $attribute,
        ?string $expectedMediaType,
        array $diagnosticTypes,
        array &$references,
        array &$diagnostics
    ): void {
        $targetId = trim((string) ($item[$field] ?? ''));
        if ($targetId === '') {
            return;
        }

        $sourceId = (string) ($item['id'] ?? '');
        $target = $manifest[$targetId] ?? null;
        $referenceDiagnostics = [];
        $reference = [
            'sourceId' => $sourceId,
            'sourceIndex' => (int) ($item['index'] ?? 0),
            'attribute' => $attribute,
            'targetId' => $targetId,
            'target' => is_array($target) ? (string) ($target['target'] ?? '') : '',
            'targetPath' => is_array($target) ? (string) ($target['path'] ?? '') : '',
            'targetMediaType' => is_array($target) ? (string) ($target['mediaType'] ?? '') : '',
            'targetExternal' => is_array($target) && ($target['external'] ?? false) === true,
            'targetExists' => is_array($target) && ($target['exists'] ?? false) === true,
        ];

        if (!is_array($target)) {
            $referenceDiagnostics[] = [
                'type' => $diagnosticTypes['missing'],
                'targetId' => $targetId,
                'message' => 'EPUB OPF manifest item references another manifest id that is not declared',
            ];
        } else {
            if (($target['external'] ?? false) === true) {
                $referenceDiagnostics[] = [
                    'type' => $diagnosticTypes['external'],
                    'targetId' => $targetId,
                    'target' => (string) ($target['target'] ?? ''),
                    'message' => 'EPUB OPF manifest item reference points outside the package and was not fetched',
                ];
            } elseif (($target['path'] ?? '') !== '' && ($target['exists'] ?? true) !== true) {
                $referenceDiagnostics[] = [
                    'type' => $diagnosticTypes['missingPart'],
                    'targetId' => $targetId,
                    'path' => (string) ($target['path'] ?? ''),
                    'message' => 'EPUB OPF manifest item reference points at a missing package part',
                ];
            }

            $mediaType = (string) ($target['mediaType'] ?? '');
            if ($expectedMediaType !== null && $mediaType !== $expectedMediaType) {
                $referenceDiagnostics[] = [
                    'type' => (string) ($diagnosticTypes['unexpectedType'] ?? 'unexpected-manifest-reference-type'),
                    'targetId' => $targetId,
                    'mediaType' => $mediaType,
                    'expectedMediaType' => $expectedMediaType,
                    'message' => 'EPUB OPF manifest item reference resolves to an unexpected media type',
                ];
            }
        }

        $reference['diagnosticCount'] = count($referenceDiagnostics);
        $reference['diagnostics'] = $referenceDiagnostics;
        $references[] = $reference;

        foreach ($referenceDiagnostics as $diagnostic) {
            $diagnostics[] = [
                'sourceId' => $sourceId,
                'sourceIndex' => (int) ($item['index'] ?? 0),
                'attribute' => $attribute,
            ] + $diagnostic;
        }
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @return array<string, mixed>
     */
    private function spineReport(array $spine, array $spineMetadata): array
    {
        $linearItemCount = 0;
        $readableItemCount = 0;
        $externalItems = [];
        $missingPackagePartItems = [];
        $missingManifestItems = [];
        $spineMetadataDiagnostics = is_array($spineMetadata['diagnostics'] ?? null)
            ? array_values($spineMetadata['diagnostics'])
            : [];
        $diagnostics = $spineMetadataDiagnostics;
        $idrefItems = [];

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
                $idrefItems[(string) $item['idref']][] = $item;
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
        }

        $duplicateIdrefItems = [];
        $duplicateIdrefDiagnostics = [];
        foreach ($idrefItems as $idref => $items) {
            if (count($items) < 2) {
                continue;
            }

            $indexes = array_map(static fn (array $item): int => (int) ($item['index'] ?? 0), $items);
            $targets = array_map(static fn (array $item): string => (string) ($item['target'] ?? ''), $items);
            $linear = array_map(static fn (array $item): bool => (bool) ($item['linear'] ?? false), $items);
            $duplicateIdrefItems[] = [
                'idref' => $idref,
                'itemCount' => count($items),
                'indexes' => $indexes,
                'targets' => $targets,
                'linear' => $linear,
                'items' => array_values($items),
            ];
            foreach ($items as $item) {
                $duplicateIdrefDiagnostics[] = [
                    'type' => 'duplicate-spine-idref',
                    'index' => (int) ($item['index'] ?? 0),
                    'idref' => $idref,
                    'indexes' => $indexes,
                    'targets' => $targets,
                    'linear' => $linear,
                    'message' => 'EPUB spine contains multiple itemref elements for the same manifest idref',
                ];
            }
        }

        $diagnostics = array_merge($diagnostics, $duplicateIdrefDiagnostics);

        return [
            'itemCount' => count($spine),
            'linearItemCount' => $linearItemCount,
            'nonlinearItemCount' => count($spine) - $linearItemCount,
            'readableItemCount' => $readableItemCount,
            'skippedItemCount' => count($spine) - $readableItemCount,
            'spineMetadata' => $spineMetadata,
            'pageProgressionDirection' => (string) ($spineMetadata['pageProgressionDirection'] ?? 'default'),
            'pageProgressionDirectionRaw' => $spineMetadata['pageProgressionDirectionRaw'] ?? null,
            'pageProgressionDirectionSpecified' => (bool) ($spineMetadata['pageProgressionDirectionSpecified'] ?? false),
            'pageProgressionDirectionValid' => (bool) ($spineMetadata['pageProgressionDirectionValid'] ?? true),
            'rightToLeft' => (bool) ($spineMetadata['rightToLeft'] ?? false),
            'spineMetadataDiagnosticCount' => count($spineMetadataDiagnostics),
            'spineMetadataDiagnostics' => $spineMetadataDiagnostics,
            'externalItemCount' => count($externalItems),
            'externalItems' => $externalItems,
            'missingManifestItemCount' => count($missingManifestItems),
            'missingManifestItems' => $missingManifestItems,
            'missingPackagePartItemCount' => count($missingPackagePartItems),
            'missingPackagePartItems' => $missingPackagePartItems,
            'duplicateIdrefCount' => count($duplicateIdrefItems),
            'duplicateIdrefItemCount' => array_sum(array_map(
                static fn (array $item): int => (int) $item['itemCount'],
                $duplicateIdrefItems
            )),
            'duplicateIdrefs' => array_map(
                static fn (array $item): string => (string) $item['idref'],
                $duplicateIdrefItems
            ),
            'duplicateIdrefItems' => $duplicateIdrefItems,
            'duplicateIdrefDiagnostics' => $duplicateIdrefDiagnostics,
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
     * @return list<array<string, mixed>>
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
    private function navReport(array $entries, array $package, string $root): array
    {
        $flat = $this->flattenNavigationEntries($entries);
        $documentReport = $this->navDocumentReport($package, $root);
        $pageListReport = $this->pageListReport($flat, $package);
        $normalizedCollisionReport = $this->navReportWithPageListTargets(
            $this->navNormalizedCollisionReport(
                $flat,
                is_string($documentReport['path'] ?? null) ? $documentReport['path'] : ''
            ),
            $pageListReport
        );
        $hrefPolicyReport = $this->navHrefPolicyReport($flat);
        $hrefNormalizationReport = $this->navHrefNormalizationReport($flat);
        $sections = [];
        $sectionKeys = [];
        $entriesByType = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        $typeCounts = [];

        foreach ($flat as $item) {
            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = (string) $sectionIndex;
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== '') {
                $typeCounts[$sectionType] = ($typeCounts[$sectionType] ?? 0) + 1;
                if (isset($entriesByType[$sectionType])) {
                    $entriesByType[$sectionType][] = $item;
                }
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
            'entryCount' => count($flat),
            'tocEntryCount' => count($entriesByType['toc']),
            'landmarksEntryCount' => count($entriesByType['landmarks']),
            'pageListEntryCount' => count($entriesByType['page-list']),
            'topLevelItemCount' => count($entries),
            'sectionCount' => count($sections),
            'types' => array_keys($typeCounts),
            'typeCounts' => $typeCounts,
            'entriesByType' => $entriesByType,
            'sections' => $sections,
            'hierarchy' => $this->hierarchySummary($flat, count($entries)),
            'document' => $documentReport,
            'documentDiagnosticCount' => $documentReport['diagnosticCount'],
            'documentDiagnostics' => $documentReport['diagnostics'],
            'targetedItemCount' => $hrefPolicyReport['targetedItemCount'],
            'localTargetCount' => $hrefPolicyReport['localTargetCount'],
            'safeLocalTargetCount' => $hrefPolicyReport['safeLocalTargetCount'],
            'externalTargetCount' => $hrefPolicyReport['externalTargetCount'],
            'unsafeTargetCount' => $hrefPolicyReport['unsafeTargetCount'],
            'hrefPolicyDiagnosticCount' => $hrefPolicyReport['diagnosticCount'],
            'hrefPolicy' => $hrefPolicyReport,
            'hrefNormalization' => $hrefNormalizationReport,
            'fragmentTargets' => $this->navFragmentTargetReport($flat, $root),
            'toc' => $this->tocNavigationReport($flat),
            'landmarks' => $this->landmarkReport($flat, $package),
            'pageList' => $pageListReport,
            'pageListItemCount' => $pageListReport['itemCount'],
            'pageBreakItemCount' => $pageListReport['pageBreakItemCount'],
            'normalizedCollisionGroupCount' => $normalizedCollisionReport['normalizedCollisionGroupCount'],
            'normalizedCollisionItemCount' => $normalizedCollisionReport['normalizedCollisionItemCount'],
            'normalizedCollisionDiagnostics' => $normalizedCollisionReport['normalizedCollisionDiagnostics'],
            'normalizedCollisionSections' => $normalizedCollisionReport['sections'],
            'crossSectionCollisionGroupCount' => $normalizedCollisionReport['crossSectionCollisionGroupCount'],
            'crossSectionCollisionItemCount' => $normalizedCollisionReport['crossSectionCollisionItemCount'],
            'crossSectionCollisionDiagnostics' => $normalizedCollisionReport['crossSectionCollisionDiagnostics'],
            'diagnosticTypes' => $this->diagnosticTypeCounts($pageListReport['diagnostics']),
            'diagnosticCount' => $pageListReport['diagnosticCount'],
            'diagnostics' => $pageListReport['diagnostics'],
        ];
    }

    /**
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function navDocumentReport(array $package, string $root): array
    {
        $navItem = null;
        foreach (is_array($package['manifest'] ?? null) ? $package['manifest'] : [] as $item) {
            if (is_array($item) && in_array('nav', is_array($item['properties'] ?? null) ? $item['properties'] : [], true)) {
                $navItem = $item;
                break;
            }
        }

        $diagnostics = [];
        if (!is_array($navItem)) {
            $diagnostics[] = [
                'type' => 'missing-nav-manifest-item',
                'message' => 'EPUB package manifest does not declare an XHTML navigation document',
            ];

            return [
                'present' => false,
                'part' => null,
                'path' => null,
                'sectionCount' => 0,
                'primarySectionCount' => 0,
                'tocSectionCount' => 0,
                'landmarksSectionCount' => 0,
                'pageListSectionCount' => 0,
                'requiredTocPresent' => false,
                'duplicatePrimaryTypeCount' => 0,
                'hiddenPrimarySectionCount' => 0,
                'missingHeadingSectionCount' => 0,
                'missingOrderedListSectionCount' => 0,
                'emptySectionCount' => 0,
                'untypedSectionCount' => 0,
                'unrecognizedSectionCount' => 0,
                'sections' => [],
                'diagnosticCount' => count($diagnostics),
                'diagnostics' => $diagnostics,
            ];
        }

        $path = is_string($navItem['path'] ?? null) ? $navItem['path'] : '';
        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $path));
        $sections = [];
        $primarySectionsByType = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        $primarySectionCount = 0;
        $hiddenPrimarySectionCount = 0;
        $missingHeadingSectionCount = 0;
        $missingOrderedListSectionCount = 0;
        $emptySectionCount = 0;
        $untypedSectionCount = 0;
        $unrecognizedSectionCount = 0;

        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }

            $sectionIndex = count($sections);
            $sectionTypes = $this->epubTypes($element);
            $primaryTypes = array_values(array_intersect($sectionTypes, ['toc', 'landmarks', 'page-list']));
            $sectionType = $primaryTypes[0] ?? null;
            $sectionId = $this->nullableAttribute($element, 'id');
            $sectionLabel = $this->navSectionLabel($element);
            $orderedList = $this->firstDirectChild($element, 'ol');
            $hasOrderedList = $orderedList instanceof \DOMElement;
            $itemCount = $hasOrderedList ? $this->directChildElementCount($orderedList, 'li') : 0;
            $hidden = $element->hasAttribute('hidden')
                || strtolower(trim($element->getAttribute('aria-hidden'))) === 'true';
            $sectionDiagnostics = [];

            if ($primaryTypes !== []) {
                ++$primarySectionCount;
                foreach ($primaryTypes as $primaryType) {
                    $primarySectionsByType[$primaryType][] = [
                        'index' => $sectionIndex,
                        'id' => $sectionId,
                        'label' => $sectionLabel,
                    ];
                }
                if ($hidden) {
                    ++$hiddenPrimarySectionCount;
                    $sectionDiagnostics[] = [
                        'type' => 'hidden-primary-nav-section',
                        'message' => 'EPUB navigation document contains a primary nav section hidden from readers',
                    ];
                }
                if ($sectionLabel === '') {
                    ++$missingHeadingSectionCount;
                    $sectionDiagnostics[] = [
                        'type' => 'missing-primary-nav-section-heading',
                        'message' => 'EPUB primary nav section is missing a heading label',
                    ];
                }
            } elseif ($sectionTypes === []) {
                ++$untypedSectionCount;
                $sectionDiagnostics[] = [
                    'type' => 'missing-nav-section-type',
                    'message' => 'EPUB nav section has no epub:type classification',
                ];
            } else {
                ++$unrecognizedSectionCount;
                $sectionDiagnostics[] = [
                    'type' => 'unrecognized-nav-section-type',
                    'message' => 'EPUB nav section has no recognized primary navigation type',
                ];
            }

            if (!$hasOrderedList) {
                ++$missingOrderedListSectionCount;
                $sectionDiagnostics[] = [
                    'type' => 'missing-nav-section-ordered-list',
                    'message' => 'EPUB nav section has no direct ordered list of navigation entries',
                ];
            }
            if ($itemCount === 0) {
                ++$emptySectionCount;
                $sectionDiagnostics[] = [
                    'type' => 'empty-nav-section',
                    'message' => 'EPUB nav section has no direct navigation list items',
                ];
            }

            $section = [
                'index' => $sectionIndex,
                'sectionIndex' => $sectionIndex,
                'id' => $sectionId,
                'sectionId' => $sectionId,
                'label' => $sectionLabel,
                'sectionLabel' => $sectionLabel,
                'type' => $sectionType,
                'sectionType' => $sectionType,
                'sectionTypes' => $primaryTypes,
                'epubTypes' => $sectionTypes,
                'hidden' => $hidden,
                'hasHeading' => $sectionLabel !== '',
                'hasOrderedList' => $hasOrderedList,
                'itemCount' => $itemCount,
                'diagnosticCount' => count($sectionDiagnostics),
                'diagnostics' => [],
            ];

            foreach ($sectionDiagnostics as $diagnostic) {
                $reportedDiagnostic = [
                    'index' => count($diagnostics),
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionType' => $sectionType,
                    'sectionTypes' => $primaryTypes,
                    'epubTypes' => $sectionTypes,
                    'label' => $sectionLabel,
                ] + $diagnostic;
                $diagnostics[] = $reportedDiagnostic;
                $section['diagnostics'][] = $reportedDiagnostic;
            }

            $sections[] = $section;
        }

        $duplicatePrimaryTypeCount = 0;
        foreach ($primarySectionsByType as $type => $matches) {
            if (count($matches) < 2) {
                continue;
            }

            ++$duplicatePrimaryTypeCount;
            $diagnostic = [
                'index' => count($diagnostics),
                'type' => 'duplicate-primary-nav-section',
                'sectionType' => $type,
                'sectionIndexes' => array_map(static fn (array $match): int => (int) $match['index'], $matches),
                'sectionIds' => array_map(static fn (array $match): ?string => $match['id'], $matches),
                'sectionLabels' => array_map(static fn (array $match): string => (string) $match['label'], $matches),
                'message' => 'EPUB navigation document contains multiple primary nav sections of the same type',
            ];
            $diagnostics[] = $diagnostic;
            foreach ($matches as $match) {
                $sectionIndex = (int) $match['index'];
                if (!isset($sections[$sectionIndex])) {
                    continue;
                }
                $sections[$sectionIndex]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionIndex]['diagnosticCount'];
            }
        }

        if ($primarySectionsByType['toc'] === []) {
            $diagnostics[] = [
                'index' => count($diagnostics),
                'type' => 'missing-primary-toc-nav-section',
                'message' => 'EPUB navigation document does not contain a primary toc nav section',
            ];
        }

        return [
            'present' => true,
            'part' => $path === '' ? null : '/' . $path,
            'path' => $path,
            'sectionCount' => count($sections),
            'primarySectionCount' => $primarySectionCount,
            'tocSectionCount' => count($primarySectionsByType['toc']),
            'landmarksSectionCount' => count($primarySectionsByType['landmarks']),
            'pageListSectionCount' => count($primarySectionsByType['page-list']),
            'requiredTocPresent' => $primarySectionsByType['toc'] !== [],
            'duplicatePrimaryTypeCount' => $duplicatePrimaryTypeCount,
            'hiddenPrimarySectionCount' => $hiddenPrimarySectionCount,
            'missingHeadingSectionCount' => $missingHeadingSectionCount,
            'missingOrderedListSectionCount' => $missingOrderedListSectionCount,
            'emptySectionCount' => $emptySectionCount,
            'untypedSectionCount' => $untypedSectionCount,
            'unrecognizedSectionCount' => $unrecognizedSectionCount,
            'sections' => $sections,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function navHrefNormalizationReport(array $flat): array
    {
        $sectionKeys = [];
        $sections = [];
        $sectionsByType = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        $typeCounts = [
            'toc' => 0,
            'landmarks' => 0,
            'page-list' => 0,
        ];
        $diagnostics = [];
        $localTargetCount = 0;
        $externalTargetCount = 0;
        $missingTargetCount = 0;
        $fragmentTargetCount = 0;
        $normalizedHrefCount = 0;
        $percentDecodedHrefCount = 0;
        $dotSegmentNormalizedHrefCount = 0;
        $packageRootEscapeCount = 0;
        $caseMismatchCount = 0;
        $emptyHrefCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if (!isset($typeCounts[$sectionType])) {
                continue;
            }

            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = $sectionIndex . ':' . $sectionType;
            if (!isset($sectionKeys[$sectionKey])) {
                $sectionKeys[$sectionKey] = count($sections);
                ++$typeCounts[$sectionType];
                $sections[] = [
                    'index' => $sectionIndex,
                    'sectionIndex' => $sectionIndex,
                    'id' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionLabel' => is_string($item['sectionLabel'] ?? null) ? $item['sectionLabel'] : '',
                    'type' => $sectionType,
                    'entryCount' => 0,
                    'itemCount' => 0,
                    'localTargetCount' => 0,
                    'externalTargetCount' => 0,
                    'missingTargetCount' => 0,
                    'fragmentTargetCount' => 0,
                    'normalizedHrefCount' => 0,
                    'percentDecodedHrefCount' => 0,
                    'dotSegmentNormalizedHrefCount' => 0,
                    'packageRootEscapeCount' => 0,
                    'caseMismatchCount' => 0,
                    'emptyHrefCount' => 0,
                    'diagnosticCount' => 0,
                    'diagnostics' => [],
                ];
            }

            $sectionOffset = $sectionKeys[$sectionKey];
            ++$sections[$sectionOffset]['entryCount'];
            ++$sections[$sectionOffset]['itemCount'];

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $external = ($item['external'] ?? false) === true;
            $unsafe = ($item['unsafe'] ?? false) === true;
            $exists = ($item['exists'] ?? false) === true;
            $normalization = is_array($item['normalization'] ?? null) ? $item['normalization'] : [];
            $packageRootEscape = ($normalization['packageRootEscape'] ?? false) === true;

            if ($href !== '' && !$external && !$unsafe && $path !== '') {
                ++$localTargetCount;
                ++$sections[$sectionOffset]['localTargetCount'];
                if (!$exists) {
                    ++$missingTargetCount;
                    ++$sections[$sectionOffset]['missingTargetCount'];
                }
            }
            if ($external && !$packageRootEscape) {
                ++$externalTargetCount;
                ++$sections[$sectionOffset]['externalTargetCount'];
            }
            if ($fragment !== '') {
                ++$fragmentTargetCount;
                ++$sections[$sectionOffset]['fragmentTargetCount'];
            }
            if (($normalization['normalized'] ?? false) === true) {
                ++$normalizedHrefCount;
                ++$sections[$sectionOffset]['normalizedHrefCount'];
            }
            if (($normalization['percentDecoded'] ?? false) === true) {
                ++$percentDecodedHrefCount;
                ++$sections[$sectionOffset]['percentDecodedHrefCount'];
            }
            if (($normalization['dotSegmentNormalized'] ?? false) === true) {
                ++$dotSegmentNormalizedHrefCount;
                ++$sections[$sectionOffset]['dotSegmentNormalizedHrefCount'];
            }
            if (($normalization['packageRootEscape'] ?? false) === true) {
                ++$packageRootEscapeCount;
                ++$sections[$sectionOffset]['packageRootEscapeCount'];
            }
            if (($normalization['caseMismatch'] ?? false) === true) {
                ++$caseMismatchCount;
                ++$sections[$sectionOffset]['caseMismatchCount'];
            }
            if ($href === '' && ($item['linkElement'] ?? null) === 'a') {
                ++$emptyHrefCount;
                ++$sections[$sectionOffset]['emptyHrefCount'];
            }

            foreach (is_array($item['hrefNormalizationDiagnostics'] ?? null) ? $item['hrefNormalizationDiagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $reportedDiagnostic = [
                    'index' => count($diagnostics),
                    'sourceIndex' => $sourceIndex,
                    'sectionIndex' => $sectionIndex,
                    'sectionType' => $sectionType,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => is_string($item['target'] ?? null) ? $item['target'] : '',
                ] + $diagnostic;
                $diagnostics[] = $reportedDiagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $reportedDiagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
            }
        }

        foreach ($sections as $section) {
            $type = is_string($section['type'] ?? null) ? $section['type'] : '';
            if (isset($sectionsByType[$type])) {
                $sectionsByType[$type][] = $section;
            }
        }

        return [
            'present' => $flat !== [],
            'entryCount' => count($flat),
            'itemCount' => count($flat),
            'sectionCount' => count($sections),
            'typeCounts' => $typeCounts,
            'sections' => $sections,
            'sectionsByType' => $sectionsByType,
            'localTargetCount' => $localTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'missingTargetCount' => $missingTargetCount,
            'fragmentTargetCount' => $fragmentTargetCount,
            'normalizedHrefCount' => $normalizedHrefCount,
            'percentDecodedHrefCount' => $percentDecodedHrefCount,
            'dotSegmentNormalizedHrefCount' => $dotSegmentNormalizedHrefCount,
            'packageRootEscapeCount' => $packageRootEscapeCount,
            'caseMismatchCount' => $caseMismatchCount,
            'emptyHrefCount' => $emptyHrefCount,
            'diagnosticCount' => count($diagnostics),
            'diagnosticTypes' => $this->diagnosticTypeCounts($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, int>
     */
    private function diagnosticTypeCounts(array $diagnostics): array
    {
        $counts = [];
        foreach ($diagnostics as $diagnostic) {
            $type = is_string($diagnostic['type'] ?? null) ? $diagnostic['type'] : '';
            if ($type === '') {
                continue;
            }

            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function navHrefPolicyReport(array $flat): array
    {
        $sectionKeys = [];
        $sections = [];
        $diagnostics = [];
        $targetedItemCount = 0;
        $localTargetCount = 0;
        $safeLocalTargetCount = 0;
        $externalTargetCount = 0;
        $unsafeTargetCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc' && $sectionType !== 'landmarks' && $sectionType !== 'page-list') {
                continue;
            }

            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = $sectionIndex . ':' . $sectionType;
            if (!isset($sectionKeys[$sectionKey])) {
                $sectionKeys[$sectionKey] = count($sections);
                $sections[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionLabel' => is_string($item['sectionLabel'] ?? null) ? $item['sectionLabel'] : '',
                    'type' => $sectionType,
                    'itemCount' => 0,
                    'topLevelItemCount' => 0,
                    'targetedItemCount' => 0,
                    'localTargetCount' => 0,
                    'safeLocalTargetCount' => 0,
                    'externalTargetCount' => 0,
                    'unsafeTargetCount' => 0,
                    'externalSchemeCounts' => [],
                    'diagnosticCount' => 0,
                    'diagnostics' => [],
                ];
            }

            $sectionOffset = $sectionKeys[$sectionKey];
            ++$sections[$sectionOffset]['itemCount'];
            if ((int) ($item['depth'] ?? 0) === 0) {
                ++$sections[$sectionOffset]['topLevelItemCount'];
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $external = ($item['external'] ?? false) === true;
            $unsafe = ($item['unsafe'] ?? false) === true;
            $scheme = is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null;
            if ($href !== '') {
                ++$targetedItemCount;
                ++$sections[$sectionOffset]['targetedItemCount'];
                if ($external) {
                    ++$externalTargetCount;
                    ++$sections[$sectionOffset]['externalTargetCount'];
                    if ($scheme !== null && $scheme !== '') {
                        $sections[$sectionOffset]['externalSchemeCounts'][$scheme] = ($sections[$sectionOffset]['externalSchemeCounts'][$scheme] ?? 0) + 1;
                    }
                } else {
                    ++$localTargetCount;
                    ++$sections[$sectionOffset]['localTargetCount'];
                    if (!$unsafe) {
                        ++$safeLocalTargetCount;
                        ++$sections[$sectionOffset]['safeLocalTargetCount'];
                    }
                }
            }
            if ($unsafe) {
                ++$unsafeTargetCount;
                ++$sections[$sectionOffset]['unsafeTargetCount'];
            }

            foreach (is_array($item['hrefDiagnostics'] ?? null) ? $item['hrefDiagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $reportedDiagnostic = [
                    'index' => count($diagnostics),
                    'sourceIndex' => $sourceIndex,
                    'sectionIndex' => $sectionIndex,
                    'sectionType' => $sectionType,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => is_string($item['target'] ?? null) ? $item['target'] : '',
                ] + $diagnostic;
                $diagnostics[] = $reportedDiagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $reportedDiagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
            }
        }

        $sectionTypeCounts = [
            'toc' => 0,
            'landmarks' => 0,
            'page-list' => 0,
        ];
        $sectionsByType = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        foreach ($sections as &$section) {
            ksort($section['externalSchemeCounts']);
            $type = is_string($section['type'] ?? null) ? $section['type'] : '';
            if (isset($sectionTypeCounts[$type])) {
                ++$sectionTypeCounts[$type];
                $sectionsByType[$type][] = $section;
            }
        }
        unset($section);

        return [
            'present' => $flat !== [],
            'itemCount' => count($flat),
            'targetedItemCount' => $targetedItemCount,
            'localTargetCount' => $localTargetCount,
            'safeLocalTargetCount' => $safeLocalTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'unsafeTargetCount' => $unsafeTargetCount,
            'sectionCount' => count($sections),
            'sectionTypeCounts' => $sectionTypeCounts,
            'tocSectionCount' => $sectionTypeCounts['toc'],
            'landmarksSectionCount' => $sectionTypeCounts['landmarks'],
            'pageListSectionCount' => $sectionTypeCounts['page-list'],
            'diagnosticCount' => count($diagnostics),
            'sections' => $sections,
            'sectionsByType' => $sectionsByType,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    private function pageListReport(array $flat, array $package): array
    {
        $manifestByPath = [];
        foreach (is_array($package['manifest'] ?? null) ? $package['manifest'] : [] as $manifestItem) {
            if (!is_array($manifestItem) || ($manifestItem['external'] ?? false) === true) {
                continue;
            }
            $path = is_string($manifestItem['path'] ?? null) ? $manifestItem['path'] : '';
            if ($path !== '' && !isset($manifestByPath[$path])) {
                $manifestByPath[$path] = $manifestItem;
            }
        }

        $spineByPath = [];
        $readingSpineByPath = [];
        foreach (is_array($package['spine'] ?? null) ? $package['spine'] : [] as $index => $spineItem) {
            if (!is_array($spineItem)) {
                continue;
            }
            $path = is_string($spineItem['path'] ?? null) ? $spineItem['path'] : '';
            if ($path === '') {
                continue;
            }
            $reportedSpineItem = ['index' => $index] + $spineItem;
            $spineByPath[$path][] = $reportedSpineItem;
            if (($spineItem['linear'] ?? false) === true) {
                $readingSpineByPath[$path][] = $reportedSpineItem;
            }
        }

        $collisionsByTarget = [];
        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc' && $sectionType !== 'landmarks') {
                continue;
            }

            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            if ($target === '') {
                continue;
            }

            $collisionsByTarget[$target][] = [
                'navigationIndex' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                'sourceIndex' => $sourceIndex,
                'type' => $sectionType,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'href' => is_string($item['href'] ?? null) ? $item['href'] : '',
                'target' => $target,
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : '',
            ];
        }

        $targetItems = [];
        $pageListIndex = 0;
        foreach ($flat as $sourceIndex => $item) {
            $type = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($type !== 'page-list') {
                continue;
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $external = (bool) ($item['external'] ?? false);
            if ($href !== '' && $path !== '' && !$external) {
                $target = $path . ($fragment === '' ? '' : '#' . $fragment);
                $targetItems[$target][] = [
                    'index' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                ];
            }
            ++$pageListIndex;
        }

        $duplicatePageTargets = [];
        $duplicatePageTargetsByTarget = [];
        $duplicatePageTargetItemCount = 0;
        foreach ($targetItems as $target => $items) {
            if (count($items) < 2) {
                continue;
            }

            $group = [
                'target' => $target,
                'path' => (string) ($items[0]['path'] ?? ''),
                'fragment' => (string) ($items[0]['fragment'] ?? ''),
                'count' => count($items),
                'indexes' => array_map(static fn (array $item): int => (int) ($item['index'] ?? 0), $items),
                'sourceIndexes' => array_map(static fn (array $item): int => (int) ($item['sourceIndex'] ?? 0), $items),
                'hrefs' => array_map(static fn (array $item): string => (string) ($item['href'] ?? ''), $items),
                'labels' => array_map(static fn (array $item): string => (string) ($item['label'] ?? ''), $items),
                'items' => array_values($items),
            ];
            $duplicatePageTargets[] = $group;
            $duplicatePageTargetsByTarget[$target] = $group;
            $duplicatePageTargetItemCount += count($items);
        }

        $pageListItems = [];
        $pageBreakItemCount = 0;
        $diagnostics = [];
        $seenPageListHrefs = [];
        $seenPageListLabels = [];
        $duplicateSpineTargetsByPath = [];
        $duplicateSpineTargetItemCount = 0;
        $collisionTargets = [];
        $fragmentTargets = [];
        $readingOrder = [];
        $previousSpineIndex = null;
        $linearTargetCount = 0;
        $nonlinearTargetCount = 0;
        $targetedItemCount = 0;
        $manifestTargetCount = 0;
        $spineReadingOrderTargetCount = 0;
        $missingManifestTargetCount = 0;
        $outsideSpineTargetCount = 0;
        $externalTargetCount = 0;
        $unresolvedTargetCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $type = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if (($item['pageBreakProvenance']['present'] ?? false) === true) {
                ++$pageBreakItemCount;
            }

            if ($type !== 'page-list') {
                continue;
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $diagnostics[] = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'navType' => $type,
                ] + $diagnostic;
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $external = (bool) ($item['external'] ?? false);
            $manifestItem = !$external && $path !== '' ? ($manifestByPath[$path] ?? null) : null;
            $spineItems = !$external && $path !== '' ? ($spineByPath[$path] ?? []) : [];
            $readingSpineItems = !$external && $path !== '' ? ($readingSpineByPath[$path] ?? []) : [];
            $spineItems = is_array($spineItems) ? array_values(array_filter($spineItems, 'is_array')) : [];
            $readingSpineItems = is_array($readingSpineItems) ? array_values(array_filter($readingSpineItems, 'is_array')) : [];
            $firstSpineItem = $spineItems[0] ?? null;
            $spineIndexes = array_map(static fn (array $spineItem): int => (int) ($spineItem['index'] ?? 0), $spineItems);
            $readingSpineIndexes = array_map(static fn (array $spineItem): int => (int) ($spineItem['index'] ?? 0), $readingSpineItems);
            $nonlinearSpineIndexes = array_map(
                static fn (array $spineItem): int => (int) ($spineItem['index'] ?? 0),
                array_values(array_filter(
                    $spineItems,
                    static fn (array $spineItem): bool => ($spineItem['linear'] ?? false) !== true
                ))
            );
            $spineIdrefs = array_map(static fn (array $spineItem): string => (string) ($spineItem['idref'] ?? ''), $spineItems);
            $spineIndex = is_array($firstSpineItem) && is_int($firstSpineItem['index'] ?? null) ? $firstSpineItem['index'] : null;
            $spineLinear = is_array($firstSpineItem) ? (($firstSpineItem['linear'] ?? false) === true) : null;
            $duplicatePageTarget = $target !== '' ? ($duplicatePageTargetsByTarget[$target] ?? null) : null;
            $collisions = $target !== '' ? ($collisionsByTarget[$target] ?? []) : [];
            $itemDiagnostics = array_values(array_filter(
                is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [],
                static fn (mixed $diagnostic): bool => is_array($diagnostic)
            ));
            $pageListIndex = count($pageListItems);
            $pageListItems[] = [
                'index' => $pageListIndex,
                'sourceIndex' => $sourceIndex,
                'navIndex' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                'sectionIndex' => is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : null,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'labelProvenance' => is_array($item['labelProvenance'] ?? null) ? $item['labelProvenance'] : [],
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'external' => $external,
                'unsafe' => (bool) ($item['unsafe'] ?? false),
                'hrefKind' => is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '',
                'hrefScheme' => is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null,
                'exists' => (bool) ($item['exists'] ?? false),
                'pageBreak' => ($item['pageBreakProvenance']['present'] ?? false) === true,
                'pageBreakProvenance' => is_array($item['pageBreakProvenance'] ?? null) ? $item['pageBreakProvenance'] : [],
                'epubTypes' => is_array($item['epubTypes'] ?? null) ? array_values($item['epubTypes']) : [],
                'manifestId' => is_array($manifestItem) && is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                'mediaType' => is_array($manifestItem) && is_string($manifestItem['mediaType'] ?? null) ? $manifestItem['mediaType'] : null,
                'spineIndex' => $spineIndex,
                'spineIndexes' => $spineIndexes,
                'readingSpineIndexes' => $readingSpineIndexes,
                'nonlinearSpineIndexes' => $nonlinearSpineIndexes,
                'spineIdref' => is_array($firstSpineItem) && is_string($firstSpineItem['idref'] ?? null) ? $firstSpineItem['idref'] : null,
                'spineIdrefs' => $spineIdrefs,
                'spineLinear' => $spineLinear,
                'linear' => $spineLinear,
                'inSpineReadingOrder' => $readingSpineItems !== [],
                'duplicatePageTarget' => is_array($duplicatePageTarget),
                'duplicatePageTargetCount' => is_array($duplicatePageTarget) ? (int) ($duplicatePageTarget['count'] ?? 0) : 0,
                'duplicateSpineTarget' => count($spineItems) > 1,
                'duplicateSpineTargetCount' => count($spineItems),
                'collisions' => $collisions,
                'diagnostics' => $itemDiagnostics,
            ];

            if ($collisions !== []) {
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'page-list-target-nav-collision',
                    'navType' => $type,
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                    'collisions' => $collisions,
                    'message' => 'EPUB page-list target also appears in toc or landmarks navigation',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                $collisionTargets[$target] = [
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                    'collisions' => $collisions,
                ];
            }

            if (is_array($duplicatePageTarget)) {
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'duplicate-page-list-target',
                    'navType' => $type,
                    'href' => $href,
                    'target' => (string) ($duplicatePageTarget['target'] ?? $target),
                    'path' => $path,
                    'fragment' => $fragment,
                    'count' => (int) ($duplicatePageTarget['count'] ?? 0),
                    'indexes' => is_array($duplicatePageTarget['indexes'] ?? null) ? $duplicatePageTarget['indexes'] : [],
                    'sourceIndexes' => is_array($duplicatePageTarget['sourceIndexes'] ?? null) ? $duplicatePageTarget['sourceIndexes'] : [],
                    'message' => 'EPUB page-list contains repeated entries for the same package target',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
            }

            if (is_array($manifestItem) && count($spineItems) > 1) {
                ++$duplicateSpineTargetItemCount;
                if (!isset($duplicateSpineTargetsByPath[$path])) {
                    $duplicateSpineTargetsByPath[$path] = [
                        'path' => $path,
                        'count' => count($spineItems),
                        'spineIndexes' => $spineIndexes,
                        'idrefs' => $spineIdrefs,
                    ];
                }
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'page-list-target-duplicate-spine-itemref',
                    'navType' => $type,
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                    'manifestId' => is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                    'count' => count($spineItems),
                    'spineIndexes' => $spineIndexes,
                    'idrefs' => $duplicateSpineTargetsByPath[$path]['idrefs'],
                    'message' => 'EPUB page-list target resolves to multiple spine itemrefs',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
            }

            if (is_array($firstSpineItem)) {
                if ($spineLinear === true) {
                    ++$linearTargetCount;
                } else {
                    ++$nonlinearTargetCount;
                    $diagnostic = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'pageListIndex' => $pageListIndex,
                        'sourceIndex' => $sourceIndex,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'page-list-target-nonlinear-spine-item',
                        'navType' => $type,
                        'href' => $href,
                        'target' => $target,
                        'path' => $path,
                        'fragment' => $fragment,
                        'spineIndex' => $spineIndex,
                        'spineIdref' => is_string($firstSpineItem['idref'] ?? null) ? $firstSpineItem['idref'] : '',
                        'message' => 'EPUB page-list target resolves to a non-linear spine itemref',
                    ];
                    $diagnostics[] = $diagnostic;
                    $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                }

                if ($previousSpineIndex !== null && $spineIndex !== null && $spineIndex < $previousSpineIndex) {
                    $diagnostic = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'pageListIndex' => $pageListIndex,
                        'sourceIndex' => $sourceIndex,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'page-list-reading-order-regression',
                        'navType' => $type,
                        'href' => $href,
                        'target' => $target,
                        'path' => $path,
                        'fragment' => $fragment,
                        'previousSpineIndex' => $previousSpineIndex,
                        'spineIndex' => $spineIndex,
                        'message' => 'EPUB page-list target order moves backward through the package spine',
                    ];
                    $diagnostics[] = $diagnostic;
                    $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                }
                $previousSpineIndex = $spineIndex;
            }

            if ($target !== '' && $fragment !== '') {
                $fragmentTargets[$target][] = [
                    'index' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'fragment' => $fragment,
                ];
            }

            $readingOrder[] = [
                'index' => $pageListIndex,
                'sourceIndex' => $sourceIndex,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'spineIndex' => $spineIndex,
                'spineIndexes' => $spineIndexes,
                'readingSpineIndexes' => $readingSpineIndexes,
                'nonlinearSpineIndexes' => $nonlinearSpineIndexes,
                'spineIdref' => is_array($firstSpineItem) && is_string($firstSpineItem['idref'] ?? null) ? $firstSpineItem['idref'] : null,
                'spineIdrefs' => $spineIdrefs,
                'linear' => $spineLinear,
                'inSpineReadingOrder' => $readingSpineItems !== [],
                'duplicatePageTarget' => is_array($duplicatePageTarget),
                'duplicateSpineTarget' => count($spineItems) > 1,
            ];

            if ($href !== '') {
                ++$targetedItemCount;
                if (isset($seenPageListHrefs[$href])) {
                    $diagnostic = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'pageListIndex' => $pageListIndex,
                        'sourceIndex' => $sourceIndex,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'duplicate-page-list-href',
                        'navType' => $type,
                        'source' => 'href',
                        'href' => $href,
                        'target' => $target,
                        'path' => $path,
                        'fragment' => $fragment,
                        'firstIndex' => is_int($seenPageListHrefs[$href]['item']['index'] ?? null) ? $seenPageListHrefs[$href]['item']['index'] : 0,
                        'firstPageListIndex' => is_int($seenPageListHrefs[$href]['pageListIndex'] ?? null) ? $seenPageListHrefs[$href]['pageListIndex'] : 0,
                        'firstTarget' => is_string($seenPageListHrefs[$href]['item']['target'] ?? null) ? $seenPageListHrefs[$href]['item']['target'] : '',
                    ];
                    $diagnostics[] = $diagnostic;
                    $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                } else {
                    $seenPageListHrefs[$href] = [
                        'item' => $item,
                        'pageListIndex' => $pageListIndex,
                    ];
                }
            } else {
                ++$unresolvedTargetCount;
            }

            $label = is_string($item['label'] ?? null) ? $item['label'] : '';
            if ($label !== '') {
                if (isset($seenPageListLabels[$label])) {
                    $diagnostic = [
                        'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                        'pageListIndex' => $pageListIndex,
                        'sourceIndex' => $sourceIndex,
                        'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                        'type' => 'duplicate-page-list-label',
                        'navType' => $type,
                        'source' => 'label',
                        'label' => $label,
                        'href' => $href,
                        'target' => $target,
                        'firstIndex' => is_int($seenPageListLabels[$label]['item']['index'] ?? null) ? $seenPageListLabels[$label]['item']['index'] : 0,
                        'firstPageListIndex' => is_int($seenPageListLabels[$label]['pageListIndex'] ?? null) ? $seenPageListLabels[$label]['pageListIndex'] : 0,
                        'firstHref' => is_string($seenPageListLabels[$label]['item']['href'] ?? null) ? $seenPageListLabels[$label]['item']['href'] : '',
                    ];
                    $diagnostics[] = $diagnostic;
                    $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                } else {
                    $seenPageListLabels[$label] = [
                        'item' => $item,
                        'pageListIndex' => $pageListIndex,
                    ];
                }
            }

            if ($href === '') {
                continue;
            }
            if ($external) {
                ++$externalTargetCount;
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'external-page-list-reference',
                    'navType' => $type,
                    'href' => $href,
                    'target' => $target,
                    'message' => 'EPUB page-list target points outside the package and was not resolved against the manifest or spine',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                continue;
            }
            if ($path === '' || !is_array($manifestItem)) {
                ++$missingManifestTargetCount;
                $diagnostic = [
                    'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                    'pageListIndex' => $pageListIndex,
                    'sourceIndex' => $sourceIndex,
                    'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                    'type' => 'missing-page-list-manifest-item',
                    'navType' => $type,
                    'href' => $href,
                    'target' => $target,
                    'path' => $path,
                    'message' => 'EPUB page-list target is not present in the OPF manifest',
                ];
                $diagnostics[] = $diagnostic;
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
                continue;
            }

            ++$manifestTargetCount;
            if ($readingSpineItems !== []) {
                ++$spineReadingOrderTargetCount;
                continue;
            }

            ++$outsideSpineTargetCount;
            $reason = is_array($firstSpineItem) && ($firstSpineItem['linear'] ?? false) !== true
                ? 'nonlinear-spine-item'
                : 'not-in-spine';
            $diagnostic = [
                'index' => is_int($item['index'] ?? null) ? $item['index'] : 0,
                'pageListIndex' => $pageListIndex,
                'sourceIndex' => $sourceIndex,
                'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                'type' => 'page-list-target-outside-spine-reading-order',
                'navType' => $type,
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'manifestId' => is_array($manifestItem) && is_string($manifestItem['id'] ?? null) ? $manifestItem['id'] : null,
                'spineIndex' => is_array($firstSpineItem) && is_int($firstSpineItem['index'] ?? null) ? $firstSpineItem['index'] : null,
                'spineIndexes' => $spineIndexes,
                'readingSpineIndexes' => $readingSpineIndexes,
                'spineLinear' => is_array($firstSpineItem) ? (($firstSpineItem['linear'] ?? false) === true) : null,
                'reason' => $reason,
                'message' => 'EPUB page-list target is in the manifest but outside the linear spine reading order',
            ];
            $diagnostics[] = $diagnostic;
            $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
        }

        $repeatedFragmentTargets = [];
        foreach ($fragmentTargets as $target => $targetItems) {
            if (count($targetItems) < 2) {
                continue;
            }

            $indexes = array_map(static fn (array $item): int => (int) $item['index'], $targetItems);
            $labels = array_map(static fn (array $item): string => (string) $item['label'], $targetItems);
            $hrefs = array_map(static fn (array $item): string => (string) $item['href'], $targetItems);
            $repeatedFragmentTargets[] = [
                'target' => $target,
                'path' => (string) ($targetItems[0]['path'] ?? ''),
                'fragment' => (string) ($targetItems[0]['fragment'] ?? ''),
                'indexes' => $indexes,
                'labels' => $labels,
                'hrefs' => $hrefs,
                'items' => $targetItems,
            ];
            $diagnostic = [
                'type' => 'repeated-page-list-fragment-target',
                'target' => $target,
                'path' => (string) ($targetItems[0]['path'] ?? ''),
                'fragment' => (string) ($targetItems[0]['fragment'] ?? ''),
                'indexes' => $indexes,
                'labels' => $labels,
                'hrefs' => $hrefs,
                'message' => 'EPUB page-list contains repeated entries for the same fragment target',
            ];
            $diagnostics[] = $diagnostic;

            foreach ($indexes as $pageListIndex) {
                if (!isset($pageListItems[$pageListIndex])) {
                    continue;
                }
                $pageListItems[$pageListIndex]['diagnostics'][] = $diagnostic;
            }
        }

        $readingOrderByTarget = [];
        $readingOrderBySpineIndex = [];
        foreach ($readingOrder as $summary) {
            $target = is_string($summary['target'] ?? null) ? $summary['target'] : '';
            if ($target !== '') {
                $readingOrderByTarget[$target][] = $summary;
            }
            foreach (is_array($summary['spineIndexes'] ?? null) ? $summary['spineIndexes'] : [] as $spineIndex) {
                $readingOrderBySpineIndex[(int) $spineIndex][] = $summary;
            }
        }
        ksort($readingOrderBySpineIndex);

        return [
            'present' => $pageListItems !== [],
            'itemCount' => count($pageListItems),
            'pageBreakItemCount' => $pageBreakItemCount,
            'targetedItemCount' => $targetedItemCount,
            'manifestTargetCount' => $manifestTargetCount,
            'spineReadingOrderTargetCount' => $spineReadingOrderTargetCount,
            'linearTargetCount' => $linearTargetCount,
            'nonlinearTargetCount' => $nonlinearTargetCount,
            'missingManifestTargetCount' => $missingManifestTargetCount,
            'outsideSpineTargetCount' => $outsideSpineTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'unresolvedTargetCount' => $unresolvedTargetCount,
            'collisionTargetCount' => count($collisionTargets),
            'collisionTargets' => array_values($collisionTargets),
            'duplicatePageTargetCount' => count($duplicatePageTargets),
            'duplicatePageTargetItemCount' => $duplicatePageTargetItemCount,
            'duplicatePageTargets' => $duplicatePageTargets,
            'duplicateSpineTargetCount' => count($duplicateSpineTargetsByPath),
            'duplicateSpineTargetItemCount' => $duplicateSpineTargetItemCount,
            'duplicateSpineTargets' => array_values($duplicateSpineTargetsByPath),
            'repeatedFragmentTargetCount' => count($repeatedFragmentTargets),
            'repeatedFragmentTargets' => $repeatedFragmentTargets,
            'readingOrder' => $readingOrder,
            'readingOrderByTarget' => $readingOrderByTarget,
            'readingOrderBySpineIndex' => $readingOrderBySpineIndex,
            'items' => $pageListItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function navNormalizedCollisionReport(array $flat, string $navPath): array
    {
        $sections = [];
        $sectionKeys = [];
        $sectionItemIndexes = [];
        $sectionTargets = [];
        $crossSectionTargets = [];
        $diagnostics = [];
        $normalizedCollisionDiagnostics = [];
        $itemCount = 0;
        $targetedItemCount = 0;
        $localTargetCount = 0;
        $externalTargetCount = 0;
        $fragmentTargetCount = 0;
        $fragmentOnlyTargetCount = 0;
        $unsafeTargetCount = 0;
        $packageRootEscapeTargetCount = 0;
        $normalizedCollisionItemCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc' && $sectionType !== 'landmarks' && $sectionType !== 'page-list') {
                continue;
            }

            $sectionIndex = is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : -1;
            $sectionKey = $sectionIndex . ':' . $sectionType;
            if (!isset($sectionKeys[$sectionKey])) {
                $sectionKeys[$sectionKey] = count($sections);
                $sectionItemIndexes[$sectionKey] = 0;
                $sections[] = [
                    'index' => $sectionIndex,
                    'sectionIndex' => $sectionIndex,
                    'id' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                    'sectionLabel' => is_string($item['sectionLabel'] ?? null) ? $item['sectionLabel'] : '',
                    'type' => $sectionType,
                    'itemCount' => 0,
                    'targetedItemCount' => 0,
                    'localTargetCount' => 0,
                    'externalTargetCount' => 0,
                    'fragmentTargetCount' => 0,
                    'fragmentOnlyTargetCount' => 0,
                    'unsafeTargetCount' => 0,
                    'packageRootEscapeTargetCount' => 0,
                    'normalizedCollisionGroupCount' => 0,
                    'normalizedCollisionItemCount' => 0,
                    'diagnosticCount' => 0,
                    'diagnostics' => [],
                ];
            }

            $sectionOffset = $sectionKeys[$sectionKey];
            $itemIndex = $sectionItemIndexes[$sectionKey]++;
            ++$itemCount;
            ++$sections[$sectionOffset]['itemCount'];

            $href = is_string($item['href'] ?? null) ? trim($item['href']) : '';
            if ($href === '') {
                continue;
            }

            ++$targetedItemCount;
            ++$sections[$sectionOffset]['targetedItemCount'];
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $external = (bool) ($item['external'] ?? false);
            $unsafe = (bool) ($item['unsafe'] ?? false);
            $hrefKind = is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '';
            $normalization = is_array($item['normalization'] ?? null) ? $item['normalization'] : [];
            $packageRootEscape = ($normalization['packageRootEscape'] ?? false) === true;
            $suffix = $this->hrefSuffix($href);
            $fragmentOnly = $hrefKind === 'same-document-fragment' || ($path === '' && str_starts_with($href, '#'));
            $baseDiagnostic = [
                'sectionIndex' => $sectionIndex,
                'sectionType' => $sectionType,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'itemIndex' => $itemIndex,
                'sourceIndex' => $sourceIndex,
                'depth' => is_int($item['depth'] ?? null) ? $item['depth'] : 0,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'href' => $href,
            ];

            if ($external) {
                ++$externalTargetCount;
                ++$sections[$sectionOffset]['externalTargetCount'];
                if ($packageRootEscape) {
                    ++$packageRootEscapeTargetCount;
                    ++$sections[$sectionOffset]['packageRootEscapeTargetCount'];
                }
                $diagnostic = $baseDiagnostic + [
                    'type' => 'external-nav-href-target',
                    'target' => $target,
                    'hrefKind' => $hrefKind,
                    'message' => 'EPUB navigation target resolves outside the package',
                ];
                $diagnostics[] = $diagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
                continue;
            }

            if ($unsafe) {
                ++$unsafeTargetCount;
                ++$sections[$sectionOffset]['unsafeTargetCount'];
                $diagnostic = $baseDiagnostic + [
                    'type' => 'unsafe-nav-href-target',
                    'target' => $target,
                    'hrefKind' => $hrefKind,
                    'message' => 'EPUB navigation target uses an unsafe href scheme',
                ];
                $diagnostics[] = $diagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
                continue;
            }

            $targetPath = $fragmentOnly ? $navPath : $path;
            if ($targetPath !== '') {
                ++$localTargetCount;
                ++$sections[$sectionOffset]['localTargetCount'];
            }

            if ($suffix['hasFragment']) {
                ++$fragmentTargetCount;
                ++$sections[$sectionOffset]['fragmentTargetCount'];
                $diagnostic = $baseDiagnostic + [
                    'type' => $fragmentOnly ? 'fragment-only-nav-href-target' : 'fragment-nav-href-target',
                    'target' => $fragmentOnly
                        ? $navPath . '#' . $fragment
                        : ($target !== '' ? $target : $targetPath . '#' . $fragment),
                    'path' => $targetPath,
                    'fragment' => $fragment,
                    'message' => $fragmentOnly
                        ? 'EPUB navigation target resolves to a fragment in the navigation document'
                        : 'EPUB navigation target includes a fragment component',
                ];
                $diagnostics[] = $diagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
            }

            if ($fragmentOnly) {
                ++$fragmentOnlyTargetCount;
                ++$sections[$sectionOffset]['fragmentOnlyTargetCount'];
            }

            $normalizedTarget = $this->normalizedNavTarget($targetPath, $suffix['hasFragment'], $fragment);
            if ($normalizedTarget === '') {
                continue;
            }

            $targetDiagnostic = $baseDiagnostic + [
                'target' => $fragmentOnly
                    ? $navPath . ($suffix['hasFragment'] ? '#' . $fragment : '')
                    : ($target !== '' ? $target : $targetPath . ($suffix['hasFragment'] ? '#' . $fragment : '')),
                'normalizedTarget' => $normalizedTarget,
                'path' => $targetPath,
                'fragment' => $fragment,
                'fragmentOnly' => $fragmentOnly,
            ];
            $sectionTargets[$sectionKey][$normalizedTarget][] = $targetDiagnostic;
            $crossSectionTargets[$normalizedTarget][] = $targetDiagnostic;
        }

        foreach ($sectionTargets as $sectionKey => $targets) {
            $sectionOffset = $sectionKeys[$sectionKey] ?? null;
            if (!is_int($sectionOffset)) {
                continue;
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

                $matches = $this->sortNavTargetMatches($matches);
                $diagnostic = [
                    'type' => 'normalized-nav-target-collision',
                    'sectionIndex' => (int) ($matches[0]['sectionIndex'] ?? 0),
                    'sectionType' => (string) ($matches[0]['sectionType'] ?? ''),
                    'sectionId' => is_string($matches[0]['sectionId'] ?? null) ? $matches[0]['sectionId'] : null,
                    'normalizedTarget' => $normalizedTarget,
                    'itemCount' => count($matches),
                    'rawHrefCount' => count($rawHrefs),
                    'itemIndexes' => array_map(static fn (array $match): int => (int) ($match['itemIndex'] ?? 0), $matches),
                    'sourceIndexes' => array_map(static fn (array $match): int => (int) ($match['sourceIndex'] ?? 0), $matches),
                    'hrefs' => $rawHrefs,
                    'targets' => $this->uniqueNavMatchValues($matches, 'target'),
                    'labels' => array_values(array_filter(
                        $this->uniqueNavMatchValues($matches, 'label'),
                        static fn (string $label): bool => $label !== ''
                    )),
                    'collisionKinds' => $this->navCollisionKinds($matches),
                    'message' => 'EPUB navigation section contains distinct hrefs that normalize to the same target',
                ];
                $sectionCollisionDiagnostics[] = $diagnostic;
            }

            $sectionCollisionDiagnostics = $this->sortNavCollisionDiagnostics($sectionCollisionDiagnostics);
            foreach ($sectionCollisionDiagnostics as $diagnostic) {
                $normalizedCollisionItemCount += (int) ($diagnostic['itemCount'] ?? 0);
                $normalizedCollisionDiagnostics[] = $diagnostic;
                $diagnostics[] = $diagnostic;
                $sections[$sectionOffset]['diagnostics'][] = $diagnostic;
                ++$sections[$sectionOffset]['diagnosticCount'];
            }

            $sections[$sectionOffset]['normalizedCollisionGroupCount'] = count($sectionCollisionDiagnostics);
            $sections[$sectionOffset]['normalizedCollisionItemCount'] = array_sum(array_map(
                static fn (array $diagnostic): int => (int) ($diagnostic['itemCount'] ?? 0),
                $sectionCollisionDiagnostics
            ));
        }

        $normalizedCollisionDiagnostics = $this->sortNavCollisionDiagnostics($normalizedCollisionDiagnostics);
        $crossSectionCollisionDiagnostics = $this->crossSectionNavCollisionDiagnostics($crossSectionTargets);
        $crossSectionCollisionItemCount = array_sum(array_map(
            static fn (array $diagnostic): int => (int) ($diagnostic['itemCount'] ?? 0),
            $crossSectionCollisionDiagnostics
        ));
        $diagnostics = $this->sortNavDiagnostics(array_merge($diagnostics, $crossSectionCollisionDiagnostics));

        return [
            'present' => $flat !== [],
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
            'sections' => $sections,
            'diagnosticTypes' => $this->diagnosticTypeCounts($diagnostics),
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
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
                $sectionId = is_string($match['sectionId'] ?? null) ? $match['sectionId'] : null;
                $sectionTypes[$sectionType] = $sectionType;
                $sectionIndexes[(string) $sectionIndex] = $sectionIndex;
                if ($sectionId !== null && $sectionId !== '') {
                    $sectionIds[$sectionId] = $sectionId;
                }
                $itemRefs[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionType' => $sectionType,
                    'sectionId' => $sectionId,
                    'itemIndex' => (int) ($match['itemIndex'] ?? 0),
                    'sourceIndex' => (int) ($match['sourceIndex'] ?? 0),
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
     * @param array<string, mixed> $navReport
     * @param array<string, mixed> $pageListReport
     * @return array<string, mixed>
     */
    private function navReportWithPageListTargets(array $navReport, array $pageListReport): array
    {
        $pageListItems = is_array($pageListReport['items'] ?? null) ? $pageListReport['items'] : [];
        $targetsByIndex = [];
        $targetsBySourceIndex = [];
        foreach ($pageListItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $summary = $this->pageListTargetSummary($item);
            $targetsByIndex[(int) ($item['index'] ?? count($targetsByIndex))] = $summary;
            if (is_int($item['sourceIndex'] ?? null)) {
                $targetsBySourceIndex[(int) $item['sourceIndex']] = $summary;
            }
        }

        if ($targetsByIndex === [] && $targetsBySourceIndex === []) {
            return $navReport;
        }

        foreach (['normalizedCollisionDiagnostics', 'crossSectionCollisionDiagnostics', 'diagnostics'] as $key) {
            if (!is_array($navReport[$key] ?? null)) {
                continue;
            }

            $navReport[$key] = $this->navDiagnosticsWithPageListTargets(
                $navReport[$key],
                $targetsByIndex,
                $targetsBySourceIndex
            );
        }

        if (is_array($navReport['sections'] ?? null)) {
            foreach ($navReport['sections'] as $sectionIndex => $section) {
                if (!is_array($section) || !is_array($section['diagnostics'] ?? null)) {
                    continue;
                }

                $navReport['sections'][$sectionIndex]['diagnostics'] = $this->navDiagnosticsWithPageListTargets(
                    $section['diagnostics'],
                    $targetsByIndex,
                    $targetsBySourceIndex
                );
            }
        }

        return $navReport;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function pageListTargetSummary(array $item): array
    {
        $path = (string) ($item['path'] ?? '');
        $fragment = (string) ($item['fragment'] ?? '');
        $diagnostics = is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [];

        return [
            'index' => (int) ($item['index'] ?? 0),
            'sourceIndex' => (int) ($item['sourceIndex'] ?? 0),
            'navIndex' => (int) ($item['navIndex'] ?? 0),
            'label' => (string) ($item['label'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'target' => $path . ($fragment === '' ? '' : '#' . $fragment),
            'path' => $path,
            'fragment' => $fragment,
            'manifestId' => is_string($item['manifestId'] ?? null) ? $item['manifestId'] : '',
            'spineIndex' => $item['spineIndex'] ?? null,
            'spineIndexes' => $this->integerList($item['spineIndexes'] ?? []),
            'readingSpineIndexes' => $this->integerList($item['readingSpineIndexes'] ?? []),
            'nonlinearSpineIndexes' => $this->integerList($item['nonlinearSpineIndexes'] ?? []),
            'spineIdrefs' => $this->stringList($item['spineIdrefs'] ?? []),
            'inSpineReadingOrder' => ($item['inSpineReadingOrder'] ?? false) === true,
            'duplicatePageTarget' => ($item['duplicatePageTarget'] ?? false) === true,
            'duplicatePageTargetCount' => (int) ($item['duplicatePageTargetCount'] ?? 0),
            'duplicateSpineTarget' => ($item['duplicateSpineTarget'] ?? false) === true,
            'duplicateSpineTargetCount' => (int) ($item['duplicateSpineTargetCount'] ?? 0),
            'diagnosticTypes' => array_values(array_filter(
                array_map(
                    static fn (mixed $diagnostic): string => is_array($diagnostic) ? (string) ($diagnostic['type'] ?? '') : '',
                    $diagnostics
                ),
                static fn (string $type): bool => $type !== ''
            )),
        ];
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private function integerList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): int => (int) $item, $value));
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @param array<int, array<string, mixed>> $targetsByIndex
     * @param array<int, array<string, mixed>> $targetsBySourceIndex
     * @return list<array<string, mixed>>
     */
    private function navDiagnosticsWithPageListTargets(array $diagnostics, array $targetsByIndex, array $targetsBySourceIndex): array
    {
        $enriched = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $enriched[] = $this->navDiagnosticWithPageListTargets($diagnostic, $targetsByIndex, $targetsBySourceIndex);
        }

        return $enriched;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @param array<int, array<string, mixed>> $targetsByIndex
     * @param array<int, array<string, mixed>> $targetsBySourceIndex
     * @return array<string, mixed>
     */
    private function navDiagnosticWithPageListTargets(array $diagnostic, array $targetsByIndex, array $targetsBySourceIndex): array
    {
        $pageListIndexes = [];
        $pageListSourceIndexes = [];
        if (($diagnostic['sectionType'] ?? '') === 'page-list') {
            foreach ($this->navDiagnosticItemIndexes($diagnostic) as $index) {
                $pageListIndexes[] = $index;
            }
            foreach ($this->integerList($diagnostic['sourceIndexes'] ?? []) as $sourceIndex) {
                $pageListSourceIndexes[] = $sourceIndex;
            }
            if (is_int($diagnostic['sourceIndex'] ?? null)) {
                $pageListSourceIndexes[] = (int) $diagnostic['sourceIndex'];
            }
        }

        if (is_array($diagnostic['itemRefs'] ?? null)) {
            foreach ($diagnostic['itemRefs'] as $refIndex => $ref) {
                if (!is_array($ref) || ($ref['sectionType'] ?? '') !== 'page-list') {
                    continue;
                }

                $pageListIndex = (int) ($ref['itemIndex'] ?? -1);
                $pageListSourceIndex = (int) ($ref['sourceIndex'] ?? -1);
                $pageListIndexes[] = $pageListIndex;
                $pageListSourceIndexes[] = $pageListSourceIndex;
                $target = $targetsBySourceIndex[$pageListSourceIndex] ?? ($targetsByIndex[$pageListIndex] ?? null);
                if (is_array($target)) {
                    $diagnostic['itemRefs'][$refIndex]['pageListTarget'] = $target;
                }
            }
        }

        $pageListIndexes = $this->uniqueIntegers($pageListIndexes);
        $pageListSourceIndexes = $this->uniqueIntegers($pageListSourceIndexes);
        $pageListTargets = [];
        foreach ($pageListSourceIndexes as $sourceIndex) {
            if (isset($targetsBySourceIndex[$sourceIndex])) {
                $pageListTargets[] = $targetsBySourceIndex[$sourceIndex];
            }
        }
        foreach ($pageListIndexes as $index) {
            if (isset($targetsByIndex[$index]) && !in_array($targetsByIndex[$index], $pageListTargets, true)) {
                $pageListTargets[] = $targetsByIndex[$index];
            }
        }

        if ($pageListTargets === []) {
            return $diagnostic;
        }

        $diagnostic['pageListTargetCount'] = count($pageListTargets);
        $diagnostic['pageListItemIndexes'] = array_column($pageListTargets, 'index');
        $diagnostic['pageListSpineIndexes'] = $this->uniqueIntegers($this->flattenIntegerField($pageListTargets, 'spineIndexes'));
        $diagnostic['pageListReadingSpineIndexes'] = $this->uniqueIntegers($this->flattenIntegerField($pageListTargets, 'readingSpineIndexes'));
        $diagnostic['pageListDuplicatePageTargetCount'] = count(array_filter(
            $pageListTargets,
            static fn (array $target): bool => ($target['duplicatePageTarget'] ?? false) === true
        ));
        $diagnostic['pageListDuplicateSpineTargetCount'] = count(array_filter(
            $pageListTargets,
            static fn (array $target): bool => ($target['duplicateSpineTarget'] ?? false) === true
        ));
        $diagnostic['pageListTargets'] = $pageListTargets;

        return $diagnostic;
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @return list<int>
     */
    private function navDiagnosticItemIndexes(array $diagnostic): array
    {
        if (is_array($diagnostic['itemIndexes'] ?? null)) {
            return $this->integerList($diagnostic['itemIndexes']);
        }
        if (isset($diagnostic['itemIndex'])) {
            return [(int) $diagnostic['itemIndex']];
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<int>
     */
    private function flattenIntegerField(array $items, string $key): array
    {
        $values = [];
        foreach ($items as $item) {
            foreach ($this->integerList($item[$key] ?? []) as $value) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private function uniqueIntegers(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            $value = (int) $value;
            if (!in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
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
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function navFragmentTargetReport(array $flat, string $root): array
    {
        $items = [];
        $diagnostics = [];
        $sectionTypeCounts = [];
        $targetIndexes = [];
        $targetedItemCount = 0;
        $fragmentItemCount = 0;
        $fragmentlessTargetCount = 0;
        $resolvedFragmentCount = 0;
        $missingFragmentCount = 0;
        $duplicateFragmentCount = 0;
        $missingDocumentCount = 0;
        $externalTargetCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc' && $sectionType !== 'landmarks' && $sectionType !== 'page-list') {
                continue;
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            if ($href === '') {
                continue;
            }

            ++$targetedItemCount;
            $sectionTypeCounts[$sectionType] = ($sectionTypeCounts[$sectionType] ?? 0) + 1;

            $targetIndex = count($items);
            $path = is_string($item['path'] ?? null) ? $item['path'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $fragment = is_string($item['fragment'] ?? null) ? $item['fragment'] : '';
            $decodedFragment = $fragment === '' ? '' : rawurldecode($fragment);
            $external = (bool) ($item['external'] ?? false);
            $exists = (bool) ($item['exists'] ?? false);
            $fragmentState = $fragment === '' ? 'document' : 'unresolved';
            $fragmentMatchCount = 0;
            $targetIdCount = 0;
            $targetUniqueIdCount = 0;
            $itemDiagnostics = [];

            if ($external) {
                ++$externalTargetCount;
                $fragmentState = 'external';
            } elseif ($path !== '' && !$exists) {
                ++$missingDocumentCount;
                $fragmentState = 'missing-document';
                if ($fragment !== '') {
                    ++$fragmentItemCount;
                    $itemDiagnostics[] = [
                        'type' => 'missing-nav-fragment-document',
                        'path' => $path,
                        'fragment' => $fragment,
                        'message' => 'EPUB nav fragment target points at a missing package document',
                    ];
                }
            } elseif ($fragment === '') {
                ++$fragmentlessTargetCount;
            } else {
                ++$fragmentItemCount;
                if (!isset($targetIndexes[$path])) {
                    $targetIndexes[$path] = $this->packageXhtmlIdIndex($root, $path);
                }

                $targetIndexReport = $targetIndexes[$path];
                $targetIdCount = is_int($targetIndexReport['idCount'] ?? null) ? $targetIndexReport['idCount'] : 0;
                $targetUniqueIdCount = is_int($targetIndexReport['uniqueIdCount'] ?? null) ? $targetIndexReport['uniqueIdCount'] : 0;
                $idCounts = is_array($targetIndexReport['idCounts'] ?? null) ? $targetIndexReport['idCounts'] : [];
                $parseError = is_string($targetIndexReport['parseError'] ?? null) ? $targetIndexReport['parseError'] : null;

                if ($parseError !== null) {
                    $fragmentState = 'unreadable-document';
                    $itemDiagnostics[] = [
                        'type' => 'unreadable-nav-fragment-document',
                        'path' => $path,
                        'fragment' => $fragment,
                        'message' => 'EPUB nav fragment target document could not be parsed for id lookup',
                    ];
                } else {
                    $fragmentMatchCount = is_int($idCounts[$fragment] ?? null) ? $idCounts[$fragment] : 0;
                    if ($fragmentMatchCount === 0 && $decodedFragment !== $fragment) {
                        $fragmentMatchCount = is_int($idCounts[$decodedFragment] ?? null) ? $idCounts[$decodedFragment] : 0;
                    }

                    if ($fragmentMatchCount === 0) {
                        ++$missingFragmentCount;
                        $fragmentState = 'missing-fragment';
                        $itemDiagnostics[] = [
                            'type' => 'missing-nav-fragment-target',
                            'path' => $path,
                            'fragment' => $fragment,
                            'message' => 'EPUB nav href fragment does not match a known XHTML id',
                        ];
                    } elseif ($fragmentMatchCount > 1) {
                        ++$duplicateFragmentCount;
                        $fragmentState = 'duplicate-fragment';
                        $itemDiagnostics[] = [
                            'type' => 'duplicate-nav-fragment-target',
                            'path' => $path,
                            'fragment' => $fragment,
                            'matchCount' => $fragmentMatchCount,
                            'message' => 'EPUB nav href fragment matches multiple XHTML ids in the target document',
                        ];
                    } else {
                        ++$resolvedFragmentCount;
                        $fragmentState = 'resolved-fragment';
                    }
                }
            }

            $reportedItem = [
                'index' => $targetIndex,
                'sourceIndex' => $sourceIndex,
                'sectionType' => $sectionType,
                'sectionIndex' => is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : null,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'labelProvenance' => is_array($item['labelProvenance'] ?? null) ? $item['labelProvenance'] : [],
                'href' => $href,
                'target' => $target,
                'path' => $path,
                'fragment' => $fragment,
                'decodedFragment' => $decodedFragment,
                'external' => $external,
                'unsafe' => (bool) ($item['unsafe'] ?? false),
                'hrefKind' => is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '',
                'hrefScheme' => is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null,
                'exists' => $exists,
                'fragmentState' => $fragmentState,
                'fragmentMatchCount' => $fragmentMatchCount,
                'targetIdCount' => $targetIdCount,
                'targetUniqueIdCount' => $targetUniqueIdCount,
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $targetIndex,
                    'sourceIndex' => $sourceIndex,
                    'sectionType' => $sectionType,
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
            'targetedItemCount' => $targetedItemCount,
            'fragmentItemCount' => $fragmentItemCount,
            'fragmentlessTargetCount' => $fragmentlessTargetCount,
            'resolvedFragmentCount' => $resolvedFragmentCount,
            'missingFragmentCount' => $missingFragmentCount,
            'duplicateFragmentCount' => $duplicateFragmentCount,
            'missingDocumentCount' => $missingDocumentCount,
            'externalTargetCount' => $externalTargetCount,
            'sectionTypeCounts' => $sectionTypeCounts,
            'diagnosticCount' => count($diagnostics),
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $flat
     * @return array<string, mixed>
     */
    private function tocNavigationReport(array $flat): array
    {
        $relationTargets = [
            'landmarks' => [],
            'page-list' => [],
        ];
        foreach ($flat as $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'landmarks' && $sectionType !== 'page-list') {
                continue;
            }

            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            if ($target !== '') {
                $relationTargets[$sectionType][$target] = true;
            }
        }

        $items = [];
        $diagnostics = [];
        $targetBuckets = [];
        $targetedItemCount = 0;
        $accessibilityLabelCount = 0;
        $missingLabelCount = 0;
        $missingTargetCount = 0;
        $roleConflictCount = 0;
        $landmarkRelationCount = 0;
        $pageListRelationCount = 0;

        foreach ($flat as $sourceIndex => $item) {
            $sectionType = is_string($item['sectionType'] ?? null)
                ? $item['sectionType']
                : (is_string($item['type'] ?? null) ? $item['type'] : '');
            if ($sectionType !== 'toc') {
                continue;
            }

            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $target = is_string($item['target'] ?? null) ? $item['target'] : '';
            $label = is_string($item['label'] ?? null) ? $item['label'] : '';
            $accessibilityLabel = is_string($item['accessibilityLabel'] ?? null) ? $item['accessibilityLabel'] : $label;
            $semanticTypes = is_array($item['semanticTypes'] ?? null) ? array_values($item['semanticTypes']) : [];
            $itemDiagnostics = [];
            $tocIndex = count($items);

            if ($href === '') {
                ++$missingTargetCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-toc-target',
                    'message' => 'EPUB toc nav item has no href target for reading-order relation checks',
                ];
            } else {
                ++$targetedItemCount;
                if ($target !== '') {
                    $targetBuckets[$target][] = [
                        'index' => $tocIndex,
                        'sourceIndex' => $sourceIndex,
                        'label' => $label,
                        'href' => $href,
                    ];
                }
            }

            if ($accessibilityLabel === '') {
                ++$missingLabelCount;
                $itemDiagnostics[] = [
                    'type' => 'missing-toc-accessibility-label',
                    'message' => 'EPUB toc nav item has no text, aria-label, or title for accessible review handoff',
                ];
            } else {
                ++$accessibilityLabelCount;
            }

            $conflictingTypes = array_values(array_intersect($semanticTypes, ['landmarks', 'page-list']));
            if ($conflictingTypes !== []) {
                ++$roleConflictCount;
                $itemDiagnostics[] = [
                    'type' => 'toc-nav-role-conflict',
                    'semanticTypes' => $conflictingTypes,
                    'message' => 'EPUB toc nav item carries a primary nav role from another section type',
                ];
            }

            $relationSections = [];
            if ($target !== '' && isset($relationTargets['landmarks'][$target])) {
                $relationSections[] = 'landmarks';
                ++$landmarkRelationCount;
            }
            if ($target !== '' && isset($relationTargets['page-list'][$target])) {
                $relationSections[] = 'page-list';
                ++$pageListRelationCount;
            }

            $reportedItem = [
                'index' => $tocIndex,
                'sourceIndex' => $sourceIndex,
                'sectionIndex' => is_int($item['sectionIndex'] ?? null) ? $item['sectionIndex'] : null,
                'sectionId' => is_string($item['sectionId'] ?? null) ? $item['sectionId'] : null,
                'label' => $label,
                'labelProvenance' => is_array($item['labelProvenance'] ?? null) ? $item['labelProvenance'] : [],
                'accessibilityLabel' => $accessibilityLabel,
                'accessibilityLabelSource' => is_string($item['accessibilityLabelSource'] ?? null) ? $item['accessibilityLabelSource'] : null,
                'href' => $href,
                'target' => $target,
                'path' => is_string($item['path'] ?? null) ? $item['path'] : '',
                'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : '',
                'external' => (bool) ($item['external'] ?? false),
                'unsafe' => (bool) ($item['unsafe'] ?? false),
                'hrefKind' => is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '',
                'hrefScheme' => is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null,
                'exists' => (bool) ($item['exists'] ?? false),
                'semanticType' => is_string($item['semanticType'] ?? null) ? $item['semanticType'] : null,
                'semanticTypes' => $semanticTypes,
                'itemTypes' => is_array($item['itemTypes'] ?? null) ? array_values($item['itemTypes']) : [],
                'labelTypes' => is_array($item['labelTypes'] ?? null) ? array_values($item['labelTypes']) : [],
                'typeSource' => is_string($item['typeSource'] ?? null) ? $item['typeSource'] : null,
                'relationSections' => $relationSections,
                'duplicateTargetCount' => 0,
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $tocIndex,
                    'sourceIndex' => $sourceIndex,
                    'sectionIndex' => $reportedItem['sectionIndex'],
                    'sectionId' => $reportedItem['sectionId'],
                    'label' => $label,
                    'href' => $href,
                    'target' => $target,
                ] + $diagnostic;
            }

            $items[] = $reportedItem;
        }

        $duplicateTargetCount = 0;
        foreach ($targetBuckets as $target => $matches) {
            if (count($matches) <= 1) {
                continue;
            }

            ++$duplicateTargetCount;
            $diagnostic = [
                'type' => 'duplicate-toc-target',
                'target' => $target,
                'itemCount' => count($matches),
                'items' => $matches,
                'message' => 'EPUB toc nav target is reused by multiple entries',
            ];
            $diagnostics[] = $diagnostic;

            foreach ($matches as $match) {
                $index = is_int($match['index'] ?? null) ? $match['index'] : -1;
                if (!isset($items[$index])) {
                    continue;
                }
                $items[$index]['duplicateTargetCount'] = count($matches);
                $items[$index]['diagnostics'][] = [
                    'type' => 'duplicate-toc-target',
                    'target' => $target,
                    'itemCount' => count($matches),
                    'message' => 'EPUB toc nav target is reused by multiple entries',
                ];
            }
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'targetedItemCount' => $targetedItemCount,
            'accessibilityLabelCount' => $accessibilityLabelCount,
            'missingLabelCount' => $missingLabelCount,
            'missingTargetCount' => $missingTargetCount,
            'roleConflictCount' => $roleConflictCount,
            'duplicateTargetCount' => $duplicateTargetCount,
            'landmarkRelationCount' => $landmarkRelationCount,
            'pageListRelationCount' => $pageListRelationCount,
            'diagnosticCount' => count($diagnostics),
            'items' => $items,
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
                'unsafe' => (bool) ($item['unsafe'] ?? false),
                'hrefKind' => is_string($item['hrefKind'] ?? null) ? $item['hrefKind'] : '',
                'hrefScheme' => is_string($item['hrefScheme'] ?? null) ? $item['hrefScheme'] : null,
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

        if ($name === 'table') {
            return [$this->tableNode($element, $baseDir)];
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

    private function tableNode(\DOMElement $element, string $baseDir): AstNode
    {
        $caption = $this->firstDirectChild($element, 'caption');
        $captionInlines = $caption instanceof \DOMElement ? $this->inlineNodesFromChildren($caption, $baseDir) : [];
        $captionText = $captionInlines === [] ? '' : $this->plainInlineText($captionInlines);
        $children = [];

        $headRows = $this->tableSectionRows($element, 'thead', $baseDir);
        if ($headRows !== []) {
            $head = $this->firstDirectChild($element, 'thead');
            $children[] = new AstNode('table_head', [
                'htmlAttributes' => $head instanceof \DOMElement ? $this->htmlAttributes($head) : [],
            ], $headRows);
        }

        $bodySections = [];
        $directRows = $this->tableRowsFromParent($element, $baseDir);
        if ($directRows !== []) {
            $bodySections[] = new AstNode('table_body', [], $directRows);
        }

        foreach ($this->directChildElements($element, 'tbody') as $bodyElement) {
            $rows = $this->tableRowsFromParent($bodyElement, $baseDir);
            if ($rows === []) {
                continue;
            }

            $bodySections[] = new AstNode('table_body', [
                'htmlAttributes' => $this->htmlAttributes($bodyElement),
            ], $rows);
        }
        array_push($children, ...$bodySections);

        $footRows = $this->tableSectionRows($element, 'tfoot', $baseDir);
        if ($footRows !== []) {
            $foot = $this->firstDirectChild($element, 'tfoot');
            $children[] = new AstNode('table_foot', [
                'htmlAttributes' => $foot instanceof \DOMElement ? $this->htmlAttributes($foot) : [],
            ], $footRows);
        }

        if ($children === []) {
            $children[] = new AstNode('table_body');
        }

        $attrs = [
            'caption' => $captionText,
            'captionInlines' => $captionInlines,
            'htmlAttributes' => $this->htmlAttributes($element),
            'sourceFormat' => 'epub-xhtml',
        ];

        if ($caption instanceof \DOMElement) {
            $attrs['captionSource'] = [
                'source' => 'epub-xhtml-caption',
                'captionSide' => $this->tableCaptionSide($caption),
                'sourceAttributes' => [
                    'htmlAttributes' => $this->htmlAttributes($caption),
                    'classes' => $this->classList($caption),
                ],
            ];
        }

        return TableGeometry::withReviewPacket(new AstNode('table', $attrs, $children), [
            'idPrefix' => trim($element->getAttribute('id')) === '' ? 'epub-table' : trim($element->getAttribute('id')),
        ]);
    }

    /**
     * @return list<AstNode>
     */
    private function tableSectionRows(\DOMElement $table, string $sectionName, string $baseDir): array
    {
        $rows = [];
        foreach ($this->directChildElements($table, $sectionName) as $section) {
            array_push($rows, ...$this->tableRowsFromParent($section, $baseDir));
        }

        return $rows;
    }

    /**
     * @return list<AstNode>
     */
    private function tableRowsFromParent(\DOMElement $parent, string $baseDir): array
    {
        $rows = [];
        foreach ($this->directChildElements($parent, 'tr') as $rowElement) {
            $cells = [];
            foreach ($rowElement->childNodes as $cellElement) {
                if (!$cellElement instanceof \DOMElement || ($cellElement->localName !== 'td' && $cellElement->localName !== 'th')) {
                    continue;
                }

                $cells[] = $this->tableCellNode($cellElement, $baseDir);
            }

            if ($cells === []) {
                continue;
            }

            $rows[] = new AstNode('table_row', [
                'htmlAttributes' => $this->htmlAttributes($rowElement),
            ], $cells);
        }

        return $rows;
    }

    private function tableCellNode(\DOMElement $cellElement, string $baseDir): AstNode
    {
        $blocks = $this->tableCellBlocksFromElement($cellElement, $baseDir);
        $attrs = [
            'text' => $this->plainBlockText($blocks),
            'header' => $cellElement->localName === 'th',
            'htmlAttributes' => $this->htmlAttributes($cellElement),
            'colspan' => $this->tableSpanAttribute($cellElement, 'colspan'),
            'rowspan' => $this->tableSpanAttribute($cellElement, 'rowspan'),
        ];

        $align = strtolower(trim($cellElement->getAttribute('align')));
        if (in_array($align, ['left', 'right', 'center'], true)) {
            $attrs['align'] = $align;
        }

        $valign = strtolower(trim($cellElement->getAttribute('valign')));
        if (in_array($valign, ['baseline', 'top', 'middle', 'bottom'], true)) {
            $attrs['valign'] = $valign;
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    private function tableSpanAttribute(\DOMElement $element, string $attribute): int
    {
        $value = trim($element->getAttribute($attribute));
        if ($value === '') {
            return 1;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            return 1;
        }

        $span = (int) $value;
        if ($attribute === 'rowspan' && $span === 0) {
            return 0;
        }

        return max(1, min(1000, $span));
    }

    private function tableCaptionSide(\DOMElement $caption): string
    {
        $style = strtolower($caption->getAttribute('style'));
        if (preg_match('/(?:^|;)\s*caption-side\s*:\s*(top|bottom)\b/', $style, $match) === 1) {
            return $match[1];
        }

        return 'top';
    }

    /**
     * @return list<AstNode>
     */
    private function tableCellBlocksFromElement(\DOMElement $element, string $baseDir): array
    {
        $blocks = [];
        $inlines = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isBlockContentElement($child)) {
                $this->flushInlinePlain($inlines, $blocks);
                array_push($blocks, ...$this->blockNodesFromElement($child, $baseDir));
                continue;
            }

            array_push($inlines, ...$this->inlineNodesFromNode($child, $baseDir));
        }

        $this->flushInlinePlain($inlines, $blocks);

        return $blocks;
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $blocks
     */
    private function flushInlinePlain(array &$inlines, array &$blocks): void
    {
        if ($inlines === []) {
            return;
        }

        $blocks[] = new AstNode('plain', [
            'text' => $this->plainInlineText($inlines),
        ], $inlines);
        $inlines = [];
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
            || $name === 'table'
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
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if ($block->type === 'code_block') {
                $parts[] = (string) $block->attr('text', '');
                continue;
            }

            $parts[] = $this->plainInlineText($block->children);
        }

        return $this->normalizedText(implode("\n", array_filter($parts, static fn (string $part): bool => $part !== '')));
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
            $hrefPolicy = $this->navHrefPolicy($baseDir, $href, $root);
            $path = $hrefPolicy['path'];
            $fragment = $hrefPolicy['fragment'];
            $target = $hrefPolicy['target'];
            $external = $hrefPolicy['external'];
            $exists = $hrefPolicy['exists'];
            $typeReport = $this->navItemTypeReport($node, $link);
            $label = $this->normalizedText($link->textContent);
            $linkTypes = $this->epubTypes($link);
            $hasPageBreakType = in_array('pagebreak', $linkTypes, true);
            $hrefNormalizationDiagnostics = $hrefPolicy['normalizationDiagnostics'];
            $itemDiagnostics = $type === 'page-list' ? $hrefPolicy['diagnostics'] : $hrefNormalizationDiagnostics;
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
            } else {
                if ($href === '' && $link->localName === 'a') {
                    $diagnostic = [
                        'type' => 'missing-nav-item-href',
                        'message' => 'EPUB navigation link item is missing an href target',
                    ];
                    $itemDiagnostics[] = $diagnostic;
                    $hrefNormalizationDiagnostics[] = $diagnostic;
                }
                if ($label === '') {
                    $diagnostic = [
                        'type' => 'empty-nav-item-label',
                        'href' => $href,
                        'message' => 'EPUB navigation list item label is empty',
                    ];
                    $itemDiagnostics[] = $diagnostic;
                    $hrefNormalizationDiagnostics[] = $diagnostic;
                }
            }
            $accessibilityLabel = $this->navAccessibilityLabel($link, $label);
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
                'accessibilityLabel' => $accessibilityLabel['text'],
                'accessibilityLabelSource' => $accessibilityLabel['source'],
                'ariaLabel' => $this->nullableAttribute($link, 'aria-label'),
                'title' => $this->nullableAttribute($link, 'title'),
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
                'unsafe' => $hrefPolicy['unsafe'],
                'hrefKind' => $hrefPolicy['hrefKind'],
                'hrefScheme' => $hrefPolicy['hrefScheme'],
                'hrefDiagnostics' => $hrefPolicy['diagnostics'],
                'hrefNormalizationDiagnostics' => $hrefNormalizationDiagnostics,
                'caseMatchedPath' => $hrefPolicy['caseMatchedPath'],
                'normalization' => $hrefPolicy['normalization'],
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
     * @return array{text:string, source:?string}
     */
    private function navAccessibilityLabel(\DOMElement $labelElement, string $visibleLabel): array
    {
        $ariaLabel = $this->nullableAttribute($labelElement, 'aria-label');
        if ($ariaLabel !== null) {
            return ['text' => $ariaLabel, 'source' => 'aria-label'];
        }

        $title = $this->nullableAttribute($labelElement, 'title');
        if ($title !== null) {
            return ['text' => $title, 'source' => 'title'];
        }

        if ($visibleLabel !== '') {
            return ['text' => $visibleLabel, 'source' => 'text'];
        }

        return ['text' => '', 'source' => null];
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

    /**
     * @return list<\DOMElement>
     */
    private function directChildElements(\DOMNode $node, string $localName): array
    {
        $elements = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $elements[] = $child;
            }
        }

        return $elements;
    }

    private function directChildElementCount(\DOMNode $node, string $localName): int
    {
        $count = 0;
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                ++$count;
            }
        }

        return $count;
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

    /**
     * @return array{
     *     target:string,
     *     path:string,
     *     fragment:string,
     *     external:bool,
     *     unsafe:bool,
     *     hrefKind:string,
     *     hrefScheme:?string,
     *     caseMatchedPath:?string,
     *     normalization:array{normalized:bool, percentDecoded:bool, dotSegmentNormalized:bool, packageRootEscape:bool, caseMismatch:bool},
     *     exists:bool,
     *     diagnostics:list<array<string, mixed>>,
     *     normalizationDiagnostics:list<array<string, mixed>>
     * }
     */
    private function navHrefPolicy(string $baseDir, string $href, string $root): array
    {
        $href = trim($href);
        $suffix = $this->hrefSuffix($href);
        $scheme = $this->hrefScheme($href);
        $pathPart = $this->hrefPathPart($href);
        $fragment = is_string($suffix['fragment'] ?? null) ? $suffix['fragment'] : '';
        $external = false;
        $unsafe = $scheme !== null && in_array($scheme, ['data', 'javascript', 'vbscript'], true);
        $hrefKind = 'local';
        $path = '';
        $target = '';
        $exists = false;
        $diagnostics = [];
        $normalizationDiagnostics = [];
        $caseMatchedPath = null;
        $normalization = [
            'normalized' => false,
            'percentDecoded' => false,
            'dotSegmentNormalized' => false,
            'packageRootEscape' => false,
            'caseMismatch' => false,
        ];

        if ($href === '') {
            $hrefKind = 'empty';
        } elseif (str_starts_with($href, '//')) {
            $external = true;
            $hrefKind = 'network-path';
            $path = $pathPart;
            $target = $href;
            $normalizationDiagnostics[] = [
                'type' => 'external-nav-reference',
                'href' => $href,
                'target' => $target,
                'message' => 'EPUB navigation href points outside the package and was not fetched',
            ];
        } elseif ($scheme !== null) {
            $external = true;
            $hrefKind = $unsafe ? 'unsafe-uri' : 'absolute-uri';
            $path = $pathPart;
            $target = $this->targetWithSuffix($path, $suffix);
            $normalizationDiagnostics[] = [
                'type' => 'external-nav-reference',
                'href' => $href,
                'target' => $target,
                'message' => 'EPUB navigation href points outside the package and was not fetched',
            ];
        } elseif (str_starts_with($pathPart, '/')) {
            $external = true;
            $hrefKind = 'package-root-reference';
            $path = $pathPart;
            $target = $href;
            $normalizationDiagnostics[] = [
                'type' => 'external-nav-reference',
                'href' => $href,
                'target' => $target,
                'message' => 'EPUB navigation href points outside the package and was not fetched',
            ];
        } elseif ($pathPart === '') {
            $hrefKind = $fragment !== '' ? 'same-document-fragment' : 'empty';
            $target = $href;
        } else {
            if (preg_match('/%(?![0-9A-Fa-f]{2})/', $pathPart) === 1) {
                $unsafe = true;
                $hrefKind = 'invalid-percent-escape';
                $normalizationDiagnostics[] = [
                    'type' => 'invalid-nav-reference',
                    'href' => $href,
                    'message' => 'EPUB navigation href contains a malformed percent escape',
                ];
            } else {
                $decodedPathPart = rawurldecode($pathPart);
                $normalization['percentDecoded'] = $decodedPathPart !== $pathPart;
                $normalization['dotSegmentNormalized'] = $this->hasDotSegments($decodedPathPart);
                try {
                    $path = $this->normalizeRelativePath($baseDir . '/' . $decodedPathPart);
                    $target = $this->targetWithSuffix($path, $suffix);
                    $exists = $path !== '' && $this->packagePathExists($root, $path);
                    $caseMatchedPath = !$exists && $path !== ''
                        ? $this->caseInsensitivePackagePathMatch($root, $path)
                        : null;
                    $normalization['caseMismatch'] = $caseMatchedPath !== null;
                    $normalization['normalized'] = $normalization['percentDecoded']
                        || $normalization['dotSegmentNormalized']
                        || $normalization['caseMismatch'];
                } catch (\RuntimeException $exception) {
                    $external = true;
                    $hrefKind = 'package-relative-external';
                    $path = $pathPart;
                    $target = $this->targetWithSuffix($path, $suffix);
                    $normalization['percentDecoded'] = false;
                    $normalization['dotSegmentNormalized'] = false;
                    $normalization['normalized'] = false;
                    $normalization['packageRootEscape'] = true;
                    $normalizationDiagnostics[] = [
                        'type' => 'nav-href-package-root-escape',
                        'href' => $href,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            if ($path !== '' && !$external && !$unsafe) {
                if ($normalization['percentDecoded']) {
                    $normalizationDiagnostics[] = [
                        'type' => 'nav-href-percent-decoded',
                        'href' => $href,
                        'path' => $path,
                    ];
                }
                if ($normalization['dotSegmentNormalized']) {
                    $normalizationDiagnostics[] = [
                        'type' => 'nav-href-dot-segment-normalized',
                        'href' => $href,
                        'path' => $path,
                    ];
                }
                if ($normalization['caseMismatch']) {
                    $normalizationDiagnostics[] = [
                        'type' => 'case-sensitive-nav-target-mismatch',
                        'href' => $href,
                        'path' => $path,
                        'caseMatchedPath' => $caseMatchedPath,
                        'message' => 'EPUB navigation href differs from a package-local path only by case',
                    ];
                } elseif (!$exists) {
                    $normalizationDiagnostics[] = [
                        'type' => 'missing-nav-reference',
                        'href' => $href,
                        'path' => $path,
                        'message' => 'EPUB navigation href target is missing from the package',
                    ];
                }
            } elseif ($path === '' && !$external && !$unsafe) {
                $resolved = $this->resolvePackageHrefForNav($baseDir, $href);
                if ($resolved === null) {
                    $external = true;
                    $hrefKind = 'package-relative-external';
                    $path = $pathPart;
                    $target = $this->targetWithSuffix($path, $suffix);
                }
            }

            if ($external && $normalizationDiagnostics === []) {
                $external = true;
                $hrefKind = 'package-relative-external';
                $path = $pathPart;
                $target = $this->targetWithSuffix($path, $suffix);
                $normalizationDiagnostics[] = [
                    'type' => 'external-nav-reference',
                    'href' => $href,
                    'target' => $target,
                    'message' => 'EPUB navigation href points outside the package and was not fetched',
                ];
            }
        }

        if ($suffix['hasQuery'] && $suffix['query'] !== null) {
            $normalizationDiagnostics[] = [
                'type' => 'nav-href-query-component',
                'href' => $href,
                'query' => $suffix['query'],
            ];
        }
        if ($suffix['hasFragment'] && $suffix['fragment'] !== null) {
            $normalizationDiagnostics[] = [
                'type' => 'nav-href-fragment-component',
                'href' => $href,
                'fragment' => $suffix['fragment'],
            ];
        }

        if ($external) {
            $diagnostics[] = [
                'type' => 'external-nav-href-target',
                'target' => $target,
                'hrefKind' => $hrefKind,
                'hrefScheme' => $scheme,
            ];
        }
        if ($unsafe) {
            $diagnostics[] = [
                'type' => 'unsafe-nav-href-target',
                'target' => $target,
                'hrefKind' => $hrefKind,
                'hrefScheme' => $scheme,
            ];
        }

        return [
            'target' => $target,
            'path' => $path,
            'fragment' => $fragment,
            'external' => $external,
            'unsafe' => $unsafe,
            'hrefKind' => $hrefKind,
            'hrefScheme' => $scheme,
            'caseMatchedPath' => $caseMatchedPath,
            'normalization' => $normalization,
            'exists' => $exists,
            'diagnostics' => $diagnostics,
            'normalizationDiagnostics' => $normalizationDiagnostics,
        ];
    }

    private function hasDotSegments(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function hrefScheme(string $href): ?string
    {
        $trimmed = trim($href);
        $colonPosition = strpos($trimmed, ':');
        if ($colonPosition === false) {
            return null;
        }

        $candidate = substr($trimmed, 0, $colonPosition);
        $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $candidate));
        if (preg_match('/^[a-z][a-z0-9+.-]*$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    private function hrefPathPart(string $href): string
    {
        $beforeFragment = explode('#', $href, 2)[0];

        return explode('?', $beforeFragment, 2)[0];
    }

    private function resolvePackageHrefForNav(string $baseDir, string $href): ?string
    {
        try {
            return $this->resolvePackageHref($baseDir, $href);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * @return array{exists:bool, parseError:?string, idCount:int, uniqueIdCount:int, idCounts:array<string, int>, duplicateIds:array<string, int>}
     */
    private function packageXhtmlIdIndex(string $root, string $relative): array
    {
        $empty = [
            'exists' => false,
            'parseError' => null,
            'idCount' => 0,
            'uniqueIdCount' => 0,
            'idCounts' => [],
            'duplicateIds' => [],
        ];

        if (!$this->packagePathExists($root, $relative)) {
            return $empty;
        }

        try {
            $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $relative));
        } catch (\RuntimeException $exception) {
            return [
                'exists' => true,
                'parseError' => $exception->getMessage(),
                'idCount' => 0,
                'uniqueIdCount' => 0,
                'idCounts' => [],
                'duplicateIds' => [],
            ];
        }

        $idCounts = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $id = trim($element->getAttribute('id'));
            if ($id !== '') {
                $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
            }

            $xmlId = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'id'));
            if ($xmlId !== '' && $xmlId !== $id) {
                $idCounts[$xmlId] = ($idCounts[$xmlId] ?? 0) + 1;
            }
        }

        $duplicateIds = array_filter($idCounts, static fn (int $count): bool => $count > 1);

        return [
            'exists' => true,
            'parseError' => null,
            'idCount' => array_sum($idCounts),
            'uniqueIdCount' => count($idCounts),
            'idCounts' => $idCounts,
            'duplicateIds' => $duplicateIds,
        ];
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

    private function caseInsensitivePackagePathMatch(string $root, string $relative): ?string
    {
        $normalized = $this->normalizeRelativePath($relative);
        $directory = $root;
        $matched = [];
        $caseMismatch = false;

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || !is_dir($directory)) {
                return null;
            }

            $entries = scandir($directory);
            if ($entries === false) {
                return null;
            }

            $next = null;
            foreach ($entries as $entry) {
                if ($entry === $segment) {
                    $next = $entry;
                    break;
                }
            }
            if ($next === null) {
                foreach ($entries as $entry) {
                    if (strcasecmp($entry, $segment) === 0) {
                        $next = $entry;
                        $caseMismatch = true;
                        break;
                    }
                }
            }
            if ($next === null) {
                return null;
            }

            $matched[] = $next;
            $directory .= DIRECTORY_SEPARATOR . $next;
        }

        if (!$caseMismatch || !is_file($directory)) {
            return null;
        }

        return implode('/', $matched);
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
