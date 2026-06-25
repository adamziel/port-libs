<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubReader
{
    private const OCF_MIMETYPE = 'application/epub+zip';
    private const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    private const OPF_NAMESPACE = 'http://www.idpf.org/2007/opf';
    private const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    private const NCX_NAMESPACE = 'http://www.daisy.org/z3986/2005/ncx/';
    private const OCF_CONTAINER_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:container';
    private const XHTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

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

            $container = $this->containerMetadata($container_xml);
            $rootfile = $container['selectedRootfile'];
            $opf_xml = $zip->getFromName($rootfile);
            if (!is_string($opf_xml)) {
                throw new \InvalidArgumentException("EPUB package is missing OPF rootfile '{$rootfile}'.");
            }

            return $this->readPackage($zip, $rootfile, $opf_xml, $container, $path);
        } finally {
            $zip->close();
        }
    }

    /**
     * @param array<string, mixed> $containerMetadata
     */
    private function readPackage(\ZipArchive $zip, string $rootfile, string $opf_xml, array $containerMetadata = [], string $path = ''): AstNode
    {
        $dom = $this->loadXml($opf_xml, 'EPUB OPF package');
        $package = $dom->documentElement;
        if (!$package instanceof \DOMElement) {
            throw new \InvalidArgumentException('EPUB OPF root must be a package element.');
        }

        $base_path = $this->dirname($rootfile);
        $metadata = array_replace($this->packageAttributes($package), $this->metadata($package, $base_path));
        $manifest = $this->manifest($package);
        $spine_items = $this->spineItems($package);
        $ocf_mimetype = $this->ocfMimetype($zip, $path);
        $ocf_sidecars = $this->ocfSidecars($zip);
        $package_diagnostics = array_merge(
            $this->ocfMimetypeDiagnostics($ocf_mimetype),
            $this->containerDiagnostics($containerMetadata, $zip),
            $this->packageDiagnostics($zip, $package, $manifest, $base_path, $rootfile)
        );
        $package_diagnostics = array_merge(
            $package_diagnostics,
            $this->ocfSidecarDiagnostics($zip, $ocf_sidecars, $manifest, $base_path, $spine_items)
        );
        $manifest_resources = $this->manifestResources($base_path, $manifest);
        $manifest_resources_by_path = $this->manifestResourcesByPath($manifest_resources);
        $package_link_resources = $this->packageLinkResourceEntries($zip, $package, $base_path, $rootfile, $manifest_resources_by_path);
        $asset_resources = $this->assetResourceEntries($manifest_resources);
        $guide_references = $this->guideReferences($package, $base_path);
        $bindings = $this->bindings($package);
        $collections = $this->collections($package, $base_path);
        $spine_metadata = $this->spineMetadata($package);
        $spine_toc_id = $this->spineTocId($package);
        $navigation = $this->navigation($zip, $base_path, $manifest, $spine_toc_id);
        foreach ($navigation['tocReadingOrderGroups'] as $toc_entry_group) {
            $package_diagnostics = array_merge(
                $package_diagnostics,
                $this->navTocReadingOrderDiagnostics($toc_entry_group, $spine_items, $manifest, $base_path)
            );
        }
        foreach ($navigation['landmarkTargetGroups'] as $landmark_entry_group) {
            $package_diagnostics = array_merge(
                $package_diagnostics,
                $this->xhtmlNavigationTargetDiagnostics($landmark_entry_group, 'landmarks', $spine_items, $manifest, $base_path)
            );
        }
        foreach ($navigation['pageListTargetGroups'] as $page_list_entry_group) {
            $package_diagnostics = array_merge(
                $package_diagnostics,
                $this->xhtmlNavigationTargetDiagnostics($page_list_entry_group, 'page-list', $spine_items, $manifest, $base_path)
            );
            $package_diagnostics = array_merge(
                $package_diagnostics,
                $this->navPageListReadingOrderDiagnostics($zip, $page_list_entry_group, $spine_items, $manifest, $base_path)
            );
        }
        $navigation = $this->navigationWithGuideDerivedLandmarks($navigation, $zip, $package, $spine_items, $manifest, $base_path);
        $package_diagnostics = array_merge(
            $package_diagnostics,
            $this->ncxNavigationDiagnostics($zip, $base_path, $manifest, $spine_toc_id, $spine_items)
        );
        $media_overlays = $this->mediaOverlays($zip, $base_path, $manifest, $this->mediaOverlayMetadata($metadata['epubMetadataProperties'] ?? []));
        $children = [];
        $resources = [];
        $non_linear_resources = [];
        $fallback_spine_resources = [];
        $referenced_resources = [];
        $iframe_resources = [];
        $image_resources = $this->imageResources($base_path, $manifest);
        $spine_xhtml_metadata = [];
        $hyperlink_diagnostic_seen = [];
        $hyperlink_resource_scan_seen = [];

        foreach ($spine_items as $spine_item) {
            $idref = $spine_item['idref'];
            if (!isset($manifest[$idref])) {
                continue;
            }
            $item = $manifest[$idref];
            $href = $this->packageResourcePath($base_path, $item['href']);
            if (!$spine_item['linear']) {
                $non_linear_resources[] = $href;
                $readable_item = $this->readableSpineManifestItem($manifest, $idref, $base_path);
                if ($readable_item !== null && $readable_item['idref'] !== $idref) {
                    $fallback_spine_resources[] = [
                        'idref' => $idref,
                        'path' => $href,
                        'mediaType' => $this->manifestResourceMediaType($href, $item['media-type']),
                        'fallbackIdref' => $readable_item['idref'],
                        'fallbackPath' => $readable_item['path'],
                        'fallbackMediaType' => $this->manifestResourceMediaType($readable_item['path'], $readable_item['item']['media-type']),
                    ];
                }
                continue;
            }
            $readable_item = $this->readableSpineManifestItem($manifest, $idref, $base_path);
            if ($readable_item === null) {
                continue;
            }
            $item = $readable_item['item'];
            $href = $readable_item['path'];
            if ($readable_item['idref'] !== $idref) {
                $sourcePath = $this->packageResourcePath($base_path, $manifest[$idref]['href']);
                $fallback_spine_resources[] = [
                    'idref' => $idref,
                    'path' => $sourcePath,
                    'mediaType' => $this->manifestResourceMediaType($sourcePath, $manifest[$idref]['media-type']),
                    'fallbackIdref' => $readable_item['idref'],
                    'fallbackPath' => $href,
                    'fallbackMediaType' => $this->manifestResourceMediaType($href, $item['media-type']),
                ];
            }
            $xhtml = $zip->getFromName($href);
            if (!is_string($xhtml)) {
                continue;
            }
            $package_diagnostics = array_merge(
                $package_diagnostics,
                $this->malformedSpineXhtmlDiagnostics($xhtml, $href, $readable_item['idref'])
            );
            $xhtml = $this->normalizeEpubSwitches($xhtml);
            $resources[] = $href;
            $xhtml_base_path = $this->dirname($href);
            $xhtml_metadata = $this->xhtmlMetadata($xhtml, $xhtml_base_path);
            if ($xhtml_metadata !== []) {
                $spine_xhtml_metadata[$idref] = $xhtml_metadata;
            }
            $resource_base_path = $this->xhtmlResourceBasePath($xhtml_metadata, $xhtml_base_path);
            $package_diagnostics = array_merge(
                $package_diagnostics,
                $this->xhtmlImageFallbackDiagnostics(
                    $xhtml,
                    $href,
                    $resource_base_path,
                    $manifest,
                    $manifest_resources_by_path,
                    $base_path
                )
            );
            $package_diagnostics = array_merge(
                $package_diagnostics,
                $this->xhtmlHyperlinkedSpineTargetDiagnostics(
                    $zip,
                    $xhtml,
                    $href,
                    $resource_base_path,
                    $manifest,
                    $manifest_resources_by_path,
                    $spine_items,
                    $base_path,
                    $hyperlink_diagnostic_seen,
                    $hyperlink_resource_scan_seen
                )
            );
            $spine_iframe_resources = $this->iframeResourcesForXhtml(
                $zip,
                $xhtml,
                $resource_base_path,
                $manifest_resources_by_path,
                $referenced_resources,
                $iframe_resources
            );
            $rewritten = $this->rewriteRelativeLinks($xhtml, $resource_base_path, $referenced_resources);
            $document = (new MarkdownReader($this->htmlReaderOptions($spine_iframe_resources)))->read($rewritten);
            array_push($children, ...$document->children);
        }

        $metadata['epubRootfile'] = $rootfile;
        if (isset($containerMetadata['version']) && is_string($containerMetadata['version']) && $containerMetadata['version'] !== '') {
            $metadata['epubContainerVersion'] = $containerMetadata['version'];
        }
        if (isset($containerMetadata['rootfiles']) && is_array($containerMetadata['rootfiles']) && $containerMetadata['rootfiles'] !== []) {
            $metadata['epubContainerRootfiles'] = $containerMetadata['rootfiles'];
        }
        $container_rootfiles = is_array($containerMetadata['rootfiles'] ?? null) ? $containerMetadata['rootfiles'] : [];
        $alternate_rootfile_packages = $this->alternateContainerRootfilePackages($zip, $container_rootfiles, $rootfile);
        if ($alternate_rootfile_packages !== []) {
            $metadata['epubAlternateRootfilePackages'] = $alternate_rootfile_packages;
            $metadata['epubAlternateRootfilePackageCount'] = count($alternate_rootfile_packages);
            array_push($children, ...$this->alternateRootfileBodyBlocks($alternate_rootfile_packages));
        }

        if ($children === []) {
            $children[] = new AstNode('paragraph', ['text' => 'No readable EPUB spine content was found.'], [
                new AstNode('text', ['text' => 'No readable EPUB spine content was found.']),
            ]);
        }
        if (isset($containerMetadata['links']) && is_array($containerMetadata['links']) && $containerMetadata['links'] !== []) {
            $metadata['epubContainerLinks'] = $this->containerLinksForMetadata($containerMetadata['links']);
            $metadata['epubContainerLinkCount'] = count($containerMetadata['links']);
        }
        $linear_spine_items = array_values(array_filter($spine_items, static fn (array $item): bool => $item['linear']));
        $metadata['epubSpineItems'] = count($spine_items);
        $metadata['epubLinearSpineItems'] = count($linear_spine_items);
        $metadata['epubNonLinearSpineItems'] = count($spine_items) - count($linear_spine_items);
        if ($spine_metadata['id'] !== '') {
            $metadata['epubSpineId'] = $spine_metadata['id'];
        }
        if ($spine_metadata['pageProgressionDirection'] !== '') {
            $metadata['epubPageProgressionDirection'] = $spine_metadata['pageProgressionDirection'];
        }
        if ($spine_metadata['toc'] !== '') {
            $metadata['epubSpineTocId'] = $spine_metadata['toc'];
        }
        if ($package_diagnostics !== []) {
            $metadata['epubDiagnostics'] = $package_diagnostics;
            $metadata['epubDiagnosticCount'] = count($package_diagnostics);
            $metadata['epubDiagnosticErrorCount'] = count(array_filter(
                $package_diagnostics,
                static fn (array $diagnostic): bool => ($diagnostic['severity'] ?? '') === 'error'
            ));
            $metadata['epubDiagnosticWarningCount'] = count(array_filter(
                $package_diagnostics,
                static fn (array $diagnostic): bool => ($diagnostic['severity'] ?? '') === 'warning'
            ));
        }
        $metadata['epubSpineItemRefs'] = $this->spineItemMetadata(
            $spine_items,
            $manifest,
            $base_path,
            $spine_xhtml_metadata,
            $this->spineRenditionMetadata($metadata['epubMetadataProperties'] ?? [])
        );
        $viewports = array_values(array_filter(
            array_map(static fn (array $item): mixed => $item['viewport'] ?? null, $metadata['epubSpineItemRefs']),
            static fn (mixed $viewport): bool => is_array($viewport)
        ));
        if ($viewports !== []) {
            $metadata['epubViewports'] = $viewports;
            $metadata['epubViewport'] = $viewports[0];
        }
        $metadata['epubReadableResources'] = $resources;
        $metadata['epubManifestResources'] = $manifest_resources;
        $metadata['epubAssetResources'] = array_map(static fn (array $resource): string => $resource['path'], $asset_resources);
        if ($package_link_resources !== []) {
            $metadata['epubPackageLinkResources'] = $package_link_resources;
        }
        if ($this->extractResources()) {
            $extracted_resources = $this->extractedResourcePayloads(
                $zip,
                $this->resourceEntriesForPayloadExtraction($asset_resources, $package_link_resources),
                $referenced_resources
            );
            $metadata['epubResourcePayloads'] = $extracted_resources['payloads'];
            $metadata['epubExtractedResourceCount'] = count($extracted_resources['payloads']);
            $metadata['epubExtractedResourceBytes'] = $extracted_resources['bytes'];
            if ($extracted_resources['skipped'] !== []) {
                $metadata['epubSkippedResourcePayloads'] = $extracted_resources['skipped'];
            }
            $container_links = is_array($containerMetadata['links'] ?? null) ? $containerMetadata['links'] : [];
            $ocf_payloads = $this->extractedOcfSidecarPayloads($zip, $ocf_sidecars);
            $ocf_payloads += $this->extractedContainerLinkPayloads(
                $zip,
                $container_links,
                $container_rootfiles,
                $rootfile,
                array_keys($ocf_payloads)
            );
            if ($ocf_payloads !== []) {
                $metadata['epubOcfSidecarPayloads'] = $ocf_payloads;
            }
            $container_rootfile_payloads = $this->extractedContainerRootfilePayloads(
                $zip,
                $container_rootfiles,
                $rootfile
            );
            if ($container_rootfile_payloads !== []) {
                $metadata['epubContainerRootfilePayloads'] = $container_rootfile_payloads;
            }
        }
        if ($non_linear_resources !== []) {
            $metadata['epubNonLinearResources'] = array_values(array_unique($non_linear_resources));
        }
        if ($fallback_spine_resources !== []) {
            $metadata['epubFallbackSpineResources'] = $this->uniqueFallbackSpineResources($fallback_spine_resources);
            $metadata['epubFallbackSpineResourceCount'] = count($metadata['epubFallbackSpineResources']);
        }
        if ($iframe_resources !== []) {
            $metadata['epubIframeResources'] = array_values(array_unique($iframe_resources));
            $metadata['epubIframeResourceCount'] = count($metadata['epubIframeResources']);
        }
        $metadata['epubReferencedResources'] = array_values(array_unique($referenced_resources));
        $metadata['epubImageResources'] = $image_resources;
        $cover_image = $this->coverImageResource($base_path, $manifest, $metadata, $guide_references);
        if ($cover_image !== '') {
            $metadata['epubCoverImage'] = $cover_image;
        }
        if ($guide_references !== []) {
            $metadata['epubGuideReferences'] = $guide_references;
        }
        if ($bindings !== []) {
            $metadata['epubBindings'] = $bindings;
        }
        if ($collections !== []) {
            $metadata['epubCollections'] = $collections;
        }
        $metadata['epubTocResources'] = $navigation['resources'];
        if ($navigation['ncxMetadata'] !== []) {
            $metadata['epubNcxMetadata'] = $navigation['ncxMetadata'];
            $primary_ncx_metadata = $navigation['ncxMetadata'][0];
            foreach ([
                'epubNcxUid' => 'uid',
                'epubNcxDepth' => 'depth',
                'epubNcxTotalPageCount' => 'totalPageCount',
                'epubNcxMaxPageNumber' => 'maxPageNumber',
                'epubNcxDocTitle' => 'docTitle',
                'epubNcxDocTitleLang' => 'docTitleLang',
                'epubNcxDocAuthors' => 'docAuthors',
                'epubNcxDocAuthorRecords' => 'docAuthorRecords',
                'epubNcxPageListLabel' => 'pageListLabel',
                'epubNcxPageListLabelLang' => 'pageListLabelLang',
            ] as $metadataKey => $sourceKey) {
                if (isset($primary_ncx_metadata[$sourceKey])) {
                    $metadata[$metadataKey] = $primary_ncx_metadata[$sourceKey];
                }
            }
        }
        $metadata['epubTocEntryCount'] = count($navigation['toc']);
        $metadata['epubLandmarkEntryCount'] = count($navigation['landmarks']);
        $metadata['epubPageListEntryCount'] = count($navigation['pageList']);
        $metadata['epubAuxiliaryNavSectionCount'] = count($navigation['auxiliaryNavSections']);
        $metadata['epubAuxiliaryNavEntryCount'] = array_sum(array_map(
            static fn (array $section): int => count($section['entries'] ?? []),
            $navigation['auxiliaryNavSections']
        ));
        if ($navigation['toc'] !== []) {
            $metadata['epubTocEntries'] = $navigation['toc'];
        }
        if ($navigation['landmarks'] !== []) {
            $metadata['epubLandmarkEntries'] = $navigation['landmarks'];
        }
        if ($navigation['pageList'] !== []) {
            $metadata['epubPageListEntries'] = $navigation['pageList'];
        }
        if (($navigation['ncxNavLists'] ?? []) !== []) {
            $metadata['epubNcxNavLists'] = $navigation['ncxNavLists'];
        }
        if ($navigation['auxiliaryNavSections'] !== []) {
            $metadata['epubAuxiliaryNavSections'] = $navigation['auxiliaryNavSections'];
        }
        foreach ([
            'epubNavRootAttributes' => 'rootAttributes',
            'epubNavBodyAttributes' => 'bodyAttributes',
        ] as $metadataKey => $navigationKey) {
            if (($navigation[$navigationKey] ?? []) !== []) {
                $metadata[$metadataKey] = $navigation[$navigationKey];
            }
        }
        foreach ([
            'epubTocNavAttributes' => 'tocNavAttributes',
            'epubLandmarkNavAttributes' => 'landmarkNavAttributes',
            'epubPageListNavAttributes' => 'pageListNavAttributes',
        ] as $metadataKey => $navigationKey) {
            if (($navigation[$navigationKey] ?? []) !== []) {
                $metadata[$metadataKey] = $navigation[$navigationKey];
            }
        }
        foreach ([
            'epubTocNavTitle' => 'tocNavTitle',
            'epubLandmarkNavTitle' => 'landmarkNavTitle',
            'epubPageListNavTitle' => 'pageListNavTitle',
        ] as $metadataKey => $navigationKey) {
            if (($navigation[$navigationKey] ?? '') !== '') {
                $metadata[$metadataKey] = $navigation[$navigationKey];
            }
        }
        if ($media_overlays !== []) {
            $metadata['epubMediaOverlayCount'] = count($media_overlays);
            $metadata['epubMediaOverlays'] = $media_overlays;
            $metadata['epubMediaOverlayResources'] = array_values(array_unique(array_filter(
                array_map(static fn (array $overlay): string => (string) ($overlay['overlayPath'] ?? ''), $media_overlays),
                static fn (string $path): bool => $path !== ''
            )));
        }
        if ($ocf_sidecars !== []) {
            $metadata['epubOcfSidecars'] = $ocf_sidecars;
            $metadata['epubOcfSidecarCount'] = count($ocf_sidecars);
            $encrypted_resources = [];
            $encryption_algorithms = [];
            foreach ($ocf_sidecars as $sidecar) {
                foreach ($sidecar['encryptedResources'] ?? [] as $resource) {
                    if (is_string($resource) && $resource !== '') {
                        $encrypted_resources[] = $resource;
                    }
                }
                foreach ($sidecar['encryptionAlgorithms'] ?? [] as $algorithm) {
                    if (is_string($algorithm) && $algorithm !== '') {
                        $encryption_algorithms[] = $algorithm;
                    }
                }
            }
            if ($encrypted_resources !== []) {
                $metadata['epubEncryptedResources'] = array_values(array_unique($encrypted_resources));
            }
            if ($encryption_algorithms !== []) {
                $metadata['epubEncryptionAlgorithms'] = array_values(array_unique($encryption_algorithms));
            }
        }
        $metadata['epubOcfMimetype'] = $ocf_mimetype;

        return new AstNode('document', ['meta' => $metadata], $children);
    }

    private function rootfilePath(string $container_xml): string
    {
        return $this->containerMetadata($container_xml)['selectedRootfile'];
    }

    /**
     * @return array{selectedRootfile: string, version?: string, root?: array<string, string>, rootfiles: list<array<string, mixed>>, invalidChildren?: list<array<string, mixed>>, invalidRootfileBranches?: list<array<string, mixed>>, invalidRootfileBranchChildren?: list<array<string, mixed>>, invalidRootfiles?: list<array<string, mixed>>, links: list<array<string, mixed>>, invalidLinkBranches?: list<array<string, mixed>>, invalidLinkBranchChildren?: list<array<string, mixed>>, invalidLinks?: list<array<string, mixed>>}
     */
    private function containerMetadata(string $container_xml): array
    {
        $dom = $this->loadXml($container_xml, 'EPUB container');
        $container = $dom->documentElement;
        $xpath = new \DOMXPath($dom);
        $rootfiles = $xpath->query('//*[local-name()="rootfile"]');
        if (!$rootfiles instanceof \DOMNodeList) {
            throw new \InvalidArgumentException('EPUB container rootfile list cannot be read.');
        }

        $fallback = '';
        $selected = '';
        $entries = [];
        $invalidEntries = [];
        $strictContainerScope = $container instanceof \DOMElement
            && $container->localName === 'container'
            && ($container->namespaceURI ?? '') === self::OCF_CONTAINER_NAMESPACE;
        $duplicateRootfileBranches = [];
        $duplicateRootfileBranchIds = [];
        $duplicateLinkBranches = [];
        $duplicateLinkBranchIds = [];
        $invalidChildren = [];
        $invalidRootfileBranchChildren = [];
        $invalidLinkBranchChildren = [];
        if ($strictContainerScope) {
            $invalidChildren = $this->invalidOcfContainerChildren($container);
            [$duplicateRootfileBranches, $duplicateRootfileBranchIds] = $this->duplicateOcfContainerBranches($container, 'rootfiles');
            [$duplicateLinkBranches, $duplicateLinkBranchIds] = $this->duplicateOcfContainerBranches($container, 'links');
            $invalidRootfileBranchChildren = $this->invalidOcfContainerBranchChildren($container, 'rootfiles', 'rootfile', $duplicateRootfileBranchIds);
            $invalidLinkBranchChildren = $this->invalidOcfContainerBranchChildren($container, 'links', 'link', $duplicateLinkBranchIds);
        }
        foreach ($rootfiles as $rootfile) {
            if (!$rootfile instanceof \DOMElement) {
                continue;
            }
            if ($strictContainerScope) {
                $rootfileNamespace = $rootfile->namespaceURI ?? '';
                $parent = $rootfile->parentNode;
                $parentElement = $parent instanceof \DOMElement ? $parent : null;
                if ($rootfileNamespace !== self::OCF_CONTAINER_NAMESPACE) {
                    $invalidEntries[] = $this->invalidContainerRootfileEntry($rootfile, 'namespace');
                    continue;
                }
                if (
                    !$parentElement instanceof \DOMElement
                    || !$this->isDirectOcfContainerBranch($parentElement, 'rootfiles')
                ) {
                    $invalidEntries[] = $this->invalidContainerRootfileEntry($rootfile, 'parent');
                    continue;
                }
                if ($parentElement instanceof \DOMElement && isset($duplicateRootfileBranchIds[$parentElement->getNodePath()])) {
                    continue;
                }
            }
            $full_path = trim($rootfile->getAttribute('full-path'));
            $path = $this->containerRootfileFullPathDiagnosticReason($full_path) === ''
                ? $this->normalizeZipPath($full_path)
                : '';
            if ($path !== '' && $fallback === '') {
                $fallback = $path;
            }
            $media_type = trim($rootfile->getAttribute('media-type'));
            $entry = [
                'path' => $path,
                'fullPath' => $full_path,
                'mediaType' => $media_type,
            ];
            $id = trim($rootfile->getAttribute('id'));
            if ($id !== '') {
                $entry['id'] = $id;
            }
            $properties = $this->attributeTokenList($rootfile, 'properties');
            if ($properties !== []) {
                $entry['properties'] = $properties;
            }
            if ($path !== '' && $this->mediaTypeMatches($media_type, self::OPF_MEDIA_TYPE) && $selected === '') {
                $selected = $path;
                $entry['selected'] = true;
            }
            $entries[] = $entry;
        }

        if ($selected === '' && $fallback !== '') {
            $selected = $fallback;
            foreach ($entries as $index => $entry) {
                if (($entry['path'] ?? '') === $selected) {
                    $entries[$index]['selected'] = true;
                    break;
                }
            }
        }
        if ($selected === '') {
            throw $this->containerRootfileSelectionException(
                $container,
                $strictContainerScope,
                $entries,
                $duplicateRootfileBranches,
                $invalidRootfileBranchChildren,
                $invalidEntries
            );
        }

        $containerLinks = $this->containerLinks($dom, $strictContainerScope, $duplicateLinkBranchIds);
        $metadata = [
            'selectedRootfile' => $selected,
            'rootfiles' => $entries,
            'links' => $containerLinks['links'],
        ];
        if ($invalidChildren !== []) {
            $metadata['invalidChildren'] = $invalidChildren;
        }
        if ($duplicateRootfileBranches !== []) {
            $metadata['invalidRootfileBranches'] = $duplicateRootfileBranches;
        }
        if ($invalidRootfileBranchChildren !== []) {
            $metadata['invalidRootfileBranchChildren'] = $invalidRootfileBranchChildren;
        }
        if ($invalidEntries !== []) {
            $metadata['invalidRootfiles'] = $invalidEntries;
        }
        if ($duplicateLinkBranches !== []) {
            $metadata['invalidLinkBranches'] = $duplicateLinkBranches;
        }
        if ($invalidLinkBranchChildren !== []) {
            $metadata['invalidLinkBranchChildren'] = $invalidLinkBranchChildren;
        }
        if ($containerLinks['invalidLinks'] !== []) {
            $metadata['invalidLinks'] = $containerLinks['invalidLinks'];
        }
        if ($container instanceof \DOMElement) {
            $metadata['root'] = [
                'name' => $container->tagName,
                'localName' => $container->localName,
                'namespaceUri' => $container->namespaceURI ?? '',
            ];
            $version = trim($container->getAttribute('version'));
            if ($version !== '') {
                $metadata['version'] = $version;
            }
        }

        return $metadata;
    }

    /**
     * @param list<array<string, mixed>> $rootfiles
     * @param list<array<string, mixed>> $invalidRootfileBranches
     * @param list<array<string, mixed>> $invalidRootfileBranchChildren
     * @param list<array<string, mixed>> $invalidRootfiles
     */
    private function containerRootfileSelectionException(
        ?\DOMElement $container,
        bool $strictContainerScope,
        array $rootfiles,
        array $invalidRootfileBranches = [],
        array $invalidRootfileBranchChildren = [],
        array $invalidRootfiles = []
    ): EpubContainerException {
        if ($strictContainerScope && $container instanceof \DOMElement) {
            $rootfilesBranch = $this->firstDirectOcfContainerBranch($container, 'rootfiles');
            if (!$rootfilesBranch instanceof \DOMElement) {
                return new EpubContainerException(
                    'EPUB container does not declare an OPF rootfile.',
                    [
                        $this->epubDiagnostic(
                            'error',
                            'missing-container-rootfiles',
                            'EPUB OCF container.xml must declare a rootfiles branch.',
                            [
                                'element' => $container->tagName,
                                'namespace' => $container->namespaceURI ?? '',
                                'expectedElement' => 'rootfiles',
                                'expectedNamespace' => self::OCF_CONTAINER_NAMESPACE,
                            ]
                        ),
                    ]
                );
            }

            if ($rootfiles === []) {
                $childElementCount = 0;
                foreach ($rootfilesBranch->childNodes as $child) {
                    if ($child instanceof \DOMElement) {
                        $childElementCount++;
                    }
                }

                if ($childElementCount > 0) {
                    return new EpubContainerException(
                        'EPUB container does not declare an OPF rootfile.',
                        array_merge(
                            [
                                $this->epubDiagnostic(
                                    'error',
                                    'invalid-container-rootfiles',
                                    'EPUB OCF container.xml rootfiles branch must declare at least one valid rootfile element.',
                                    [
                                        'element' => $rootfilesBranch->tagName,
                                        'namespace' => $rootfilesBranch->namespaceURI ?? '',
                                        'childElementCount' => $childElementCount,
                                        'invalidRootfileCount' => count($invalidRootfiles),
                                        'invalidChildCount' => count($invalidRootfileBranchChildren),
                                    ]
                                ),
                            ],
                            $this->invalidRootfileBranchDiagnostics($invalidRootfileBranches),
                            $this->invalidRootfileBranchChildDiagnostics($invalidRootfileBranchChildren),
                            $this->invalidContainerRootfileDiagnostics($invalidRootfiles)
                        )
                    );
                }

                return new EpubContainerException(
                    'EPUB container does not declare an OPF rootfile.',
                    [
                        $this->epubDiagnostic(
                            'error',
                            'empty-container-rootfiles',
                            'EPUB OCF container.xml rootfiles branch must declare at least one valid rootfile element.',
                            [
                                'element' => $rootfilesBranch->tagName,
                                'namespace' => $rootfilesBranch->namespaceURI ?? '',
                                'childElementCount' => $childElementCount,
                            ]
                        ),
                    ]
                );
            }
        }

        return new EpubContainerException(
            'EPUB container does not declare an OPF rootfile.',
            array_merge(
                [
                    $this->epubDiagnostic(
                        'error',
                        'missing-container-opf-rootfile',
                        'EPUB container rootfiles did not identify a usable OPF package document.',
                        ['rootfileCount' => count($rootfiles)]
                    ),
                ],
                $this->containerRootfileEntryDiagnostics($rootfiles)
            )
        );
    }

    private function firstDirectOcfContainerBranch(\DOMElement $container, string $branchName): ?\DOMElement
    {
        foreach ($container->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && $child->localName === $branchName
                && ($child->namespaceURI ?? '') === self::OCF_CONTAINER_NAMESPACE
            ) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invalidOcfContainerChildren(\DOMElement $container): array
    {
        $invalidChildren = [];
        $allowed = [
            'links' => true,
            'rootfiles' => true,
        ];
        foreach ($container->childNodes as $index => $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $namespace = $child->namespaceURI ?? '';
            $localName = $child->localName;
            $entry = [
                'element' => $child->tagName,
                'localName' => $localName,
                'namespace' => $namespace,
                'childIndex' => $index,
            ];
            if ($namespace !== self::OCF_CONTAINER_NAMESPACE) {
                $invalidChildren[] = $entry + [
                    'reason' => 'namespace',
                    'expectedNamespace' => self::OCF_CONTAINER_NAMESPACE,
                ];
                continue;
            }

            if (!isset($allowed[$localName])) {
                $invalidChildren[] = $entry + [
                    'reason' => 'element',
                    'expectedElements' => ['rootfiles', 'links'],
                ];
            }
        }

        return $invalidChildren;
    }

    /**
     * @param array<string, true> $duplicateBranchIds
     * @return list<array<string, mixed>>
     */
    private function invalidOcfContainerBranchChildren(
        \DOMElement $container,
        string $branchName,
        string $expectedChildName,
        array $duplicateBranchIds = []
    ): array {
        $invalidChildren = [];
        $branchIndex = 0;
        foreach ($container->childNodes as $branch) {
            if (
                !$branch instanceof \DOMElement
                || $branch->localName !== $branchName
                || ($branch->namespaceURI ?? '') !== self::OCF_CONTAINER_NAMESPACE
            ) {
                continue;
            }

            if (isset($duplicateBranchIds[$branch->getNodePath()])) {
                $branchIndex++;
                continue;
            }

            foreach ($branch->childNodes as $childIndex => $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }

                $localName = $child->localName;
                if ($localName === $expectedChildName) {
                    continue;
                }

                $namespace = $child->namespaceURI ?? '';
                $entry = [
                    'branch' => $branchName,
                    'branchIndex' => $branchIndex,
                    'element' => $child->tagName,
                    'localName' => $localName,
                    'namespace' => $namespace,
                    'childIndex' => $childIndex,
                ];
                if ($namespace !== self::OCF_CONTAINER_NAMESPACE) {
                    $invalidChildren[] = $entry + [
                        'reason' => 'namespace',
                        'expectedNamespace' => self::OCF_CONTAINER_NAMESPACE,
                    ];
                    continue;
                }

                $invalidChildren[] = $entry + [
                    'reason' => 'element',
                    'expectedElements' => [$expectedChildName],
                ];
            }

            $branchIndex++;
        }

        return $invalidChildren;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, true>}
     */
    private function duplicateOcfContainerBranches(\DOMElement $container, string $branchName): array
    {
        $seen = false;
        $branchIndex = 0;
        $duplicates = [];
        $duplicateIds = [];
        foreach ($container->childNodes as $child) {
            if (
                !$child instanceof \DOMElement
                || $child->localName !== $branchName
                || ($child->namespaceURI ?? '') !== self::OCF_CONTAINER_NAMESPACE
            ) {
                continue;
            }

            if (!$seen) {
                $seen = true;
                $branchIndex++;
                continue;
            }

            $duplicates[] = $this->invalidContainerBranchEntry($child, $branchName, 'duplicate', $branchIndex);
            $duplicateIds[$child->getNodePath()] = true;
            $branchIndex++;
        }

        return [$duplicates, $duplicateIds];
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidContainerBranchEntry(\DOMElement $branch, string $branchName, string $reason, int $branchIndex): array
    {
        $childElementCount = 0;
        foreach ($branch->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $childElementCount++;
            }
        }

        return [
            'reason' => $reason,
            'branch' => $branchName,
            'branchIndex' => $branchIndex,
            'element' => $branch->tagName,
            'namespace' => $branch->namespaceURI ?? '',
            'childElementCount' => $childElementCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidContainerRootfileEntry(\DOMElement $rootfile, string $reason): array
    {
        $parent = $rootfile->parentNode;
        $entry = [
            'reason' => $reason,
            'element' => $rootfile->tagName,
            'namespace' => $rootfile->namespaceURI ?? '',
            'fullPath' => trim($rootfile->getAttribute('full-path')),
            'mediaType' => trim($rootfile->getAttribute('media-type')),
        ];
        $id = trim($rootfile->getAttribute('id'));
        if ($id !== '') {
            $entry['id'] = $id;
        }
        if ($parent instanceof \DOMElement) {
            $entry['parentElement'] = $parent->tagName;
            $entry['parentNamespace'] = $parent->namespaceURI ?? '';
            $parentParent = $parent->parentNode;
            if ($parentParent instanceof \DOMElement) {
                $entry['parentParentElement'] = $parentParent->tagName;
                $entry['parentParentNamespace'] = $parentParent->namespaceURI ?? '';
            }
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function invalidContainerLinkEntry(\DOMElement $link, string $reason): array
    {
        $parent = $link->parentNode;
        $entry = [
            'reason' => $reason,
            'element' => $link->tagName,
            'namespace' => $link->namespaceURI ?? '',
        ];
        foreach ([
            'id' => 'id',
            'href' => 'href',
            'rel' => 'rel',
            'mediaType' => 'media-type',
            'hreflang' => 'hreflang',
            'refines' => 'refines',
            'dir' => 'dir',
        ] as $key => $attribute) {
            $value = trim($link->getAttribute($attribute));
            if ($value !== '') {
                $entry[$key] = $value;
            }
        }
        if ($parent instanceof \DOMElement) {
            $entry['parentElement'] = $parent->tagName;
            $entry['parentNamespace'] = $parent->namespaceURI ?? '';
            $parentParent = $parent->parentNode;
            if ($parentParent instanceof \DOMElement) {
                $entry['parentParentElement'] = $parentParent->tagName;
                $entry['parentParentNamespace'] = $parentParent->namespaceURI ?? '';
            }
        }

        return $entry;
    }

    private function isDirectOcfContainerBranch(?\DOMElement $branch, string $branchName): bool
    {
        if (
            !$branch instanceof \DOMElement
            || $branch->localName !== $branchName
            || ($branch->namespaceURI ?? '') !== self::OCF_CONTAINER_NAMESPACE
        ) {
            return false;
        }

        $parent = $branch->parentNode;

        return $parent instanceof \DOMElement
            && $parent->localName === 'container'
            && ($parent->namespaceURI ?? '') === self::OCF_CONTAINER_NAMESPACE;
    }

    /**
     * @param list<array<string, mixed>> $invalidRootfileBranches
     * @return list<array<string, mixed>>
     */
    private function invalidRootfileBranchDiagnostics(array $invalidRootfileBranches): array
    {
        $diagnostics = [];
        foreach ($invalidRootfileBranches as $invalidRootfileBranch) {
            $reason = (string) ($invalidRootfileBranch['reason'] ?? '');
            $context = $invalidRootfileBranch;
            unset($context['reason']);
            if ($reason === 'duplicate') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-container-rootfiles',
                    'EPUB OCF container.xml must not declare multiple rootfiles branches.',
                    $context
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $invalidRootfileBranchChildren
     * @return list<array<string, mixed>>
     */
    private function invalidRootfileBranchChildDiagnostics(array $invalidRootfileBranchChildren): array
    {
        $diagnostics = [];
        foreach ($invalidRootfileBranchChildren as $invalidRootfileBranchChild) {
            $reason = (string) ($invalidRootfileBranchChild['reason'] ?? '');
            $context = $invalidRootfileBranchChild;
            unset($context['reason']);
            if ($reason === 'namespace') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-rootfiles-child-namespace',
                    'EPUB OCF container rootfiles branch children must use the OCF container namespace.',
                    $context
                );
                continue;
            }
            if ($reason === 'element') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-rootfiles-child-element',
                    'EPUB OCF container rootfiles branch children must be rootfile elements.',
                    $context
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $invalidRootfiles
     * @return list<array<string, mixed>>
     */
    private function invalidContainerRootfileDiagnostics(array $invalidRootfiles): array
    {
        $diagnostics = [];
        foreach ($invalidRootfiles as $index => $invalidRootfile) {
            $reason = (string) ($invalidRootfile['reason'] ?? '');
            $context = $invalidRootfile + ['rootfileIndex' => $index];
            unset($context['reason']);
            if ($reason === 'namespace') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-rootfile-namespace',
                    'EPUB OCF container rootfile elements must use the OCF container namespace.',
                    $context
                );
                continue;
            }
            if ($reason === 'parent') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-rootfile-parent',
                    'EPUB OCF container rootfile elements must be direct children of rootfiles.',
                    $context
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $rootfiles
     * @return list<array<string, mixed>>
     */
    private function containerRootfileEntryDiagnostics(array $rootfiles, ?\ZipArchive $zip = null): array
    {
        $diagnostics = [];
        $seenRootfileIds = [];
        foreach ($rootfiles as $index => $rootfile) {
            $fullPath = (string) ($rootfile['fullPath'] ?? '');
            $path = (string) ($rootfile['path'] ?? '');
            $mediaType = (string) ($rootfile['mediaType'] ?? '');
            $context = $this->containerRootfileDiagnosticContext($rootfile, $index);
            $id = (string) ($rootfile['id'] ?? '');
            $fullPathReason = $this->containerRootfileFullPathDiagnosticReason($fullPath);

            if ($id !== '') {
                if (!$this->validXmlId($id)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-container-rootfile-id',
                        'EPUB OCF rootfile id attributes must be XML NCNames.',
                        $context
                    );
                } elseif (isset($seenRootfileIds[$id])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-container-rootfile-id',
                        'EPUB OCF rootfile id attributes must be unique within container rootfiles.',
                        $context + ['previousRootfileIndex' => $seenRootfileIds[$id]]
                    );
                } else {
                    $seenRootfileIds[$id] = $index;
                }
            }

            $properties = is_array($rootfile['properties'] ?? null) ? $rootfile['properties'] : [];
            $propertyTokens = array_values(array_filter(
                array_map(static fn (mixed $property): string => is_scalar($property) ? (string) $property : '', $properties),
                static fn (string $property): bool => $property !== ''
            ));
            foreach ($this->duplicateTokens($propertyTokens) as $property) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-container-rootfile-property',
                    'EPUB OCF rootfile properties values must not repeat token values.',
                    $context + ['property' => $property]
                );
            }
            foreach ($properties as $property) {
                if (!is_scalar($property)) {
                    continue;
                }

                $property = (string) $property;
                if ($this->validPropertyValue($property)) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-rootfile-property',
                    'EPUB OCF rootfile properties values must be valid property data type values.',
                    $context + ['property' => $property]
                );
            }

            if ($fullPathReason !== '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-rootfile-full-path',
                    'EPUB OCF rootfile full-path must be a package-relative archive path.',
                    $context + ['reason' => $fullPathReason]
                );
            }

            if ($fullPath === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-container-rootfile-full-path',
                    'EPUB OCF rootfile must declare a usable full-path attribute.',
                    $context
                );
            }

            if ($mediaType === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-container-rootfile-media-type',
                    'EPUB OCF rootfile must declare a media-type attribute.',
                    $context
                );
                continue;
            }

            if (!$this->validMediaType($mediaType)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-rootfile-media-type',
                    'EPUB OCF rootfile media-type is not a valid media type.',
                    $context + [
                        'expectedMediaType' => self::OPF_MEDIA_TYPE,
                        'reason' => 'syntax',
                    ]
                );
                continue;
            }

            if (!$this->mediaTypeMatches($mediaType, self::OPF_MEDIA_TYPE)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-rootfile-media-type',
                    'EPUB OCF rootfile media-type must identify an OPF package document.',
                    $context + [
                        'expectedMediaType' => self::OPF_MEDIA_TYPE,
                        'reason' => 'unexpected',
                    ]
                );
                continue;
            }

            if ($zip instanceof \ZipArchive && $path !== '' && $zip->locateName($path) === false) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-container-rootfile-resource',
                    'EPUB OCF rootfile full-path does not resolve to a resource in the EPUB archive.',
                    $context
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $containerMetadata
     * @return list<array<string, mixed>>
     */
    private function containerDiagnostics(array $containerMetadata, \ZipArchive $zip): array
    {
        $diagnostics = [];
        $root = is_array($containerMetadata['root'] ?? null) ? $containerMetadata['root'] : [];
        $rootLocalName = (string) ($root['localName'] ?? '');
        $rootNamespace = (string) ($root['namespaceUri'] ?? '');
        if ($rootLocalName !== 'container' || $rootNamespace !== self::OCF_CONTAINER_NAMESPACE) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-container-root',
                'EPUB OCF container.xml must use the OCF container root element.',
                [
                    'element' => (string) ($root['name'] ?? $rootLocalName),
                    'namespace' => $rootNamespace,
                    'expectedElement' => 'container',
                    'expectedNamespace' => self::OCF_CONTAINER_NAMESPACE,
                ]
            );
        }

        $version = (string) ($containerMetadata['version'] ?? '');
        if ($version !== '' && $version !== '1.0') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-container-version',
                'EPUB OCF container.xml version must be 1.0 when present.',
                [
                    'version' => $version,
                    'expectedVersion' => '1.0',
                ]
            );
        }

        $invalidChildren = is_array($containerMetadata['invalidChildren'] ?? null) ? $containerMetadata['invalidChildren'] : [];
        foreach ($invalidChildren as $invalidChild) {
            if (!is_array($invalidChild)) {
                continue;
            }

            $reason = (string) ($invalidChild['reason'] ?? '');
            $context = $invalidChild;
            unset($context['reason']);
            if ($reason === 'namespace') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-child-namespace',
                    'EPUB OCF container.xml direct children must use the OCF container namespace.',
                    $context
                );
                continue;
            }
            if ($reason === 'element') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-child-element',
                    'EPUB OCF container.xml direct children must be rootfiles or links branches.',
                    $context
                );
            }
        }

        $rootfiles = is_array($containerMetadata['rootfiles'] ?? null) ? $containerMetadata['rootfiles'] : [];
        $invalidRootfileBranches = is_array($containerMetadata['invalidRootfileBranches'] ?? null) ? $containerMetadata['invalidRootfileBranches'] : [];
        $diagnostics = array_merge($diagnostics, $this->invalidRootfileBranchDiagnostics($invalidRootfileBranches));
        $invalidRootfileBranchChildren = is_array($containerMetadata['invalidRootfileBranchChildren'] ?? null) ? $containerMetadata['invalidRootfileBranchChildren'] : [];
        $diagnostics = array_merge($diagnostics, $this->invalidRootfileBranchChildDiagnostics($invalidRootfileBranchChildren));
        $invalidRootfiles = is_array($containerMetadata['invalidRootfiles'] ?? null) ? $containerMetadata['invalidRootfiles'] : [];
        $diagnostics = array_merge($diagnostics, $this->invalidContainerRootfileDiagnostics($invalidRootfiles));
        $diagnostics = array_merge($diagnostics, $this->containerRootfileEntryDiagnostics($rootfiles, $zip));

        $links = is_array($containerMetadata['links'] ?? null) ? $containerMetadata['links'] : [];
        $invalidLinkBranches = is_array($containerMetadata['invalidLinkBranches'] ?? null) ? $containerMetadata['invalidLinkBranches'] : [];
        foreach ($invalidLinkBranches as $invalidLinkBranch) {
            if (!is_array($invalidLinkBranch)) {
                continue;
            }

            $reason = (string) ($invalidLinkBranch['reason'] ?? '');
            $context = $invalidLinkBranch;
            unset($context['reason']);
            if ($reason === 'duplicate') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-container-links',
                    'EPUB OCF container.xml must not declare multiple links branches.',
                    $context
                );
            }
        }
        $invalidLinkBranchChildren = is_array($containerMetadata['invalidLinkBranchChildren'] ?? null) ? $containerMetadata['invalidLinkBranchChildren'] : [];
        foreach ($invalidLinkBranchChildren as $invalidLinkBranchChild) {
            if (!is_array($invalidLinkBranchChild)) {
                continue;
            }

            $reason = (string) ($invalidLinkBranchChild['reason'] ?? '');
            $context = $invalidLinkBranchChild;
            unset($context['reason']);
            if ($reason === 'namespace') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-links-child-namespace',
                    'EPUB OCF container links branch children must use the OCF container namespace.',
                    $context
                );
                continue;
            }
            if ($reason === 'element') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-links-child-element',
                    'EPUB OCF container links branch children must be link elements.',
                    $context
                );
            }
        }
        $invalidLinks = is_array($containerMetadata['invalidLinks'] ?? null) ? $containerMetadata['invalidLinks'] : [];
        foreach ($invalidLinks as $index => $invalidLink) {
            if (!is_array($invalidLink)) {
                continue;
            }

            $reason = (string) ($invalidLink['reason'] ?? '');
            $context = $invalidLink + ['containerLinkIndex' => $index];
            unset($context['reason']);
            if ($reason === 'namespace') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-link-namespace',
                    'EPUB OCF container link elements must use the OCF container namespace.',
                    $context
                );
                continue;
            }
            if ($reason === 'parent') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-link-parent',
                    'EPUB OCF container link elements must be direct children of links.',
                    $context
                );
            }
        }
        $seenContainerLinkIds = [];
        $targetDocuments = [];
        foreach ($links as $index => $link) {
            if (!is_array($link)) {
                continue;
            }

            $href = (string) ($link['href'] ?? '');
            $sourceHref = (string) ($link['sourceHref'] ?? $href);
            $rel = (string) ($link['rel'] ?? '');
            $mediaType = (string) ($link['mediaType'] ?? '');
            $context = $this->containerLinkDiagnosticContext($link, $index);
            $id = (string) ($link['id'] ?? '');

            if ($id !== '') {
                if (!$this->validXmlId($id)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-container-link-id',
                        'EPUB OCF container link id attributes must be XML NCNames.',
                        $context
                    );
                } elseif (isset($seenContainerLinkIds[$id])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-container-link-id',
                        'EPUB OCF container link id attributes must be unique within container links.',
                        $context + ['previousContainerLinkIndex' => $seenContainerLinkIds[$id]]
                    );
                } else {
                    $seenContainerLinkIds[$id] = $index;
                }
            }

            if ($href === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-container-link-href',
                    'EPUB OCF container link must declare an href attribute.',
                    $context
                );
            } else {
                $lowerHref = strtolower($href);
                if (str_starts_with($lowerHref, 'data:')) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-container-link-data-url',
                        'EPUB OCF container link href must not be a data URL.',
                        $context + ['href' => $href]
                    );
                }
                if (str_starts_with($lowerHref, 'file:')) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-container-link-file-url',
                        'EPUB OCF container link href must not be a file URL.',
                        $context + ['href' => $href]
                    );
                }
                $hrefPathReason = $this->containerLinkHrefPathDiagnosticReason($sourceHref);
                if ($hrefPathReason !== '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-container-link-href-path',
                        'EPUB OCF container link href must be an absolute URL or a path-relative scheme-less URL.',
                        $context + ['reason' => $hrefPathReason]
                    );
                }
                $hrefFragmentReason = $this->containerLinkHrefFragmentDiagnosticReason($sourceHref);
                if ($hrefFragmentReason !== '') {
                    $fragment = $this->urlFragmentIdentifier($sourceHref);
                    $fragmentContext = $context + ['reason' => $hrefFragmentReason];
                    if ($fragment !== '') {
                        $fragmentContext['fragment'] = $fragment;
                    }
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-container-link-href-fragment',
                        'EPUB OCF container link href fragments must be non-empty fragment identifiers without whitespace.',
                        $fragmentContext
                    );
                }
            }

            if ($rel === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-container-link-rel',
                    'EPUB OCF container link must declare a rel attribute.',
                    $context
                );
            }
            foreach ($this->duplicateTokens($this->tokenList($rel)) as $relValue) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-container-link-rel',
                    'EPUB OCF container link rel values must not repeat token values.',
                    $context + ['value' => $relValue]
                );
            }
            foreach ($this->tokenList($rel) as $relValue) {
                if ($this->validPropertyValue($relValue)) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-link-rel',
                    'EPUB OCF container link rel values must be valid property data type values.',
                    $context + ['value' => $relValue]
                );
            }

            if ($mediaType !== '' && !$this->validMediaType($mediaType)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-link-media-type',
                    'EPUB OCF container link media-type must be a valid media type.',
                    $context
                );
            }

            $hreflang = (string) ($link['hreflang'] ?? '');
            if ($hreflang !== '' && !$this->validXmlLanguageTag($hreflang)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-link-hreflang',
                    'EPUB OCF container link hreflang must be a valid language tag.',
                    $context + ['hreflang' => $hreflang]
                );
            }

            $direction = (string) ($link['dir'] ?? '');
            if ($direction !== '' && !in_array(strtolower($direction), ['ltr', 'rtl', 'auto'], true)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-link-dir',
                    'EPUB OCF container link dir attribute must be ltr, rtl, or auto.',
                    $context + ['dir' => $direction]
                );
            }

            $refines = (string) ($link['refines'] ?? '');
            if ($refines !== '' && !$this->validMetadataRefinesValue($refines)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-link-refines',
                    'EPUB OCF container link refines attributes must be fragment references to XML IDs.',
                    $context + ['refines' => $refines]
                );
            }

            $properties = is_array($link['properties'] ?? null) ? $link['properties'] : [];
            $propertyTokens = array_values(array_filter(
                array_map(static fn (mixed $property): string => is_scalar($property) ? (string) $property : '', $properties),
                static fn (string $property): bool => $property !== ''
            ));
            foreach ($this->duplicateTokens($propertyTokens) as $property) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-container-link-property',
                    'EPUB OCF container link properties values must not repeat token values.',
                    $context + ['property' => $property]
                );
            }
            foreach ($properties as $property) {
                if (!is_scalar($property)) {
                    continue;
                }

                $property = (string) $property;
                if ($this->validPropertyValue($property)) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-container-link-property',
                    'EPUB OCF container link properties values must be valid property data type values.',
                    $context + ['property' => $property]
                );
            }

            if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
                continue;
            }
            if ($this->containerLinkHrefPathDiagnosticReason($sourceHref) !== '') {
                continue;
            }

            [$zipPath] = $this->splitUrlPathSuffix($href);
            $zipPath = $this->normalizeZipPath($zipPath);
            if ($zipPath === '') {
                continue;
            }

            if ($zip->locateName($zipPath) === false) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-container-link-resource',
                    'EPUB OCF container link href does not resolve to a resource in the EPUB archive.',
                    $context + ['path' => $zipPath]
                );
                continue;
            }

            $fragment = $this->urlFragmentIdentifier($sourceHref);
            if ($fragment === '' || $this->containerLinkHrefFragmentDiagnosticReason($sourceHref) !== '') {
                continue;
            }

            if (!array_key_exists($zipPath, $targetDocuments)) {
                $targetDocuments[$zipPath] = false;
                $targetXml = $zip->getFromName($zipPath);
                if (is_string($targetXml)) {
                    try {
                        $targetDocuments[$zipPath] = $this->loadXml($targetXml, 'EPUB OCF container link target');
                    } catch (\Throwable) {
                        $targetDocuments[$zipPath] = false;
                    }
                }
            }

            $targetDocument = $targetDocuments[$zipPath];
            if ($targetDocument === false) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'malformed-container-link-resource',
                    'EPUB OCF container link href references a resource that is not valid XML.',
                    $context + ['path' => $zipPath, 'fragment' => $fragment]
                );
                continue;
            }
            if ($targetDocument instanceof \DOMDocument && !$this->xmlDocumentHasElementId($targetDocument, $fragment)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-container-link-fragment',
                    'EPUB OCF container link href fragment does not resolve to an element id in the referenced resource.',
                    $context + ['path' => $zipPath, 'fragment' => $fragment]
                );
            }
        }

        return $diagnostics;
    }

    private function containerLinkHrefPathDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($href)) {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return 'absolute-path';
        }
        if (str_contains($href, '\\')) {
            return 'backslash';
        }

        [$hrefPath, $suffix] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '' && $suffix !== '') {
            return 'empty-path';
        }
        foreach (explode('/', $hrefPath) as $segment) {
            if ($segment === '..') {
                return 'traversal';
            }
        }

        return $this->encodedDotSegmentPathDiagnosticReason($hrefPath);
    }

    private function containerLinkHrefFragmentDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '' || !str_contains($href, '#') || $this->isAbsoluteUrl($href) || str_starts_with($href, '#')) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($href);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $rootfile
     * @return array<string, mixed>
     */
    private function containerRootfileDiagnosticContext(array $rootfile, int $index): array
    {
        $context = ['rootfileIndex' => $index];
        foreach (['id', 'path', 'fullPath', 'mediaType'] as $key) {
            if (isset($rootfile[$key]) && is_scalar($rootfile[$key]) && (string) $rootfile[$key] !== '') {
                $context[$key] = (string) $rootfile[$key];
            }
        }

        return $context;
    }

    private function containerRootfileFullPathDiagnosticReason(string $fullPath): string
    {
        $fullPath = trim($fullPath);
        if ($fullPath === '') {
            return '';
        }

        $lowerPath = strtolower($fullPath);
        if (str_starts_with($lowerPath, 'data:')) {
            return 'data-url';
        }
        if (str_starts_with($lowerPath, 'file:')) {
            return 'file-url';
        }
        if ($this->isAbsoluteUrl($fullPath)) {
            return 'absolute-url';
        }
        if (str_contains($fullPath, '\\')) {
            return 'backslash';
        }

        $path = str_replace('\\', '/', $fullPath);
        if (str_starts_with($path, '/')) {
            return 'absolute-path';
        }
        if (strpbrk($path, '?#') !== false) {
            return 'url-suffix';
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                return 'traversal';
            }
        }
        $encodedDotSegmentReason = $this->encodedDotSegmentPathDiagnosticReason($path);
        if ($encodedDotSegmentReason !== '') {
            return $encodedDotSegmentReason;
        }
        if ($this->normalizeZipPath($path) === '') {
            return 'empty-normalized-path';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $link
     * @return array<string, mixed>
     */
    private function containerLinkDiagnosticContext(array $link, int $index): array
    {
        $context = ['containerLinkIndex' => $index];
        foreach (['id', 'href', 'rel', 'mediaType', 'hreflang', 'dir', 'refines'] as $key) {
            if (isset($link[$key]) && is_scalar($link[$key]) && (string) $link[$key] !== '') {
                $context[$key] = (string) $link[$key];
            }
        }
        if (isset($link['sourceHref']) && is_scalar($link['sourceHref']) && (string) $link['sourceHref'] !== '') {
            $context['href'] = (string) $link['sourceHref'];
        }

        return $context;
    }

    /**
     * @param list<array<string, mixed>> $links
     * @return list<array<string, mixed>>
     */
    private function containerLinksForMetadata(array $links): array
    {
        return array_map(
            static function (array $link): array {
                unset($link['sourceHref']);
                return $link;
            },
            $links
        );
    }

    /**
     * @return array{links: list<array<string, mixed>>, invalidLinks: list<array<string, mixed>>}
     */
    private function containerLinks(\DOMDocument $dom, bool $strictContainerScope, array $duplicateLinkBranchIds = []): array
    {
        $links = [];
        $invalidLinks = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'link') {
                continue;
            }
            $parent = $element->parentNode;
            $parentElement = $parent instanceof \DOMElement ? $parent : null;
            if ($strictContainerScope) {
                if (($element->namespaceURI ?? '') !== self::OCF_CONTAINER_NAMESPACE) {
                    $invalidLinks[] = $this->invalidContainerLinkEntry($element, 'namespace');
                    continue;
                }
                if (
                    !$parentElement instanceof \DOMElement
                    || !$this->isDirectOcfContainerBranch($parentElement, 'links')
                ) {
                    $invalidLinks[] = $this->invalidContainerLinkEntry($element, 'parent');
                    continue;
                }
                if ($parentElement instanceof \DOMElement && isset($duplicateLinkBranchIds[$parentElement->getNodePath()])) {
                    continue;
                }
            } elseif (!$parentElement instanceof \DOMElement || $parentElement->localName !== 'links') {
                continue;
            }
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $link = [];
            if ($href !== '') {
                $link['href'] = $this->rewriteRelativeResourceUrl($href, 'META-INF');
                $link['sourceHref'] = $href;
            }
            foreach ([
                'id' => 'id',
                'rel' => 'rel',
                'hreflang' => 'hreflang',
                'mediaType' => 'media-type',
                'refines' => 'refines',
            ] as $key => $attribute) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $link[$key] = $value;
                }
            }
            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language === '') {
                $language = trim($element->getAttribute('lang'));
            }
            if ($language !== '') {
                $link['lang'] = $language;
            }
            $direction = trim($element->getAttribute('dir'));
            if ($direction !== '') {
                $lowerDirection = strtolower($direction);
                $link['dir'] = in_array($lowerDirection, ['ltr', 'rtl', 'auto'], true)
                    ? $lowerDirection
                    : $direction;
            }
            $properties = $this->attributeTokenList($element, 'properties');
            if ($properties !== []) {
                $link['properties'] = $properties;
            }
            $links[] = $link;
        }

        return [
            'links' => $links,
            'invalidLinks' => $invalidLinks,
        ];
    }

    /**
     * @return list<string>
     */
    private function attributeTokenList(\DOMElement $element, string $attribute): array
    {
        return $this->tokenList($element->getAttribute($attribute));
    }

    /**
     * @return list<string>
     */
    private function tokenList(string $value): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', trim($value)) ?: [],
            static fn (string $token): bool => $token !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function duplicateAttributeTokens(\DOMElement $element, string $attribute): array
    {
        return $this->duplicateTokens($this->attributeTokenList($element, $attribute));
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private function duplicateTokens(array $tokens): array
    {
        $duplicates = [];
        $seen = [];
        foreach ($tokens as $token) {
            if (!isset($seen[$token])) {
                $seen[$token] = true;
                continue;
            }
            if (!isset($duplicates[$token])) {
                $duplicates[$token] = $token;
            }
        }

        return array_values($duplicates);
    }

    private function mediaTypeMatches(string $candidate, string $expected): bool
    {
        $type = strtolower(trim(explode(';', $candidate, 2)[0]));

        return $type === strtolower($expected);
    }

    private function isEpubContentDocumentMediaType(string $media_type): bool
    {
        return $this->mediaTypeMatches($media_type, 'application/xhtml+xml')
            || $this->mediaTypeMatches($media_type, 'image/svg+xml');
    }

    /**
     * @param array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string} $item
     */
    private function isMediaOverlayDocumentManifestItem(array $item): bool
    {
        $mediaType = strtolower(trim($item['media-type']));
        [$hrefPath] = $this->splitUrlPathSuffix($item['href']);

        return str_contains($mediaType, 'smil')
            || str_ends_with(strtolower($hrefPath), '.smil');
    }

    /**
     * @return array<string, mixed>
     */
    private function packageAttributes(\DOMElement $package): array
    {
        $attributes = [];
        $version = trim($package->getAttribute('version'));
        if ($version !== '') {
            $attributes['epubPackageVersion'] = $version;
        }
        $id = trim($package->getAttribute('id'));
        if ($id !== '') {
            $attributes['epubPackageId'] = $id;
        }
        $unique_identifier = trim($package->getAttribute('unique-identifier'));
        if ($unique_identifier !== '') {
            $attributes['epubPackageUniqueIdentifierId'] = $unique_identifier;
        }
        $prefix = trim(preg_replace('/\s+/', ' ', $package->getAttribute('prefix')) ?? $package->getAttribute('prefix'));
        if ($prefix !== '') {
            $attributes['epubPackagePrefix'] = $prefix;
        }
        $direction = strtolower(trim($package->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['epubPackageDirection'] = $direction;
        }
        $language = trim($package->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language !== '') {
            $attributes['epubPackageLanguage'] = $language;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(\DOMElement $package, string $base_path): array
    {
        $meta = [];
        $unique_identifier = trim($package->getAttribute('unique-identifier'));
        $identifiers = [];
        $metadataElement = $this->directOpfChildElement($package, 'metadata');
        if ($metadataElement === null) {
            return $meta;
        }

        foreach ($metadataElement->childNodes as $entry) {
            if (!$entry instanceof \DOMElement) {
                continue;
            }
            $name = $entry->localName;
            $text = trim(preg_replace('/\s+/', ' ', $entry->textContent) ?? $entry->textContent);
            if ($name === 'meta') {
                if (!$this->isOpfPackageElement($entry, 'meta')) {
                    continue;
                }

                $property = trim($entry->getAttribute('property'));
                if ($property !== '') {
                    $value = trim($entry->getAttribute('content'));
                    if ($value === '') {
                        $value = $text;
                    }
                    if ($value !== '') {
                        $meta['epubProperties'][$property][] = $value;
                        $record = [
                            'property' => $property,
                            'value' => $value,
                        ];
                        $hasRefinement = false;
                        foreach (['id', 'refines', 'scheme'] as $attribute) {
                            $attributeValue = trim($entry->getAttribute($attribute));
                            if ($attributeValue !== '') {
                                $record[$attribute] = $attributeValue;
                                if ($attribute === 'refines') {
                                    $hasRefinement = true;
                                }
                            }
                        }
                        $direction = strtolower(trim($entry->getAttribute('dir')));
                        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                            $record['dir'] = $direction;
                        }
                        $language = trim($entry->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
                        if ($language !== '') {
                            $record['lang'] = $language;
                        }
                        $meta['epubMetadataProperties'][] = $record;
                        if (!$hasRefinement) {
                            $this->assignRenditionMetadata($meta, $property, $value);
                            $this->assignMediaMetadata($meta, $property, $value);
                        }
                    }
                }

                $meta_name = strtolower(trim($entry->getAttribute('name')));
                $content = trim($entry->getAttribute('content'));
                if ($meta_name === 'cover' && $content !== '') {
                    $meta['epubCoverItemId'] = $content;
                }
                continue;
            }
            if ($name === 'link') {
                if (!$this->isOpfPackageElement($entry, 'link')) {
                    continue;
                }

                $link = $this->metadataLink($entry, $base_path);
                if ($link !== null) {
                    $meta['epubMetadataLinks'][] = $link;
                }
                continue;
            }
            if ($text === '') {
                continue;
            }
            if ($entry->namespaceURI === self::DC_NAMESPACE || in_array($name, ['title', 'creator', 'contributor', 'date', 'language', 'identifier', 'subject', 'description', 'publisher', 'rights', 'source', 'relation', 'coverage', 'type', 'format'], true)) {
                $record = $this->dublinCoreMetadataRecord($entry, $name, $text);
                if ($record !== null) {
                    $meta['epubDublinCoreMetadata'][] = $record;
                }
                $key = match ($name) {
                    'creator' => 'author',
                    'language' => 'lang',
                    default => $name,
                };
                if ($name === 'identifier') {
                    $identifiers[] = [
                        'id' => trim($entry->getAttribute('id')),
                        'value' => $text,
                    ];
                }
                if (isset($meta[$key])) {
                    $meta[$key] = is_array($meta[$key]) ? array_merge($meta[$key], [$text]) : [$meta[$key], $text];
                } else {
                    $meta[$key] = $text;
                }
                if ($key === 'title') {
                    $meta['titleInlines'] = [new AstNode('text', ['text' => $text])];
                }
                continue;
            }
        }
        if ($unique_identifier !== '' && $identifiers !== []) {
            foreach ($identifiers as $identifier) {
                if ($identifier['id'] === $unique_identifier) {
                    $meta['identifier'] = $identifier['value'];
                    break;
                }
            }
        }
        $this->suppressConflictingPackageRenditionMetadata($meta);

        return $meta;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function suppressConflictingPackageRenditionMetadata(array &$meta): void
    {
        foreach ($this->packageRenditionMetadataValueGroups($meta['epubMetadataProperties'] ?? []) as $property => $group) {
            if (count($group['values'] ?? []) < 2) {
                continue;
            }

            $config = $this->packageRenditionMetadataConfig($property);
            if ($config === null) {
                continue;
            }

            unset($meta[$config['metaKey']]);
            if ($property === 'rendition:viewport') {
                unset($meta['epubViewport']);
            }
        }
    }

    /**
     * @param mixed $metadataProperties
     * @return array<string, array{values: array<string, true>, ids: list<string>}>
     */
    private function packageRenditionMetadataValueGroups(mixed $metadataProperties): array
    {
        if (!is_array($metadataProperties)) {
            return [];
        }

        $groups = [];
        foreach ($metadataProperties as $record) {
            if (!is_array($record)) {
                continue;
            }

            $property = strtolower(trim((string) ($record['property'] ?? '')));
            if ($property === '' || trim((string) ($record['refines'] ?? '')) !== '') {
                continue;
            }

            $config = $this->packageRenditionMetadataConfig($property);
            if ($config === null) {
                continue;
            }

            $value = trim((string) ($record['value'] ?? $record['content'] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($property === 'rendition:viewport') {
                $viewport = $this->parseViewportContent($value);
                if ($viewport === null) {
                    continue;
                }
                $value = $viewport['content'];
            } else {
                $value = strtolower($value);
                if (!in_array($value, $config['allowed'], true)) {
                    continue;
                }
            }

            $groups[$property]['values'][$value] = true;
            $id = trim((string) ($record['id'] ?? ''));
            if ($id !== '' && !in_array($id, $groups[$property]['ids'] ?? [], true)) {
                $groups[$property]['ids'][] = $id;
            }
        }

        return $groups;
    }

    /**
     * @return array{metaKey: string, allowed: list<string>}|null
     */
    private function packageRenditionMetadataConfig(string $property): ?array
    {
        return match (strtolower(trim($property))) {
            'rendition:layout' => ['metaKey' => 'epubRenditionLayout', 'allowed' => ['reflowable', 'pre-paginated']],
            'rendition:orientation' => ['metaKey' => 'epubRenditionOrientation', 'allowed' => ['landscape', 'portrait', 'auto']],
            'rendition:spread' => ['metaKey' => 'epubRenditionSpread', 'allowed' => ['none', 'landscape', 'portrait', 'both', 'auto']],
            'rendition:flow' => ['metaKey' => 'epubRenditionFlow', 'allowed' => ['paginated', 'scrolled-continuous', 'scrolled-doc', 'auto']],
            'rendition:viewport' => ['metaKey' => 'epubRenditionViewport', 'allowed' => []],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dublinCoreMetadataRecord(\DOMElement $entry, string $name, string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        $record = [
            'element' => $name,
            'value' => $text,
        ];
        $id = trim($entry->getAttribute('id'));
        if ($id !== '') {
            $record['id'] = $id;
        }
        $direction = strtolower(trim($entry->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $record['dir'] = $direction;
        }
        $language = trim($entry->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language !== '') {
            $record['lang'] = $language;
        }

        foreach ($entry->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            $localName = $attribute->localName;
            $isOpfAttribute = $attribute->namespaceURI === 'http://www.idpf.org/2007/opf'
                || $attribute->prefix === 'opf'
                || in_array($localName, ['file-as', 'role', 'scheme', 'authority', 'term'], true);
            if (!$isOpfAttribute) {
                continue;
            }
            $value = trim($attribute->value);
            if ($value === '') {
                continue;
            }
            match ($localName) {
                'file-as' => $record['fileAs'] = $value,
                'role' => $record['role'] = $value,
                'scheme' => $record['scheme'] = $value,
                'authority' => $record['authority'] = $value,
                'term' => $record['term'] = $value,
                default => null,
            };
        }

        return $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function metadataLink(\DOMElement $link, string $base_path): ?array
    {
        $href = html_entity_decode(trim($link->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($href === '') {
            return null;
        }

        $entry = [
            'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
        ];
        foreach ([
            'rel' => 'rel',
            'hreflang' => 'hreflang',
            'mediaType' => 'media-type',
            'refines' => 'refines',
            'id' => 'id',
        ] as $key => $attribute) {
            $value = trim($link->getAttribute($attribute));
            if ($value !== '') {
                $entry[$key] = $value;
            }
        }
        $language = trim($link->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($link->getAttribute('lang'));
        }
        if ($language !== '') {
            $entry['lang'] = $language;
        }
        $direction = strtolower(trim($link->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $entry['dir'] = $direction;
        }

        $properties = array_values(array_filter(
            preg_split('/\s+/', trim($link->getAttribute('properties'))) ?: [],
            static fn (string $property): bool => $property !== ''
        ));
        if ($properties !== []) {
            $entry['properties'] = $properties;
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function assignRenditionMetadata(array &$meta, string $property, string $value): void
    {
        $property = strtolower(trim($property));
        $value = trim($value);
        if ($property === 'rendition:viewport') {
            $viewport = $this->parseViewportContent($value);
            if ($viewport !== null) {
                $meta['epubRenditionViewport'] ??= $viewport;
                $meta['epubViewport'] ??= $viewport;
            }
            return;
        }

        $value = strtolower($value);
        $key = match ($property) {
            'rendition:layout' => 'epubRenditionLayout',
            'rendition:orientation' => 'epubRenditionOrientation',
            'rendition:spread' => 'epubRenditionSpread',
            'rendition:flow' => 'epubRenditionFlow',
            default => '',
        };
        if ($key === '') {
            return;
        }
        $allowed = match ($key) {
            'epubRenditionLayout' => ['reflowable', 'pre-paginated'],
            'epubRenditionOrientation' => ['landscape', 'portrait', 'auto'],
            'epubRenditionSpread' => ['none', 'landscape', 'portrait', 'both', 'auto'],
            'epubRenditionFlow' => ['paginated', 'scrolled-continuous', 'scrolled-doc', 'auto'],
            default => [],
        };
        if (in_array($value, $allowed, true) && !isset($meta[$key])) {
            $meta[$key] = $value;
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function assignMediaMetadata(array &$meta, string $property, string $value): void
    {
        $property = strtolower(trim($property));
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $key = match ($property) {
            'media:duration' => 'epubMediaDuration',
            'media:narrator' => 'epubMediaNarrator',
            'media:active-class' => 'epubMediaActiveClass',
            'media:playback-active-class' => 'epubMediaPlaybackActiveClass',
            default => '',
        };
        if ($key !== '' && !isset($meta[$key])) {
            $meta[$key] = $value;
        }
    }

    /**
     * @param mixed $metadataProperties
     * @return array<string, array<string, mixed>>
     */
    private function mediaOverlayMetadata(mixed $metadataProperties): array
    {
        if (!is_array($metadataProperties)) {
            return [];
        }

        $metadata = [];
        foreach ($metadataProperties as $property) {
            if (!is_array($property)) {
                continue;
            }
            $name = strtolower(trim((string) ($property['property'] ?? '')));
            $config = $this->mediaPropertyConfig($name);
            if ($config === null) {
                continue;
            }
            $target = ltrim(trim((string) ($property['refines'] ?? '')), '#');
            if ($target === '') {
                continue;
            }
            $value = trim((string) ($property['value'] ?? $property['content'] ?? ''));
            if ($value === '') {
                continue;
            }

            $record = $property;
            $record['property'] = $name;
            $record['value'] = $value;
            $record['refines'] = '#' . $target;

            $metadata[$target]['metadataProperties'][] = $record;
            $metadata[$target][$config['key']] = $value;
        }

        return $metadata;
    }

    /**
     * @return array{key: string}|null
     */
    private function mediaPropertyConfig(string $property): ?array
    {
        return match ($property) {
            'media:duration' => ['key' => 'duration'],
            'media:narrator' => ['key' => 'narrator'],
            'media:active-class' => ['key' => 'activeClass'],
            'media:playback-active-class' => ['key' => 'playbackActiveClass'],
            default => null,
        };
    }

    /**
     * @param mixed $metadataProperties
     * @return array<string, array{metadataProperties?: list<array<string, mixed>>, renditionLayout?: string, renditionOrientation?: string, renditionSpread?: string, renditionFlow?: string, viewport?: array{width: int, height: int, content: string, properties?: array<string, string>}}>
     */
    private function spineRenditionMetadata(mixed $metadataProperties): array
    {
        if (!is_array($metadataProperties)) {
            return [];
        }

        $metadata = [];
        $conflicts = $this->scopedRenditionMetadataConflicts($metadataProperties);
        foreach ($metadataProperties as $property) {
            if (!is_array($property)) {
                continue;
            }
            $name = strtolower(trim((string) ($property['property'] ?? '')));
            if ($name === 'rendition:viewport') {
                $target = ltrim(trim((string) ($property['refines'] ?? '')), '#');
                if ($target === '') {
                    continue;
                }
                $value = trim((string) ($property['value'] ?? $property['content'] ?? ''));
                $viewport = $this->parseViewportContent($value);
                if ($viewport === null) {
                    continue;
                }

                $record = $property;
                $record['property'] = $name;
                $record['value'] = $viewport['content'];
                $record['refines'] = '#' . $target;

                $metadata[$target]['metadataProperties'][] = $record;
                if (!isset($conflicts[$target]['viewport'])) {
                    $metadata[$target]['viewport'] = $viewport;
                }
                continue;
            }
            $config = $this->renditionPropertyConfig($name);
            if ($config === null) {
                continue;
            }
            $target = trim((string) ($property['refines'] ?? ''));
            if ($target === '') {
                continue;
            }
            $target = ltrim($target, '#');
            if ($target === '') {
                continue;
            }
            $value = strtolower(trim((string) ($property['value'] ?? $property['content'] ?? '')));
            if (!in_array($value, $config['allowed'], true)) {
                continue;
            }

            $record = $property;
            $record['property'] = $name;
            $record['value'] = $value;
            $record['refines'] = '#' . $target;

            $metadata[$target]['metadataProperties'][] = $record;
            if (!isset($conflicts[$target][$config['key']])) {
                $metadata[$target][$config['key']] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function scopedRenditionMetadataConflicts(mixed $metadataProperties): array
    {
        $conflicts = [];
        foreach ($this->scopedRenditionMetadataValueGroups($metadataProperties) as $target => $properties) {
            foreach ($properties as $property => $group) {
                if (count($group['values'] ?? []) < 2) {
                    continue;
                }

                $key = $this->scopedRenditionMetadataKey($property);
                if ($key !== null) {
                    $conflicts[$target][$key] = true;
                }
            }
        }

        return $conflicts;
    }

    /**
     * @return array<string, array<string, array{values: array<string, true>, ids: list<string>, refines: list<string>}>>
     */
    private function scopedRenditionMetadataValueGroups(mixed $metadataProperties): array
    {
        if (!is_array($metadataProperties)) {
            return [];
        }

        $groups = [];
        foreach ($metadataProperties as $record) {
            if (!is_array($record)) {
                continue;
            }

            $refines = trim((string) ($record['refines'] ?? ''));
            if (!$this->validMetadataRefinesValue($refines)) {
                continue;
            }
            $target = substr($refines, 1);

            $property = strtolower(trim((string) ($record['property'] ?? '')));
            if ($property === '') {
                continue;
            }

            $value = trim((string) ($record['value'] ?? $record['content'] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($property === 'rendition:viewport') {
                $viewport = $this->parseViewportContent($value);
                if ($viewport === null) {
                    continue;
                }
                $value = $viewport['content'];
            } else {
                $config = $this->renditionPropertyConfig($property);
                if ($config === null) {
                    continue;
                }
                $value = strtolower($value);
                if (!in_array($value, $config['allowed'], true)) {
                    continue;
                }
            }

            $groups[$target][$property]['values'][$value] = true;
            $id = trim((string) ($record['id'] ?? ''));
            if ($id !== '' && !in_array($id, $groups[$target][$property]['ids'] ?? [], true)) {
                $groups[$target][$property]['ids'][] = $id;
            }
            if (!in_array($refines, $groups[$target][$property]['refines'] ?? [], true)) {
                $groups[$target][$property]['refines'][] = $refines;
            }
        }

        return $groups;
    }

    private function scopedRenditionMetadataKey(string $property): ?string
    {
        $property = strtolower(trim($property));
        if ($property === 'rendition:viewport') {
            return 'viewport';
        }

        $config = $this->renditionPropertyConfig($property);
        return $config['key'] ?? null;
    }

    /**
     * @return array{key: string, allowed: list<string>}|null
     */
    private function renditionPropertyConfig(string $property): ?array
    {
        return match ($property) {
            'rendition:layout' => ['key' => 'renditionLayout', 'allowed' => ['reflowable', 'pre-paginated']],
            'rendition:orientation' => ['key' => 'renditionOrientation', 'allowed' => ['landscape', 'portrait', 'auto']],
            'rendition:spread' => ['key' => 'renditionSpread', 'allowed' => ['none', 'landscape', 'portrait', 'both', 'auto']],
            'rendition:flow' => ['key' => 'renditionFlow', 'allowed' => ['paginated', 'scrolled-continuous', 'scrolled-doc', 'auto']],
            default => null,
        };
    }

    /**
     * @return array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}>
     */
    private function manifest(\DOMElement $package): array
    {
        $items = [];
        $manifestElement = $this->directOpfChildElement($package, 'manifest');
        if ($manifestElement === null) {
            return $items;
        }

        foreach ($this->directOpfChildElements($manifestElement, 'item') as $element) {
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
                'fallback' => trim($element->getAttribute('fallback')),
                'fallback-style' => trim($element->getAttribute('fallback-style')),
                'media-overlay' => trim($element->getAttribute('media-overlay')),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function packageDiagnostics(\ZipArchive $zip, \DOMElement $package, array $manifest, string $base_path, string $rootfile): array
    {
        $diagnostics = $this->packageRootDiagnostics($package);
        $diagnostics = array_merge($diagnostics, $this->packageMetadataDiagnostics($zip, $package, $manifest, $base_path, $rootfile));
        $declaredPrefixes = $this->packagePropertyPrefixNames($package);
        $diagnostics = array_merge($diagnostics, $this->packageAttributeDiagnostics($package));
        $diagnostics = array_merge($diagnostics, $this->packageDuplicateIdDiagnostics($package));
        $diagnostics = array_merge($diagnostics, $this->packageChildNamespaceDiagnostics($package));
        $diagnostics = array_merge($diagnostics, $this->packageDuplicateChildDiagnostics($package));
        $diagnostics = array_merge($diagnostics, $this->packageUnexpectedChildDiagnostics($package));
        $diagnostics = array_merge($diagnostics, $this->packageChildOrderDiagnostics($package));
        $diagnostics = array_merge($diagnostics, $this->renditionDiagnostics($package));
        $manifestElement = $this->directOpfChildElement($package, 'manifest');
        if ($manifestElement === null) {
            $diagnostics[] = $this->epubDiagnostic('error', 'missing-manifest', 'OPF package has no manifest element.');
        } else {
            $diagnostics = array_merge(
                $diagnostics,
                $this->manifestDiagnostics(
                    $zip,
                    $manifestElement,
                    $manifest,
                    $base_path,
                    $rootfile,
                    $this->packageVersionIsEpub3(trim($package->getAttribute('version'))),
                    $declaredPrefixes
                )
            );
            $diagnostics = array_merge($diagnostics, $this->mediaOverlayManifestDiagnostics($manifest));
        }
        $diagnostics = array_merge($diagnostics, $this->packageLinkAttributeDiagnostics($package, $declaredPrefixes));
        $diagnostics = array_merge($diagnostics, $this->packageLinkPackageDocumentReferenceDiagnostics($package, $base_path, $rootfile));
        $diagnostics = array_merge($diagnostics, $this->packageLinkResourceDiagnostics($zip, $package, $base_path, $rootfile));
        $diagnostics = array_merge($diagnostics, $this->packageLinkManifestResourceDiagnostics($package, $manifest, $base_path, $rootfile));
        $diagnostics = array_merge($diagnostics, $this->guideDiagnostics($zip, $package, $manifest, $base_path));
        $diagnostics = array_merge($diagnostics, $this->mediaOverlayResourceDiagnostics($zip, $manifest, $base_path));
        if ($this->packageVersionIsEpub3(trim($package->getAttribute('version')))) {
            $diagnostics = array_merge($diagnostics, $this->navResourceDiagnostics($zip, $manifest, $base_path));
        }

        $spineElement = $this->directOpfChildElement($package, 'spine');
        if ($spineElement === null) {
            $diagnostics[] = $this->epubDiagnostic('error', 'missing-spine', 'OPF package has no spine element.');
        } else {
            $diagnostics = array_merge($diagnostics, $this->spineDiagnostics($zip, $spineElement, $manifest, $declaredPrefixes, $base_path));
        }

        $diagnostics = array_merge($diagnostics, $this->bindingDiagnostics($package, $manifest));
        $diagnostics = array_merge($diagnostics, $this->collectionDiagnostics($package, $declaredPrefixes));

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageDuplicateIdDiagnostics(\DOMElement $package): array
    {
        $diagnostics = [];
        $seen = [];
        foreach (array_merge([$package], $this->primaryPackageDescendantElements($package)) as $element) {
            $id = trim($element->getAttribute('id'));
            if ($id === '' || !$this->validXmlId($id)) {
                continue;
            }

            $context = $this->packageIdDiagnosticContext($element, $id);
            if (isset($seen[$id])) {
                if ($this->sameLocalDuplicateIdScope($package, $seen[$id]['element'], $element)) {
                    continue;
                }

                $previousContext = $seen[$id]['context'];
                $duplicateContext = $context + ['previousElement' => $previousContext['element']];
                foreach (['parent', 'href', 'idref', 'property', 'rel', 'role', 'mediaType', 'handler', 'value'] as $key) {
                    if (isset($previousContext[$key])) {
                        $duplicateContext['previous' . ucfirst($key)] = $previousContext[$key];
                    }
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-package-id',
                    'OPF package XML id attributes must be unique across the package document.',
                    $duplicateContext
                );
                continue;
            }

            $seen[$id] = ['element' => $element, 'context' => $context];
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageIdDiagnosticContext(\DOMElement $element, string $id): array
    {
        $context = [
            'element' => $this->qualifiedName($element),
            'id' => $id,
        ];
        $parent = $element->parentNode;
        if ($parent instanceof \DOMElement) {
            $context['parent'] = $this->qualifiedName($parent);
        }
        foreach ([
            'href' => 'href',
            'idref' => 'idref',
            'property' => 'property',
            'rel' => 'rel',
            'role' => 'role',
            'mediaType' => 'media-type',
            'handler' => 'handler',
        ] as $key => $attribute) {
            $value = trim($element->getAttribute($attribute));
            if ($value !== '') {
                $context[$key] = $key === 'href'
                    ? html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8')
                    : $value;
            }
        }

        $value = $this->metadataElementText($element);
        if ($value !== '') {
            $context['value'] = $value;
        }

        return $context;
    }

    private function sameLocalDuplicateIdScope(\DOMElement $package, \DOMElement $first, \DOMElement $second): bool
    {
        $firstScope = $this->localDuplicateIdScope($package, $first);
        if ($firstScope === '') {
            return false;
        }

        return $firstScope === $this->localDuplicateIdScope($package, $second);
    }

    private function localDuplicateIdScope(\DOMElement $package, \DOMElement $element): string
    {
        $parent = $element->parentNode;
        if (!$parent instanceof \DOMElement) {
            return '';
        }

        $metadataElement = $this->directOpfChildElement($package, 'metadata');
        if ($metadataElement instanceof \DOMElement && $parent->isSameNode($metadataElement)) {
            return 'metadata';
        }

        $manifestElement = $this->directOpfChildElement($package, 'manifest');
        if ($manifestElement instanceof \DOMElement && $parent->isSameNode($manifestElement) && $this->isOpfPackageElement($element, 'item')) {
            return 'manifest';
        }

        $spineElement = $this->directOpfChildElement($package, 'spine');
        if ($spineElement instanceof \DOMElement && $parent->isSameNode($spineElement) && $this->isOpfPackageElement($element, 'itemref')) {
            return 'spine-itemref';
        }

        if ($this->isOpfPackageElement($element, 'collection')) {
            return 'collection';
        }

        $collection = $this->nearestOpfCollectionAncestor($element);
        if (!$collection instanceof \DOMElement) {
            return '';
        }

        if ($parent->isSameNode($collection) && $this->isOpfPackageElement($element, 'link')) {
            return 'collection-link:' . $this->nodePath($collection);
        }

        if (
            $this->isOpfPackageElement($parent, 'metadata')
            && $parent->parentNode instanceof \DOMElement
            && $parent->parentNode->isSameNode($collection)
            && in_array($element->localName, ['meta', 'link'], true)
        ) {
            return 'collection-metadata:' . $this->nodePath($parent);
        }

        return '';
    }

    private function nearestOpfCollectionAncestor(\DOMElement $element): ?\DOMElement
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMElement) {
            if ($this->isOpfPackageElement($node, 'collection')) {
                return $node;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    private function nodePath(\DOMElement $element): string
    {
        $path = $element->getNodePath();

        return is_string($path) && $path !== '' ? $path : $this->qualifiedName($element);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageRootDiagnostics(\DOMElement $package): array
    {
        $diagnostics = [];
        if ($package->localName !== 'package') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-root-element',
                'OPF package root element must be package.',
                [
                    'element' => $this->qualifiedName($package),
                    'localName' => $package->localName,
                    'expectedElement' => 'package',
                ]
            );
        }

        if (($package->namespaceURI ?? '') !== self::OPF_NAMESPACE) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-root-namespace',
                'OPF package root element must use the OPF namespace.',
                [
                    'element' => $this->qualifiedName($package),
                    'namespace' => $package->namespaceURI ?? '',
                    'expectedNamespace' => self::OPF_NAMESPACE,
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageChildNamespaceDiagnostics(\DOMElement $package): array
    {
        $diagnostics = [];
        foreach ($package->childNodes as $child) {
            if (!$child instanceof \DOMElement || !in_array($child->localName, ['manifest', 'spine'], true)) {
                continue;
            }
            if ($this->isOpfPackageElement($child, $child->localName)) {
                continue;
            }

            $code = $child->localName === 'manifest'
                ? 'invalid-package-manifest-namespace'
                : 'invalid-package-spine-namespace';
            $message = $child->localName === 'manifest'
                ? 'OPF package manifest elements must use the OPF namespace.'
                : 'OPF package spine elements must use the OPF namespace.';
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                $code,
                $message,
                [
                    'element' => $this->qualifiedName($child),
                    'namespace' => $child->namespaceURI ?? '',
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageDuplicateChildDiagnostics(\DOMElement $package): array
    {
        $diagnostics = [];
        foreach (['metadata', 'manifest', 'spine', 'guide', 'bindings'] as $localName) {
            $seen = 0;
            foreach ($this->directOpfChildElements($package, $localName) as $child) {
                $seen++;
                if ($seen === 1) {
                    continue;
                }

                $context = [
                    'element' => $this->qualifiedName($child),
                    'position' => $seen,
                ];
                $id = trim($child->getAttribute('id'));
                if ($id !== '') {
                    $context['id'] = $id;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-package-' . $localName,
                    'OPF package must not contain duplicate ' . $localName . ' elements.',
                    $context
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageUnexpectedChildDiagnostics(\DOMElement $package): array
    {
        $diagnostics = [];
        $allowed = ['metadata', 'manifest', 'spine', 'guide', 'bindings', 'collection'];
        foreach ($package->childNodes as $child) {
            if (!$child instanceof \DOMElement || in_array($child->localName, $allowed, true)) {
                continue;
            }
            if (($child->namespaceURI ?? '') !== self::OPF_NAMESPACE && ($child->namespaceURI ?? '') !== '') {
                continue;
            }

            $context = ['element' => $this->qualifiedName($child)];
            $id = trim($child->getAttribute('id'));
            if ($id !== '') {
                $context['id'] = $id;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-child-element',
                'OPF package contains an unexpected direct child element.',
                $context
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageChildOrderDiagnostics(\DOMElement $package): array
    {
        $order = [
            'metadata' => 0,
            'manifest' => 1,
            'spine' => 2,
            'guide' => 3,
            'bindings' => 4,
            'collection' => 5,
        ];
        $expectedOrder = array_keys($order);
        $diagnostics = [];
        $seenSingletons = [];
        $maxRank = -1;
        $maxContext = null;
        $position = 0;

        foreach ($package->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $position++;
            if (!array_key_exists($child->localName, $order) || !$this->isOpfPackageElement($child, $child->localName)) {
                continue;
            }
            if ($child->localName !== 'collection') {
                if (isset($seenSingletons[$child->localName])) {
                    continue;
                }
                $seenSingletons[$child->localName] = true;
            }

            $rank = $order[$child->localName];
            $context = [
                'element' => $this->qualifiedName($child),
                'position' => $position,
                'expectedOrder' => $expectedOrder,
            ];
            $id = trim($child->getAttribute('id'));
            if ($id !== '') {
                $context['id'] = $id;
            }

            if ($rank < $maxRank) {
                if (is_array($maxContext)) {
                    $context['previousElement'] = $maxContext['element'];
                    $context['previousPosition'] = $maxContext['position'];
                    if (isset($maxContext['id'])) {
                        $context['previousId'] = $maxContext['id'];
                    }
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-child-order',
                    'OPF package children must appear in content-model order: metadata, manifest, spine, guide, bindings, then collection.',
                    $context
                );
                continue;
            }

            if ($rank > $maxRank) {
                $maxRank = $rank;
                $maxContext = [
                    'element' => $this->qualifiedName($child),
                    'position' => $position,
                ];
                if ($id !== '') {
                    $maxContext['id'] = $id;
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageAttributeDiagnostics(\DOMElement $package): array
    {
        $diagnostics = [];

        $id = trim($package->getAttribute('id'));
        if ($id !== '' && !$this->validXmlId($id)) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-id',
                'OPF package id must be an XML NCName.',
                ['id' => $id]
            );
        }

        $direction = trim($package->getAttribute('dir'));
        if ($direction !== '' && !in_array(strtolower($direction), ['ltr', 'rtl', 'auto'], true)) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-dir',
                'OPF package dir attribute must be ltr, rtl, or auto.',
                ['value' => $direction]
            );
        }

        $language = trim($package->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language !== '' && !$this->validXmlLanguageTag($language)) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-language',
                'OPF package xml:lang attribute must be a valid language tag.',
                ['value' => $language]
            );
        }

        $uniqueIdentifierId = trim($package->getAttribute('unique-identifier'));
        if ($uniqueIdentifierId !== '') {
            if (!$this->validXmlId($uniqueIdentifierId)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-unique-identifier-idref',
                    'OPF unique-identifier must be an XML IDREF.',
                    ['id' => $uniqueIdentifierId]
                );
            }

            $target = $this->packageElementById($package, $uniqueIdentifierId);
            $metadataIdentifier = $this->packageUniqueIdentifierElement($package, $uniqueIdentifierId);
            if (!$target instanceof \DOMElement && !$metadataIdentifier instanceof \DOMElement) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-unique-identifier',
                    'OPF unique-identifier does not match any package element id.',
                    ['id' => $uniqueIdentifierId]
                );
            } elseif (!$metadataIdentifier instanceof \DOMElement) {
                $context = [
                    'id' => $uniqueIdentifierId,
                ];
                if ($target instanceof \DOMElement) {
                    $context['target'] = $this->qualifiedName($target);
                    $context['reason'] = $target->localName === 'identifier' && $target->namespaceURI === self::DC_NAMESPACE
                        ? 'not-package-metadata'
                        : 'not-dc-identifier';
                    $parent = $target->parentNode;
                    if ($parent instanceof \DOMElement) {
                        $context['parent'] = $this->qualifiedName($parent);
                    }
                    if (($target->namespaceURI ?? '') !== '') {
                        $context['namespace'] = $target->namespaceURI;
                    }
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-unique-identifier-target',
                    'OPF unique-identifier must reference a package metadata dc:identifier element.',
                    $context
                );
            } elseif ($this->metadataElementText($metadataIdentifier) === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'empty-unique-identifier',
                    'OPF unique-identifier must reference a non-empty dc:identifier element.',
                    [
                        'id' => $uniqueIdentifierId,
                        'target' => $this->qualifiedName($metadataIdentifier),
                    ]
                );
            }
        }

        $prefix = trim(preg_replace('/\s+/', ' ', $package->getAttribute('prefix')) ?? $package->getAttribute('prefix'));
        if ($prefix !== '') {
            $diagnostics = array_merge($diagnostics, $this->packagePrefixDiagnostics($prefix));
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packagePrefixDiagnostics(string $prefix): array
    {
        $diagnostics = [];
        $seenPrefixes = [];
        $reservedPrefixes = $this->packageReservedPrefixIris();
        $tokens = preg_split('/\s+/', $prefix) ?: [];
        for ($i = 0, $count = count($tokens); $i < $count; $i += 2) {
            $prefixToken = $tokens[$i] ?? '';
            $iri = $tokens[$i + 1] ?? '';
            $context = [
                'declaration' => trim($prefixToken . ' ' . $iri),
            ];

            if (!str_ends_with($prefixToken, ':')) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-prefix',
                    'OPF package prefix declarations must use prefix: IRI pairs.',
                    $context + ['prefix' => $prefixToken]
                );
                continue;
            }

            $name = substr($prefixToken, 0, -1);
            if ($name === '' || !$this->validXmlId($name)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-prefix-name',
                    'OPF package prefix declaration names must be XML NCNames.',
                    $context + ['prefix' => $name]
                );
            } else {
                if ($name === '_') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'reserved-package-prefix',
                        'OPF package prefix "_" is reserved and must not be declared.',
                        $context + ['prefix' => $name]
                    );
                }

                if (
                    isset($reservedPrefixes[$name])
                    && $iri !== ''
                    && $this->absoluteIriLike($iri)
                    && !in_array($iri, $reservedPrefixes[$name], true)
                ) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'warning',
                        'overridden-package-prefix',
                        'OPF package prefix declaration remaps a reserved EPUB prefix.',
                        $context + ['prefix' => $name, 'iri' => $iri, 'reservedIris' => $reservedPrefixes[$name]]
                    );
                }

                if (array_key_exists($name, $seenPrefixes)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-package-prefix',
                        'OPF package prefix declarations must not repeat a prefix name.',
                        $context + ['prefix' => $name, 'firstIri' => $seenPrefixes[$name]]
                    );
                } else {
                    $seenPrefixes[$name] = $iri;
                }
            }

            if ($iri === '' || !$this->absoluteIriLike($iri)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-prefix-iri',
                    'OPF package prefix declaration IRIs must be absolute IRIs.',
                    $context + ['iri' => $iri]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, list<string>>
     */
    private function packageReservedPrefixIris(): array
    {
        return [
            'a11y' => ['http://www.idpf.org/epub/vocab/package/a11y/#'],
            'dcterms' => ['http://purl.org/dc/terms/'],
            'marc' => ['http://id.loc.gov/vocabulary/'],
            'media' => ['http://www.idpf.org/epub/vocab/overlays/#'],
            'onix' => ['http://www.editeur.org/ONIX/book/codelists/current.html#'],
            'rendition' => ['http://www.idpf.org/vocab/rendition/#'],
            'schema' => ['http://schema.org/', 'https://schema.org/'],
            'xsd' => ['http://www.w3.org/2001/XMLSchema#'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function renditionDiagnostics(\DOMElement $package): array
    {
        $diagnostics = [];
        $metadataElement = $this->directOpfChildElement($package, 'metadata');
        if ($metadataElement !== null) {
            foreach ($this->directOpfChildElements($metadataElement, 'meta') as $child) {

                $property = strtolower(trim($child->getAttribute('property')));
                if (!str_starts_with($property, 'rendition:')) {
                    continue;
                }

                $value = trim($child->getAttribute('content'));
                if ($value === '') {
                    $value = trim(preg_replace('/\s+/u', ' ', $child->textContent) ?? $child->textContent);
                }
                $context = $this->renditionMetadataDiagnosticContext($child, $property, $value);

                if ($property === 'rendition:viewport') {
                    if ($value === '' || $this->parseViewportContent($value) === null) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-rendition-viewport',
                            'OPF rendition:viewport metadata must include positive width and height values.',
                            $context
                        );
                    }
                    continue;
                }

                $config = $this->renditionPropertyConfig($property);
                if ($config === null) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-rendition-metadata-property',
                        'OPF rendition metadata uses an unsupported rendition property.',
                        $context
                    );
                    continue;
                }

                if ($value === '' || !in_array(strtolower($value), $config['allowed'], true)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-rendition-metadata',
                        'OPF rendition metadata value is not allowed for its property.',
                        $context + ['allowed' => $config['allowed']]
                    );
                }
            }
            array_push($diagnostics, ...$this->conflictingPackageRenditionMetadataDiagnostics($metadataElement));
            array_push($diagnostics, ...$this->conflictingScopedRenditionMetadataDiagnostics($metadataElement));
            array_push($diagnostics, ...$this->conflictingSpineScopedRenditionMetadataDiagnostics($package, $metadataElement));
        }

        $spineElement = $this->directOpfChildElement($package, 'spine');
        if ($spineElement !== null) {
            foreach ($this->directOpfChildElements($spineElement, 'itemref') as $itemref) {
                foreach ($this->attributeTokenList($itemref, 'properties') as $property) {
                    $diagnostic = $this->renditionSpinePropertyDiagnostic($itemref, $property);
                    if ($diagnostic !== null) {
                        $diagnostics[] = $diagnostic;
                    }
                }
                array_push($diagnostics, ...$this->conflictingRenditionSpinePropertyDiagnostics($itemref));
                if ($metadataElement !== null) {
                    array_push(
                        $diagnostics,
                        ...$this->conflictingItemrefRenditionMetadataDiagnostics(
                            $itemref,
                            $this->scopedRenditionMetadataValueGroups($this->scopedRenditionMetadataRecords($metadataElement))
                        )
                    );
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function renditionMetadataDiagnosticContext(\DOMElement $element, string $property, string $value): array
    {
        $context = [
            'element' => $this->qualifiedName($element),
            'property' => $property,
        ];
        foreach (['id', 'refines', 'scheme'] as $attribute) {
            $attributeValue = trim($element->getAttribute($attribute));
            if ($attributeValue !== '') {
                $context[$attribute] = $attributeValue;
            }
        }
        if ($value !== '') {
            $context['value'] = $value;
        }

        return $context;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conflictingPackageRenditionMetadataDiagnostics(\DOMElement $metadataElement): array
    {
        $records = [];
        foreach ($this->directOpfChildElements($metadataElement, 'meta') as $child) {
            $property = strtolower(trim($child->getAttribute('property')));
            if (!str_starts_with($property, 'rendition:') || trim($child->getAttribute('refines')) !== '') {
                continue;
            }

            $value = trim($child->getAttribute('content'));
            if ($value === '') {
                $value = trim(preg_replace('/\s+/u', ' ', $child->textContent) ?? $child->textContent);
            }
            $records[] = $this->renditionMetadataRecord($child, $property, $value);
        }

        $diagnostics = [];
        foreach ($this->packageRenditionMetadataValueGroups($records) as $property => $group) {
            $values = array_keys($group['values'] ?? []);
            if (count($values) < 2) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'conflicting-rendition-metadata',
                'OPF package metadata contains mutually exclusive rendition metadata values.',
                [
                    'property' => $property,
                    'values' => $values,
                    'ids' => array_values($group['ids'] ?? []),
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conflictingScopedRenditionMetadataDiagnostics(\DOMElement $metadataElement): array
    {
        $diagnostics = [];
        foreach ($this->scopedRenditionMetadataValueGroups($this->scopedRenditionMetadataRecords($metadataElement)) as $target => $properties) {
            foreach ($properties as $property => $group) {
                $values = array_keys($group['values'] ?? []);
                if (count($values) < 2) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'conflicting-rendition-metadata-refines',
                    'OPF rendition metadata refinement contains mutually exclusive values for one target.',
                    [
                        'target' => $target,
                        'property' => $property,
                        'values' => $values,
                        'ids' => array_values($group['ids'] ?? []),
                        'refines' => array_values($group['refines'] ?? []),
                    ]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conflictingSpineScopedRenditionMetadataDiagnostics(\DOMElement $package, \DOMElement $metadataElement): array
    {
        $spineElement = $this->directOpfChildElement($package, 'spine');
        if ($spineElement === null) {
            return [];
        }

        $targetGroups = $this->scopedRenditionMetadataValueGroups($this->scopedRenditionMetadataRecords($metadataElement));
        $diagnostics = [];
        foreach ($this->directOpfChildElements($spineElement, 'itemref') as $itemref) {
            $targets = $this->spineItemRefRenditionTargets($itemref);
            if (count($targets) < 2) {
                continue;
            }

            foreach ($this->spineScopedRenditionMetadataPropertyGroups($targets, $targetGroups) as $property => $group) {
                $values = array_keys($group['values'] ?? []);
                $targetsWithValues = array_values($group['targets'] ?? []);
                if (count($values) < 2 || count($targetsWithValues) < 2) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'conflicting-rendition-spine-metadata',
                    'OPF rendition metadata refinements for one spine item contain mutually exclusive values.',
                    $this->conflictingSpineScopedRenditionMetadataDiagnosticContext(
                        $itemref,
                        $property,
                        $values,
                        $targetsWithValues,
                        array_values($group['ids'] ?? []),
                        array_values($group['refines'] ?? [])
                    )
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array<string, array{values: array<string, true>, ids: list<string>, refines: list<string>}>> $targetGroups
     * @return list<array<string, mixed>>
     */
    private function conflictingItemrefRenditionMetadataDiagnostics(\DOMElement $itemref, array $targetGroups): array
    {
        $itemrefGroups = $this->spineItemRenditionPropertyValueGroups($this->attributeTokenList($itemref, 'properties'));
        if ($itemrefGroups === []) {
            return [];
        }

        $metadataGroups = $this->spineScopedRenditionMetadataPropertyGroups(
            $this->spineItemRefRenditionTargets($itemref),
            $targetGroups
        );

        $diagnostics = [];
        foreach ($itemrefGroups as $key => $itemrefGroup) {
            $itemrefValues = array_keys($itemrefGroup['values'] ?? []);
            if (count($itemrefValues) !== 1) {
                continue;
            }

            $propertyGroup = $this->renditionSpinePropertyGroupName($key);
            $metadataGroup = $metadataGroups[$propertyGroup] ?? null;
            if (!is_array($metadataGroup)) {
                continue;
            }

            $metadataValues = array_keys($metadataGroup['values'] ?? []);
            if (count($metadataValues) !== 1 || $metadataValues[0] === $itemrefValues[0]) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'conflicting-rendition-itemref-metadata',
                'OPF spine itemref rendition property conflicts with scoped rendition metadata.',
                $this->conflictingItemrefRenditionMetadataDiagnosticContext(
                    $itemref,
                    $propertyGroup,
                    array_values($itemrefGroup['properties'] ?? []),
                    $itemrefValues[0],
                    $metadataValues[0],
                    array_values($metadataGroup['targets'] ?? []),
                    array_values($metadataGroup['ids'] ?? []),
                    array_values($metadataGroup['refines'] ?? [])
                )
            );
        }

        return $diagnostics;
    }

    /**
     * @param list<string> $itemrefProperties
     * @param list<string> $metadataTargets
     * @param list<string> $metadataIds
     * @param list<string> $metadataRefines
     * @return array<string, mixed>
     */
    private function conflictingItemrefRenditionMetadataDiagnosticContext(
        \DOMElement $itemref,
        string $propertyGroup,
        array $itemrefProperties,
        string $itemrefValue,
        string $metadataValue,
        array $metadataTargets,
        array $metadataIds,
        array $metadataRefines
    ): array {
        $context = [
            'element' => $this->qualifiedName($itemref),
            'propertyGroup' => $propertyGroup,
            'itemrefProperties' => $itemrefProperties,
            'itemrefValue' => $itemrefValue,
            'metadataValue' => $metadataValue,
            'metadataTargets' => $metadataTargets,
            'metadataRefines' => $metadataRefines,
        ];
        foreach (['id', 'idref'] as $attribute) {
            $attributeValue = trim($itemref->getAttribute($attribute));
            if ($attributeValue !== '') {
                $context[$attribute] = $attributeValue;
            }
        }
        if ($metadataIds !== []) {
            $context['metadataIds'] = $metadataIds;
        }

        return $context;
    }

    /**
     * @param list<string> $targets
     * @param array<string, array<string, array{values: array<string, true>, ids: list<string>, refines: list<string>}>> $targetGroups
     * @return array<string, array{values: array<string, true>, targets: list<string>, ids: list<string>, refines: list<string>}>
     */
    private function spineScopedRenditionMetadataPropertyGroups(array $targets, array $targetGroups): array
    {
        $groups = [];
        foreach ($targets as $target) {
            foreach ($targetGroups[$target] ?? [] as $property => $group) {
                if (($group['values'] ?? []) === []) {
                    continue;
                }

                foreach (array_keys($group['values']) as $value) {
                    $groups[$property]['values'][$value] = true;
                }
                if (!in_array($target, $groups[$property]['targets'] ?? [], true)) {
                    $groups[$property]['targets'][] = $target;
                }
                foreach ($group['ids'] ?? [] as $id) {
                    if (!in_array($id, $groups[$property]['ids'] ?? [], true)) {
                        $groups[$property]['ids'][] = $id;
                    }
                }
                foreach ($group['refines'] ?? [] as $refines) {
                    if (!in_array($refines, $groups[$property]['refines'] ?? [], true)) {
                        $groups[$property]['refines'][] = $refines;
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * @param list<string> $values
     * @param list<string> $targets
     * @param list<string> $ids
     * @param list<string> $refines
     * @return array<string, mixed>
     */
    private function conflictingSpineScopedRenditionMetadataDiagnosticContext(
        \DOMElement $itemref,
        string $property,
        array $values,
        array $targets,
        array $ids,
        array $refines
    ): array {
        $context = [
            'element' => $this->qualifiedName($itemref),
            'property' => $property,
            'values' => $values,
            'targets' => $targets,
            'refines' => $refines,
        ];
        foreach (['id', 'idref'] as $attribute) {
            $attributeValue = trim($itemref->getAttribute($attribute));
            if ($attributeValue !== '') {
                $context[$attribute] = $attributeValue;
            }
        }
        if ($ids !== []) {
            $context['ids'] = $ids;
        }

        return $context;
    }

    /**
     * @return list<string>
     */
    private function spineItemRefRenditionTargets(\DOMElement $itemref): array
    {
        $targets = [];
        foreach (['id', 'idref'] as $attribute) {
            $target = trim($itemref->getAttribute($attribute));
            if ($target !== '' && !in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scopedRenditionMetadataRecords(\DOMElement $metadataElement): array
    {
        $records = [];
        foreach ($this->directOpfChildElements($metadataElement, 'meta') as $child) {
            $property = strtolower(trim($child->getAttribute('property')));
            if (!str_starts_with($property, 'rendition:') || trim($child->getAttribute('refines')) === '') {
                continue;
            }

            $value = trim($child->getAttribute('content'));
            if ($value === '') {
                $value = trim(preg_replace('/\s+/u', ' ', $child->textContent) ?? $child->textContent);
            }
            $records[] = $this->renditionMetadataRecord($child, $property, $value);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function renditionMetadataRecord(\DOMElement $element, string $property, string $value): array
    {
        $record = [
            'property' => $property,
            'value' => $value,
        ];
        foreach (['id', 'refines', 'scheme'] as $attribute) {
            $attributeValue = trim($element->getAttribute($attribute));
            if ($attributeValue !== '') {
                $record[$attribute] = $attributeValue;
            }
        }

        return $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function renditionSpinePropertyDiagnostic(\DOMElement $itemref, string $property): ?array
    {
        $lower = strtolower(trim($property));
        if ($lower === '') {
            return null;
        }

        foreach ($this->renditionSpinePropertyDiagnosticConfig() as $prefix => $allowed) {
            if (!str_starts_with($lower, $prefix)) {
                continue;
            }

            $value = substr($lower, strlen($prefix));
            if ($value !== '' && in_array($value, $allowed, true)) {
                return null;
            }

            return $this->epubDiagnostic(
                'error',
                'invalid-rendition-spine-property',
                'OPF spine itemref uses an unsupported rendition property token.',
                $this->renditionSpinePropertyDiagnosticContext($itemref, $property, $value, $allowed)
            );
        }

        if (str_starts_with($lower, 'rendition:')) {
            return $this->epubDiagnostic(
                'error',
                'invalid-rendition-spine-property',
                'OPF spine itemref uses an unsupported rendition property token.',
                $this->renditionSpinePropertyDiagnosticContext($itemref, $property, '', [])
            );
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function renditionSpinePropertyDiagnosticConfig(): array
    {
        return [
            'rendition:page-spread-' => ['left', 'right', 'center'],
            'page-spread-' => ['left', 'right', 'center'],
            'rendition:layout-' => ['reflowable', 'pre-paginated'],
            'rendition:orientation-' => ['landscape', 'portrait', 'auto'],
            'rendition:spread-' => ['none', 'landscape', 'portrait', 'both', 'auto'],
            'rendition:flow-' => ['paginated', 'scrolled-continuous', 'scrolled-doc', 'auto'],
        ];
    }

    /**
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private function renditionSpinePropertyDiagnosticContext(\DOMElement $itemref, string $property, string $value, array $allowed): array
    {
        $context = [
            'element' => $this->qualifiedName($itemref),
            'property' => $property,
        ];
        foreach (['id', 'idref'] as $attribute) {
            $attributeValue = trim($itemref->getAttribute($attribute));
            if ($attributeValue !== '') {
                $context[$attribute] = $attributeValue;
            }
        }
        if ($value !== '') {
            $context['value'] = $value;
        }
        if ($allowed !== []) {
            $context['allowed'] = $allowed;
        }

        return $context;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conflictingRenditionSpinePropertyDiagnostics(\DOMElement $itemref): array
    {
        $groups = [];
        foreach ($this->attributeTokenList($itemref, 'properties') as $property) {
            $entry = $this->spineItemRenditionProperty(strtolower(trim($property)));
            if ($entry === null) {
                continue;
            }

            [$key, $value] = $entry;
            $groups[$key]['values'][$value] = true;
            $groups[$key]['properties'][] = $property;
        }

        $diagnostics = [];
        foreach ($groups as $key => $group) {
            $values = array_keys($group['values'] ?? []);
            if (count($values) < 2) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'conflicting-rendition-spine-property',
                'OPF spine itemref contains mutually exclusive rendition property values.',
                $this->conflictingRenditionSpinePropertyDiagnosticContext(
                    $itemref,
                    $this->renditionSpinePropertyGroupName($key),
                    array_values($group['properties'] ?? []),
                    $values
                )
            );
        }

        return $diagnostics;
    }

    /**
     * @param list<string> $properties
     * @param list<string> $values
     * @return array<string, mixed>
     */
    private function conflictingRenditionSpinePropertyDiagnosticContext(\DOMElement $itemref, string $propertyGroup, array $properties, array $values): array
    {
        $context = [
            'element' => $this->qualifiedName($itemref),
            'propertyGroup' => $propertyGroup,
            'properties' => $properties,
            'values' => $values,
        ];
        foreach (['id', 'idref'] as $attribute) {
            $attributeValue = trim($itemref->getAttribute($attribute));
            if ($attributeValue !== '') {
                $context[$attribute] = $attributeValue;
            }
        }

        return $context;
    }

    private function renditionSpinePropertyGroupName(string $key): string
    {
        return match ($key) {
            'pageSpread' => 'rendition:page-spread',
            'renditionLayout' => 'rendition:layout',
            'renditionOrientation' => 'rendition:orientation',
            'renditionSpread' => 'rendition:spread',
            'renditionFlow' => 'rendition:flow',
            default => $key,
        };
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function packageMetadataDiagnostics(\ZipArchive $zip, \DOMElement $package, array $manifest, string $base_path, string $rootfile): array
    {
        $diagnostics = [];
        $version = trim($package->getAttribute('version'));
        if ($version === '') {
            $diagnostics[] = $this->epubDiagnostic('error', 'missing-package-version', 'OPF package is missing a version attribute.');
        } elseif (preg_match('/^\d+(?:\.\d+)?$/', $version) !== 1) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-version',
                'OPF package version is not a numeric EPUB version.',
                ['version' => $version]
            );
        } elseif (!in_array($version, ['2.0', '3.0'], true)) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'unsupported-package-version',
                'OPF package version must be 2.0 or 3.0.',
                ['version' => $version, 'supported' => ['2.0', '3.0']]
            );
        }

        foreach ($package->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'metadata' || $this->isOpfPackageElement($child, 'metadata')) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-metadata-namespace',
                'OPF package metadata elements must use the OPF namespace.',
                [
                    'element' => $this->qualifiedName($child),
                    'namespace' => $child->namespaceURI ?? '',
                ]
            );
        }

        $metadataElement = $this->directOpfChildElement($package, 'metadata');
        $isEpub3Package = $this->packageVersionIsEpub3($version);
        if ($metadataElement === null) {
            $diagnostics[] = $this->epubDiagnostic('error', 'missing-metadata', 'OPF package has no metadata element.');
            $diagnostics[] = $this->epubDiagnostic('error', 'missing-identifier', 'OPF metadata has no dc:identifier element.');
            $diagnostics[] = $this->epubDiagnostic('error', 'missing-title', 'OPF metadata has no dc:title element.');
            $diagnostics[] = $this->epubDiagnostic('error', 'missing-language', 'OPF metadata has no dc:language element.');
            if ($isEpub3Package) {
                $diagnostics[] = $this->epubDiagnostic('error', 'missing-package-modified', 'EPUB3 package metadata has no dcterms:modified meta element.');
            }
        } else {
            foreach ([
                'identifier' => 'missing-identifier',
                'title' => 'missing-title',
                'language' => 'missing-language',
            ] as $element => $code) {
                if (!$this->requiredDublinCoreMetadataElementHasText($metadataElement, $element)) {
                    $diagnostics[] = $this->epubDiagnostic('error', $code, 'OPF metadata is missing a required Dublin Core element.');
                }
            }
            $diagnostics = array_merge($diagnostics, $this->requiredMetadataNamespaceDiagnostics($metadataElement));
            $diagnostics = array_merge($diagnostics, $this->metadataLanguageDiagnostics($metadataElement));
            $diagnostics = array_merge($diagnostics, $this->metadataDateCardinalityDiagnostics($metadataElement));
            $diagnostics = array_merge($diagnostics, $this->metadataXmlLanguageDiagnostics($metadataElement));
            $diagnostics = array_merge($diagnostics, $this->metadataDirectionDiagnostics($metadataElement));
            $diagnostics = array_merge($diagnostics, $this->metadataIdentityDiagnostics($metadataElement));
            if ($isEpub3Package) {
                $diagnostics = array_merge($diagnostics, $this->packageMetadataElementDiagnostics($package, $metadataElement));
                $diagnostics = array_merge($diagnostics, $this->packageMetadataValueDiagnostics($metadataElement));
                $diagnostics = array_merge($diagnostics, $this->modifiedMetadataDiagnostics($metadataElement));
                $diagnostics = array_merge($diagnostics, $this->mediaOverlayMetadataDiagnostics($metadataElement, $manifest));
            }
        }

        if (trim($package->getAttribute('unique-identifier')) === '') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-package-unique-identifier',
                'OPF package is missing a unique-identifier attribute.'
            );
        }

        $diagnostics = array_merge($diagnostics, $this->metadataRefinesDiagnostics($zip, $package, $base_path, $rootfile));

        if ($isEpub3Package) {
            $diagnostics = array_merge($diagnostics, $this->navDocumentDiagnostics($manifest));
        }

        return $diagnostics;
    }

    private function packageVersionIsEpub3(string $version): bool
    {
        return $version === '' || preg_match('/^3(?:\.\d+)?$/', $version) === 1;
    }

    private function requiredDublinCoreMetadataElementHasText(\DOMElement $metadataElement, string $localName): bool
    {
        foreach ($this->directChildElements($metadataElement, $localName) as $element) {
            if ($element->namespaceURI === self::DC_NAMESPACE && $this->metadataElementText($element) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requiredMetadataNamespaceDiagnostics(\DOMElement $metadataElement): array
    {
        $diagnostics = [];
        foreach (['identifier', 'title', 'language'] as $localName) {
            foreach ($this->directChildElements($metadataElement, $localName) as $element) {
                if ($element->namespaceURI === self::DC_NAMESPACE || $this->metadataElementText($element) === '') {
                    continue;
                }

                $context = [
                    'element' => $this->qualifiedName($element),
                    'name' => $localName,
                    'value' => $this->metadataElementText($element),
                ];
                $id = trim($element->getAttribute('id'));
                if ($id !== '') {
                    $context['id'] = $id;
                }
                if (($element->namespaceURI ?? '') !== '') {
                    $context['namespace'] = $element->namespaceURI;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-required-metadata-namespace',
                    'OPF required Dublin Core metadata elements must use the Dublin Core namespace.',
                    $context
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageMetadataElementDiagnostics(\DOMElement $package, \DOMElement $metadataElement): array
    {
        $diagnostics = [];
        $declaredPrefixes = $this->packagePropertyPrefixNames($package);
        $allowedDublinCoreElements = array_flip($this->packageDublinCoreMetadataElementNames());
        foreach ($metadataElement->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            if ($element->namespaceURI === self::DC_NAMESPACE) {
                if (!isset($allowedDublinCoreElements[$element->localName])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-metadata-dublin-core-element',
                        'EPUB3 package metadata Dublin Core elements must use the package metadata Dublin Core vocabulary.',
                        $this->packageMetadataChildDiagnosticContext($element) + [
                            'name' => $element->localName,
                            'allowed' => $this->packageDublinCoreMetadataElementNames(),
                        ]
                    );
                }
                continue;
            }

            if ($this->isOpfPackageElement($element, 'meta') || $this->isOpfPackageElement($element, 'link')) {
                continue;
            }

            if (in_array($element->localName, ['meta', 'link'], true)) {
                continue;
            }

            if (in_array($element->localName, ['identifier', 'title', 'language'], true) && $this->metadataElementText($element) !== '') {
                continue;
            }

            $context = $this->packageMetadataChildDiagnosticContext($element);
            if (($element->namespaceURI ?? '') !== '') {
                $context['namespace'] = $element->namespaceURI;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-metadata-child-element',
                'EPUB3 package metadata must contain only Dublin Core, meta, and link child elements.',
                $context
            );
        }

        foreach ($metadataElement->childNodes as $element) {
            if (!$element instanceof \DOMElement || !in_array($element->localName, ['meta', 'link'], true)) {
                continue;
            }
            if ($this->isOpfPackageElement($element, $element->localName)) {
                continue;
            }

            $code = $element->localName === 'meta'
                ? 'invalid-package-meta-namespace'
                : 'invalid-package-link-namespace';
            $message = $element->localName === 'meta'
                ? 'EPUB3 package meta elements must use the OPF namespace.'
                : 'EPUB3 package link elements must use the OPF namespace.';
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                $code,
                $message,
                $this->packageMetadataChildDiagnosticContext($element) + ['namespace' => $element->namespaceURI ?? '']
            );
        }

        foreach ($this->directOpfChildElements($metadataElement, 'meta') as $element) {
            $property = trim($element->getAttribute('property'));
            $name = trim($element->getAttribute('name'));
            $scheme = trim($element->getAttribute('scheme'));
            $context = $this->packageMetadataChildDiagnosticContext($element);

            if ($property !== '') {
                if ($name !== '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-opf2-meta',
                        'EPUB3 package metadata must not include OPF2-style meta elements.',
                        $context + ['name' => $name]
                    );
                }
                if (!$this->validPropertyValue($property)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-meta-property',
                        'EPUB3 package meta property attributes must be valid property data type values.',
                        $context + ['property' => $property]
                    );
                } elseif (!$this->propertyValuePrefixIsDeclared($property, $declaredPrefixes)) {
                    [$propertyPrefix] = explode(':', $property, 2);
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-package-meta-property-prefix',
                        'EPUB3 package meta property prefix must be reserved or declared in package prefix.',
                        $context + ['property' => $property, 'prefix' => $propertyPrefix]
                    );
                }
                if ($scheme !== '') {
                    if (!$this->validPropertyValue($scheme)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-package-meta-scheme',
                            'EPUB3 package meta scheme attributes must be valid property data type values.',
                            $context + ['property' => $property, 'scheme' => $scheme]
                        );
                    } elseif (!$this->propertyValuePrefixIsDeclared($scheme, $declaredPrefixes)) {
                        [$schemePrefix] = explode(':', $scheme, 2);
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'undeclared-package-meta-scheme-prefix',
                            'EPUB3 package meta scheme prefix must be reserved or declared in package prefix.',
                            $context + ['property' => $property, 'scheme' => $scheme, 'prefix' => $schemePrefix]
                        );
                    }
                }
                continue;
            }
            if ($scheme !== '') {
                if (!$this->validPropertyValue($scheme)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-meta-scheme',
                        'EPUB3 package meta scheme attributes must be valid property data type values.',
                        $context + ['scheme' => $scheme]
                    );
                } elseif (!$this->propertyValuePrefixIsDeclared($scheme, $declaredPrefixes)) {
                    [$schemePrefix] = explode(':', $scheme, 2);
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-package-meta-scheme-prefix',
                        'EPUB3 package meta scheme prefix must be reserved or declared in package prefix.',
                        $context + ['scheme' => $scheme, 'prefix' => $schemePrefix]
                    );
                }
            }

            if ($name === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-package-meta-property',
                    'EPUB3 package meta elements must include a property attribute.',
                    $context
                );
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-opf2-meta',
                'EPUB3 package metadata must not include OPF2-style meta elements.',
                $context + ['name' => $name]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageMetadataValueDiagnostics(\DOMElement $metadataElement): array
    {
        $diagnostics = [];
        $requiredDublinCoreElements = [
            'identifier' => true,
            'title' => true,
            'language' => true,
        ];
        $allowedDublinCoreElements = array_flip($this->packageDublinCoreMetadataElementNames());
        $specializedMetaProperties = [
            'dcterms:modified' => true,
            'media:duration' => true,
        ];

        foreach ($metadataElement->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            if ($element->namespaceURI === self::DC_NAMESPACE && isset($allowedDublinCoreElements[$element->localName])) {
                if ($this->metadataElementText($element) !== '') {
                    continue;
                }
                if (
                    isset($requiredDublinCoreElements[$element->localName])
                    && !$this->requiredDublinCoreMetadataElementHasText($metadataElement, $element->localName)
                ) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'empty-package-metadata-value',
                    'EPUB3 package metadata values must not be empty.',
                    $this->packageMetadataChildDiagnosticContext($element) + ['name' => $element->localName]
                );
                continue;
            }

            if (!$this->isOpfPackageElement($element, 'meta')) {
                continue;
            }

            $property = trim($element->getAttribute('property'));
            if ($property === '' || isset($specializedMetaProperties[strtolower($property)]) || $this->metadataElementText($element) !== '') {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'empty-package-metadata-value',
                'EPUB3 package metadata values must not be empty.',
                $this->packageMetadataChildDiagnosticContext($element)
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<string>
     */
    private function packageDublinCoreMetadataElementNames(): array
    {
        return [
            'identifier',
            'title',
            'language',
            'contributor',
            'coverage',
            'creator',
            'date',
            'description',
            'format',
            'publisher',
            'relation',
            'rights',
            'source',
            'subject',
            'type',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packageMetadataChildDiagnosticContext(\DOMElement $element): array
    {
        $context = [
            'element' => $this->qualifiedName($element),
        ];
        foreach (['id', 'property', 'name', 'rel', 'refines', 'scheme'] as $attribute) {
            $value = trim($element->getAttribute($attribute));
            if ($value !== '') {
                $context[$attribute] = $value;
            }
        }
        $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($href !== '') {
            $context['href'] = $href;
        }
        $content = trim($element->getAttribute('content'));
        if ($content !== '') {
            $context['content'] = $content;
        }
        $value = $this->metadataElementText($element);
        if ($value !== '') {
            $context['value'] = $value;
        }

        return $context;
    }

    /**
     * @return array<string, true>
     */
    private function packagePropertyPrefixNames(\DOMElement $package): array
    {
        $prefixes = [];
        foreach (array_keys($this->packageReservedPrefixIris()) as $prefix) {
            $prefixes[$prefix] = true;
        }

        $prefix = trim(preg_replace('/\s+/', ' ', $package->getAttribute('prefix')) ?? $package->getAttribute('prefix'));
        $tokens = $prefix === '' ? [] : (preg_split('/\s+/', $prefix) ?: []);
        for ($i = 0, $count = count($tokens); $i < $count; $i += 2) {
            $prefixToken = $tokens[$i] ?? '';
            $iri = $tokens[$i + 1] ?? '';
            if (!str_ends_with($prefixToken, ':')) {
                continue;
            }

            $name = substr($prefixToken, 0, -1);
            if ($name !== '' && $this->validXmlId($name) && $iri !== '' && $this->absoluteIriLike($iri)) {
                $prefixes[$name] = true;
            }
        }

        return $prefixes;
    }

    /**
     * @return array<string, true>
     */
    private function xhtmlPropertyPrefixNames(\DOMDocument $dom): array
    {
        $prefixes = [];
        foreach (array_keys($this->packageReservedPrefixIris()) as $prefix) {
            $prefixes[$prefix] = true;
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return $prefixes;
        }

        $rawPrefix = $this->attributeByLocalName($root, 'prefix');
        $prefix = trim(preg_replace('/\s+/', ' ', $rawPrefix) ?? $rawPrefix);
        $tokens = $prefix === '' ? [] : (preg_split('/\s+/', $prefix) ?: []);
        for ($i = 0, $count = count($tokens); $i < $count; $i += 2) {
            $prefixToken = $tokens[$i] ?? '';
            $iri = $tokens[$i + 1] ?? '';
            if (!str_ends_with($prefixToken, ':')) {
                continue;
            }

            $name = substr($prefixToken, 0, -1);
            if ($name !== '' && $this->validXmlId($name) && $iri !== '' && $this->absoluteIriLike($iri)) {
                $prefixes[$name] = true;
            }
        }

        return $prefixes;
    }

    private function propertyValuePrefix(string $value): string
    {
        if (!str_contains($value, ':')) {
            return '';
        }

        [$prefix] = explode(':', trim($value), 2);

        return $prefix;
    }

    /**
     * @param array<string, true> $declaredPrefixes
     */
    private function propertyValuePrefixIsDeclared(string $value, array $declaredPrefixes): bool
    {
        if (!str_contains($value, ':')) {
            return true;
        }

        $prefix = $this->propertyValuePrefix($value);

        return isset($declaredPrefixes[$prefix]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metadataLanguageDiagnostics(\DOMElement $metadataElement): array
    {
        $diagnostics = [];
        foreach ($this->directChildElements($metadataElement, 'language') as $element) {
            if ($element->namespaceURI !== self::DC_NAMESPACE) {
                continue;
            }

            $value = $this->metadataElementText($element);
            if ($value === '' || $this->validXmlLanguageTag($value)) {
                continue;
            }

            $context = [
                'element' => $this->qualifiedName($element),
                'value' => $value,
            ];
            $id = trim($element->getAttribute('id'));
            if ($id !== '') {
                $context['id'] = $id;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-metadata-language',
                'OPF dc:language metadata must be a valid language tag.',
                $context
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metadataDateCardinalityDiagnostics(\DOMElement $metadataElement): array
    {
        $dates = [];
        foreach ($this->directChildElements($metadataElement, 'date') as $element) {
            if ($element->namespaceURI !== self::DC_NAMESPACE) {
                continue;
            }

            $dates[] = $this->packageMetadataChildDiagnosticContext($element);
        }

        if (count($dates) <= 1) {
            return [];
        }

        return [
            $this->epubDiagnostic(
                'error',
                'multiple-metadata-date',
                'EPUB3 package metadata must not contain more than one dc:date element.',
                [
                    'count' => count($dates),
                    'dates' => $dates,
                ]
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metadataXmlLanguageDiagnostics(\DOMElement $metadataElement): array
    {
        $diagnostics = [];
        foreach ($metadataElement->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            if ($this->isOpfPackageElement($element, 'link')) {
                continue;
            }

            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language === '' || $this->validXmlLanguageTag($language)) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-metadata-xml-language',
                'OPF metadata xml:lang attributes must be valid language tags.',
                $this->packageMetadataChildDiagnosticContext($element) + ['lang' => $language]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metadataDirectionDiagnostics(\DOMElement $metadataElement): array
    {
        $diagnostics = [];
        foreach ($metadataElement->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            if ($this->isOpfPackageElement($element, 'link')) {
                continue;
            }

            $direction = trim($element->getAttribute('dir'));
            if ($direction === '' || in_array(strtolower($direction), ['ltr', 'rtl', 'auto'], true)) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-metadata-dir',
                'OPF metadata dir attributes must be ltr, rtl, or auto.',
                $this->packageMetadataChildDiagnosticContext($element) + ['dir' => $direction]
            );
        }

        return $diagnostics;
    }

    private function metadataElementText(\DOMElement $element): string
    {
        return trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metadataIdentityDiagnostics(\DOMElement $metadataElement): array
    {
        $diagnostics = [];
        $seen = [];
        foreach ($metadataElement->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $id = trim($element->getAttribute('id'));
            if ($id === '') {
                continue;
            }

            $context = $this->metadataIdentityDiagnosticContext($element, $id);
            if (!$this->validXmlId($id)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-metadata-id',
                    'OPF metadata id attributes must be XML NCNames.',
                    $context
                );
                continue;
            }

            if (isset($seen[$id])) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-metadata-id',
                    'OPF metadata id attributes must be unique within the metadata element.',
                    $context + ['previousElement' => $seen[$id]['element']]
                );
                continue;
            }

            $seen[$id] = $context;
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataIdentityDiagnosticContext(\DOMElement $element, string $id): array
    {
        $context = [
            'element' => $this->qualifiedName($element),
            'id' => $id,
        ];
        $property = trim($element->getAttribute('property'));
        if ($property !== '') {
            $context['property'] = $property;
        }
        $name = trim($element->getAttribute('name'));
        if ($name !== '') {
            $context['name'] = $name;
        }
        $rel = trim($element->getAttribute('rel'));
        if ($rel !== '') {
            $context['rel'] = $rel;
        }
        $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($href !== '') {
            $context['href'] = $href;
        }
        if ($element->localName !== 'meta' && $element->localName !== 'link') {
            $value = $this->metadataElementText($element);
            if ($value !== '') {
                $context['value'] = $value;
            }
        }

        return $context;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function modifiedMetadataDiagnostics(\DOMElement $metadataElement): array
    {
        $entries = [];
        foreach ($this->directOpfChildElements($metadataElement, 'meta') as $metaElement) {
            if (trim($metaElement->getAttribute('property')) !== 'dcterms:modified') {
                continue;
            }
            if (trim($metaElement->getAttribute('refines')) !== '') {
                continue;
            }

            $value = trim($metaElement->getAttribute('content'));
            if ($value === '') {
                $value = trim(preg_replace('/\s+/', ' ', $metaElement->textContent) ?? $metaElement->textContent);
            }
            $context = ['property' => 'dcterms:modified'];
            $id = trim($metaElement->getAttribute('id'));
            if ($id !== '') {
                $context['id'] = $id;
            }
            if ($value !== '') {
                $context['value'] = $value;
            }

            $entries[] = [
                'value' => $value,
                'context' => $context,
            ];
        }

        if ($entries === []) {
            return [
                $this->epubDiagnostic('error', 'missing-package-modified', 'EPUB3 package metadata has no dcterms:modified meta element.'),
            ];
        }

        $diagnostics = [];
        if (count($entries) > 1) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'multiple-package-modified',
                'EPUB3 package metadata must contain exactly one unrefined dcterms:modified meta element.',
                [
                    'property' => 'dcterms:modified',
                    'count' => count($entries),
                    'values' => array_values(array_filter(
                        array_map(static fn (array $entry): string => (string) $entry['value'], $entries),
                        static fn (string $value): bool => $value !== ''
                    )),
                ]
            );
        }

        foreach ($entries as $entry) {
            $value = (string) $entry['value'];
            if ($value !== '' && $this->validPackageModifiedTimestamp($value)) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-modified',
                'EPUB3 dcterms:modified metadata must be a UTC timestamp formatted as YYYY-MM-DDThh:mm:ssZ.',
                $entry['context']
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function mediaOverlayMetadataDiagnostics(\DOMElement $metadataElement, array $manifest): array
    {
        $diagnostics = [];
        $packageDurationPresent = false;
        $overlayDurationTargets = [];
        foreach ($this->directOpfChildElements($metadataElement, 'meta') as $metaElement) {
            if (strtolower(trim($metaElement->getAttribute('property'))) !== 'media:duration') {
                continue;
            }

            $refines = trim($metaElement->getAttribute('refines'));
            if ($refines === '') {
                $packageDurationPresent = true;
            } elseif (str_starts_with($refines, '#') && strlen($refines) > 1) {
                $overlayDurationTargets[substr($refines, 1)] = true;
            }

            $value = trim($metaElement->getAttribute('content'));
            if ($value === '') {
                $value = trim(preg_replace('/\s+/', ' ', $metaElement->textContent) ?? $metaElement->textContent);
            }
            if ($value !== '' && $this->smilClockSeconds($value) !== null) {
                continue;
            }

            $context = ['property' => 'media:duration'];
            $id = trim($metaElement->getAttribute('id'));
            if ($id !== '') {
                $context['id'] = $id;
            }
            if ($refines !== '') {
                $context['refines'] = $refines;
                if (str_starts_with($refines, '#') && strlen($refines) > 1) {
                    $context['target'] = substr($refines, 1);
                }
            }
            if ($value !== '') {
                $context['value'] = $value;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-media-duration',
                'EPUB3 media:duration metadata must be a supported SMIL clock value.',
                $context
            );
        }

        $overlayReferences = $this->mediaOverlayDurationReferences($manifest);
        if ($overlayReferences !== [] && !$packageDurationPresent) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-package-media-duration',
                'EPUB3 media overlay packages must specify a total media:duration metadata value.',
                [
                    'property' => 'media:duration',
                    'overlayCount' => count($overlayReferences),
                    'mediaOverlays' => array_keys($overlayReferences),
                ]
            );
        }

        foreach ($overlayReferences as $overlayId => $contentIds) {
            if (isset($overlayDurationTargets[$overlayId])) {
                continue;
            }

            $context = [
                'property' => 'media:duration',
                'refines' => '#' . $overlayId,
                'target' => $overlayId,
                'overlayHref' => $manifest[$overlayId]['href'],
            ];
            if (count($contentIds) === 1) {
                $context['contentId'] = $contentIds[0];
            } else {
                $context['contentIds'] = array_values($contentIds);
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-media-overlay-duration',
                'EPUB3 media overlay documents must specify a refined media:duration metadata value.',
                $context
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array<string, list<string>>
     */
    private function mediaOverlayDurationReferences(array $manifest): array
    {
        $overlayReferences = [];
        foreach ($manifest as $id => $item) {
            $overlayId = trim($item['media-overlay']);
            if ($overlayId === '' || !isset($manifest[$overlayId])) {
                continue;
            }

            if (!$this->isMediaOverlayDocumentManifestItem($manifest[$overlayId])) {
                continue;
            }

            $overlayReferences[$overlayId][] = $id;
        }

        return $overlayReferences;
    }

    private function validPackageModifiedTimestamp(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
            return false;
        }

        $timestamp = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        if (!$timestamp instanceof \DateTimeImmutable) {
            return false;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return false;
        }

        return $timestamp->format('Y-m-d\TH:i:s\Z') === $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function metadataRefinesDiagnostics(\ZipArchive $zip, \DOMElement $package, string $base_path, string $rootfile): array
    {
        $diagnostics = [];
        $targetDocuments = [];
        $metadataElement = $this->directOpfChildElement($package, 'metadata');
        if ($metadataElement === null) {
            return $diagnostics;
        }

        foreach ($metadataElement->childNodes as $element) {
            if (
                !$element instanceof \DOMElement
                || !$element->hasAttribute('refines')
                || !in_array($element->localName, ['meta', 'link'], true)
                || !$this->isOpfPackageElement($element, $element->localName)
            ) {
                continue;
            }
            $refines = trim($element->getAttribute('refines'));
            $target = $this->packageMetadataRefinesTarget($base_path, $rootfile, $refines);
            $context = $this->metadataRefinesDiagnosticContext($element, $refines);
            if ($target['path'] !== '') {
                $context['path'] = $target['path'];
            }
            if ($target['fragment'] !== '') {
                $context['fragment'] = $target['fragment'];
            }
            if ($target['packageDocument'] && $target['fragment'] !== '') {
                $context['target'] = $target['fragment'];
            }

            $reason = $this->packageMetadataRefinesDiagnosticReason($refines, $target);
            if ($reason !== '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-metadata-refines',
                    'OPF metadata refines attributes must be path-relative URLs with optional fragments.',
                    $context + ['reason' => $reason]
                );
                continue;
            }

            if (!$target['packageDocument']) {
                if ($target['path'] !== '' && $zip->locateName($target['path']) === false) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-metadata-refines-resource',
                        'OPF metadata refinement references a missing package resource.',
                        $context
                    );
                    continue;
                }
                if ($target['path'] !== '' && $target['fragment'] !== '') {
                    if (!array_key_exists($target['path'], $targetDocuments)) {
                        $targetDocuments[$target['path']] = false;
                        $targetXml = $zip->getFromName($target['path']);
                        if (is_string($targetXml)) {
                            try {
                                $targetDocuments[$target['path']] = $this->loadXml($targetXml, 'EPUB metadata refines target');
                            } catch (\Throwable) {
                                $targetDocuments[$target['path']] = false;
                            }
                        }
                    }

                    $targetDocument = $targetDocuments[$target['path']];
                    if ($targetDocument === false) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'malformed-metadata-refines-resource',
                            'OPF metadata refinement references a package resource that is not valid XML.',
                            $context
                        );
                        continue;
                    }
                    if ($targetDocument instanceof \DOMDocument && !$this->xmlDocumentHasElementId($targetDocument, $target['fragment'])) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'missing-metadata-refines-fragment',
                            'OPF metadata refinement references a missing element id in a package resource.',
                            $context
                        );
                    }
                }
                continue;
            }

            $targetId = $target['fragment'];
            if ($this->packageHasElementId($package, $targetId)) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-metadata-refines-target',
                'OPF metadata refinement references a missing package element id.',
                $context + ['target' => $targetId]
            );
        }

        return $diagnostics;
    }

    /**
     * @return array{path: string, fragment: string, hasFragment: bool, packageDocument: bool}
     */
    private function packageMetadataRefinesTarget(string $base_path, string $rootfile, string $refines): array
    {
        $fragment = $this->urlFragmentIdentifier($refines);
        [$refinesPath] = $this->splitUrlPathSuffix($refines);
        $path = '';
        if (str_starts_with($refines, '#')) {
            $path = $this->normalizeZipPath($rootfile);
        } elseif ($this->isPackageRelativeResourceUrl($refines)) {
            $path = $this->packageResourceZipPath($base_path, $refines);
        }

        return [
            'path' => $path,
            'fragment' => $fragment,
            'hasFragment' => str_contains($refines, '#'),
            'packageDocument' => $path !== '' && $path === $this->normalizeZipPath($rootfile) && (trim($refinesPath) === '' || !$this->isAbsoluteUrl($refines)),
        ];
    }

    /**
     * @param array{path: string, fragment: string, hasFragment: bool, packageDocument: bool} $target
     */
    private function packageMetadataRefinesDiagnosticReason(string $refines, array $target): string
    {
        $refines = trim($refines);
        if ($refines === '') {
            return 'empty';
        }
        if (str_starts_with($refines, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($refines)) {
            return 'absolute-url';
        }
        if (str_starts_with($refines, '/')) {
            return 'absolute-path';
        }
        if (str_contains($refines, '\\')) {
            return 'backslash';
        }
        [$refinesPath] = $this->splitUrlPathSuffix($refines);
        $encodedDotSegmentReason = $this->encodedDotSegmentPathDiagnosticReason($refinesPath);
        if ($encodedDotSegmentReason !== '') {
            return $encodedDotSegmentReason;
        }
        if ($target['hasFragment'] && $target['fragment'] === '') {
            return 'empty-fragment';
        }
        if ($target['path'] === '') {
            return 'empty-path';
        }
        if ($target['packageDocument'] && !$this->validXmlId($target['fragment'])) {
            return 'invalid-fragment';
        }
        if (!$target['packageDocument'] && $target['fragment'] !== '' && preg_match('/\s/u', $target['fragment']) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    private function validMetadataRefinesValue(string $refines): bool
    {
        if (!str_starts_with($refines, '#')) {
            return false;
        }

        $target = substr($refines, 1);

        return $target !== '' && $this->validXmlId($target);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataRefinesDiagnosticContext(\DOMElement $element, string $refines): array
    {
        $context = [
            'refines' => $refines,
            'element' => $this->qualifiedName($element),
        ];
        if (str_starts_with($refines, '#') && strlen($refines) > 1) {
            $context['target'] = substr($refines, 1);
        }
        $parent = $element->parentNode;
        if ($parent instanceof \DOMElement) {
            $context['parent'] = $this->qualifiedName($parent);
        }
        $id = trim($element->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }
        $property = trim($element->getAttribute('property'));
        if ($property !== '') {
            $context['property'] = $property;
        }
        $rel = trim($element->getAttribute('rel'));
        if ($rel !== '') {
            $context['rel'] = $rel;
        }
        $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($href !== '') {
            $context['href'] = $href;
        }

        return $context;
    }

    /**
     * @param array<string, true> $declaredPrefixes
     * @return list<array<string, mixed>>
     */
    private function packageLinkAttributeDiagnostics(\DOMElement $package, array $declaredPrefixes): array
    {
        $diagnostics = [];
        foreach ($this->packageLinkElements($package) as $element) {
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $rel = trim($element->getAttribute('rel'));
            $context = $this->packageLinkDiagnosticContext($element);
            $diagnostics = array_merge($diagnostics, $this->packageLinkContentDiagnostics($element, $href, $context));
            if ($href === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-package-link-href',
                    'OPF package link element is missing href.',
                    $context
                );
            } else {
                $lowerHref = strtolower($href);
                if (str_starts_with($lowerHref, 'data:')) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-link-data-url',
                        'OPF package link href must not be a data URL.',
                        $context + ['href' => $href]
                    );
                }
                if (str_starts_with($lowerHref, 'file:')) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-link-file-url',
                        'OPF package link href must not be a file URL.',
                        $context + ['href' => $href]
                    );
                }
                $hrefPathReason = $this->packageLinkHrefPathDiagnosticReason($href);
                if ($hrefPathReason !== '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-link-href-path',
                        'OPF package link href must be an absolute URL or a path-relative scheme-less URL.',
                        $context + ['href' => $href, 'reason' => $hrefPathReason]
                    );
                }
                $hrefFragmentReason = $this->packageLinkHrefFragmentDiagnosticReason($href);
                if ($hrefFragmentReason !== '') {
                    $fragment = $this->urlFragmentIdentifier($href);
                    $fragmentContext = $context + ['href' => $href, 'reason' => $hrefFragmentReason];
                    if ($fragment !== '') {
                        $fragmentContext['fragment'] = $fragment;
                    }
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-link-href-fragment',
                        'OPF package link href fragments must be non-empty fragment identifiers without whitespace.',
                        $fragmentContext
                    );
                }
            }
            if ($rel === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-package-link-rel',
                    'OPF package link element is missing rel.',
                    $href === '' ? $context : ($context + ['href' => $href])
                );
            }
            foreach ($this->duplicateAttributeTokens($element, 'rel') as $relValue) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-package-link-rel',
                    'OPF package link rel values must not repeat token values.',
                    ($href === '' ? $context : ($context + ['href' => $href])) + ['value' => $relValue]
                );
            }
            foreach ($this->attributeTokenList($element, 'rel') as $relValue) {
                if (!$this->validPropertyValue($relValue)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-link-rel',
                        'OPF package link rel values must be valid property data type values.',
                        ($href === '' ? $context : ($context + ['href' => $href])) + ['value' => $relValue]
                    );
                } elseif (!$this->propertyValuePrefixIsDeclared($relValue, $declaredPrefixes)) {
                    [$relPrefix] = explode(':', $relValue, 2);
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-package-link-rel-prefix',
                        'OPF package link rel prefix must be reserved or declared in package prefix.',
                        ($href === '' ? $context : ($context + ['href' => $href])) + ['value' => $relValue, 'prefix' => $relPrefix]
                    );
                }
            }

            $diagnostics = array_merge(
                $diagnostics,
                $this->packageLinkRelationRefinesDiagnostics($element, $href, $context)
            );

            $mediaType = trim($element->getAttribute('media-type'));
            $requiredMediaTypeRel = $this->packageLinkRequiredMediaTypeRel($element, $href);
            if ($mediaType === '' && $requiredMediaTypeRel !== '') {
                $requiredMediaTypeContext = $context + ['href' => $href];
                if ($requiredMediaTypeRel !== 'local-resource') {
                    $requiredMediaTypeContext['value'] = $requiredMediaTypeRel;
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-package-link-media-type',
                    'OPF package links that require a concrete linked resource type must include media-type.',
                    $requiredMediaTypeContext
                );
            } elseif ($mediaType !== '' && !$this->validMediaType($mediaType)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-link-media-type',
                    'OPF package link media-type must be a valid media type.',
                    ($href === '' ? $context : ($context + ['href' => $href])) + ['mediaType' => $mediaType]
                );
            }

            $hreflang = trim($element->getAttribute('hreflang'));
            if ($hreflang !== '' && !$this->validXmlLanguageTag($hreflang)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-link-hreflang',
                    'OPF package link hreflang must be a valid language tag.',
                    ($href === '' ? $context : ($context + ['href' => $href])) + ['hreflang' => $hreflang]
                );
            }

            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language !== '' && !$this->validXmlLanguageTag($language)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-link-xml-language',
                    'OPF package link xml:lang attribute must be a valid language tag.',
                    ($href === '' ? $context : ($context + ['href' => $href])) + ['lang' => $language]
                );
            }

            $direction = trim($element->getAttribute('dir'));
            if ($direction !== '' && !in_array(strtolower($direction), ['ltr', 'rtl', 'auto'], true)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-link-dir',
                    'OPF package link dir attribute must be ltr, rtl, or auto.',
                    ($href === '' ? $context : ($context + ['href' => $href])) + ['dir' => $direction]
                );
            }

            foreach ($this->duplicateAttributeTokens($element, 'properties') as $property) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-package-link-property',
                    'OPF package link properties values must not repeat token values.',
                    ($href === '' ? $context : ($context + ['href' => $href])) + ['property' => $property]
                );
            }
            foreach ($this->attributeTokenList($element, 'properties') as $property) {
                if (!$this->validPropertyValue($property)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-package-link-property',
                        'OPF package link properties values must be valid property data type values.',
                        ($href === '' ? $context : ($context + ['href' => $href])) + ['property' => $property]
                    );
                } elseif (!$this->propertyValuePrefixIsDeclared($property, $declaredPrefixes)) {
                    [$propertyPrefix] = explode(':', $property, 2);
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-package-link-property-prefix',
                        'OPF package link properties prefix must be reserved or declared in package prefix.',
                        ($href === '' ? $context : ($context + ['href' => $href])) + ['property' => $property, 'prefix' => $propertyPrefix]
                    );
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function packageLinkContentDiagnostics(\DOMElement $element, string $href, array $context): array
    {
        $contentContext = $href === '' ? $context : ($context + ['href' => $href]);
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return [
                    $this->epubDiagnostic(
                        'error',
                        'invalid-package-link-content',
                        'OPF package link elements must be empty.',
                        $contentContext + ['childElement' => $this->qualifiedName($child)]
                    ),
                ];
            }
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->textContent) !== '') {
                return [
                    $this->epubDiagnostic(
                        'error',
                        'invalid-package-link-content',
                        'OPF package link elements must be empty.',
                        $contentContext + ['text' => trim(preg_replace('/\s+/', ' ', $child->textContent) ?? $child->textContent)]
                    ),
                ];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function packageLinkRelationRefinesDiagnostics(\DOMElement $element, string $href, array $context): array
    {
        $parent = $element->parentNode;
        if (!$parent instanceof \DOMElement || !$this->isOpfPackageElement($parent, 'metadata')) {
            return [];
        }

        $diagnostics = [];
        $refines = trim($element->getAttribute('refines'));
        $linkContext = $href === '' ? $context : ($context + ['href' => $href]);
        $seenRels = [];
        foreach ($this->attributeTokenList($element, 'rel') as $relValue) {
            $normalizedRel = strtolower($relValue);
            if (isset($seenRels[$normalizedRel])) {
                continue;
            }
            $seenRels[$normalizedRel] = true;

            if ($normalizedRel === 'record' && $refines !== '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-link-record-refines',
                    'OPF metadata record links must not include refines.',
                    $linkContext + ['value' => $relValue, 'refines' => $refines]
                );
            } elseif ($normalizedRel === 'voicing' && $refines === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-package-link-voicing-refines',
                    'OPF metadata voicing links must include refines.',
                    $linkContext + ['value' => $relValue]
                );
            }
        }

        return $diagnostics;
    }

    private function packageLinkRequiredMediaTypeRel(\DOMElement $element, string $href): string
    {
        foreach ($this->attributeTokenList($element, 'rel') as $relValue) {
            $normalizedRel = strtolower($relValue);
            if ($normalizedRel === 'record' || $normalizedRel === 'voicing') {
                return $relValue;
            }
        }

        if ($this->packageMetadataLinkRequiresLocalMediaType($element, $href)) {
            return 'local-resource';
        }

        return '';
    }

    private function packageMetadataLinkRequiresLocalMediaType(\DOMElement $element, string $href): bool
    {
        $parent = $element->parentNode;
        if (!$parent instanceof \DOMElement || !$this->isOpfPackageElement($parent, 'metadata')) {
            return false;
        }
        if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
            return false;
        }
        if ($this->packageLinkHrefPathDiagnosticReason($href) !== '') {
            return false;
        }
        if ($this->packageLinkHrefFragmentDiagnosticReason($href) !== '') {
            return false;
        }

        return true;
    }

    private function packageLinkHrefPathDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($href)) {
            return '';
        }
        if (str_starts_with($href, '#')) {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return 'absolute-path';
        }
        if (str_contains($href, '\\')) {
            return 'backslash';
        }

        [$hrefPath, $suffix] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '' && str_starts_with($suffix, '?')) {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($hrefPath);
    }

    private function packageLinkHrefFragmentDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '' || !str_contains($href, '#') || $this->isAbsoluteUrl($href)) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($href);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageLinkResourceDiagnostics(\ZipArchive $zip, \DOMElement $package, string $base_path, string $rootfile): array
    {
        $diagnostics = [];
        $packagePath = $this->normalizeZipPath($rootfile);
        $targetDocuments = [];
        foreach ($this->packageLinkElements($package) as $element) {
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href === '') {
                continue;
            }
            if ($this->packageLinkHrefPathDiagnosticReason($href) !== '') {
                continue;
            }
            if ($this->packageLinkHrefFragmentDiagnosticReason($href) !== '') {
                continue;
            }
            $zipPath = $this->packageResourceZipPath($base_path, $href);
            if ($zipPath === '' || $zip->locateName($zipPath) !== false) {
                if ($zipPath === '' || $zipPath === $packagePath) {
                    continue;
                }
            } else {
                $context = $this->packageLinkDiagnosticContext($element) + [
                    'href' => $href,
                    'path' => $zipPath,
                ];

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-package-link-resource',
                    'OPF package link href does not resolve to a resource in the EPUB archive.',
                    $context
                );
                continue;
            }

            $context = $this->packageLinkDiagnosticContext($element) + [
                'href' => $href,
                'path' => $zipPath,
            ];

            $fragment = $this->urlFragmentIdentifier($href);
            if ($fragment === '') {
                continue;
            }

            if (!array_key_exists($zipPath, $targetDocuments)) {
                $targetDocuments[$zipPath] = false;
                $targetXml = $zip->getFromName($zipPath);
                if (is_string($targetXml)) {
                    try {
                        $targetDocuments[$zipPath] = $this->loadXml($targetXml, 'EPUB package link target');
                    } catch (\Throwable) {
                        $targetDocuments[$zipPath] = false;
                    }
                }
            }

            $targetDocument = $targetDocuments[$zipPath];
            if ($targetDocument === false) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'malformed-package-link-resource',
                    'OPF package link href references a package resource that is not valid XML.',
                    $context + ['fragment' => $fragment]
                );
                continue;
            }
            if ($targetDocument instanceof \DOMDocument && !$this->xmlDocumentHasElementId($targetDocument, $fragment)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-package-link-fragment',
                    'OPF package link href fragment does not resolve to an element id in the referenced resource.',
                    $context + ['fragment' => $fragment]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function packageLinkPackageDocumentReferenceDiagnostics(\DOMElement $package, string $base_path, string $rootfile): array
    {
        $diagnostics = [];
        $packagePath = $this->normalizeZipPath($rootfile);
        foreach ($this->packageLinkElements($package) as $element) {
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href === '') {
                continue;
            }

            if ($this->packageLinkHrefPathDiagnosticReason($href) !== '') {
                continue;
            }
            if ($this->packageLinkHrefFragmentDiagnosticReason($href) !== '') {
                continue;
            }

            $fragment = $this->urlFragmentIdentifier($href);
            [$hrefPath] = $this->splitUrlPathSuffix($href);
            $path = trim($hrefPath) === ''
                ? $packagePath
                : $this->packageResourceZipPath($base_path, $href);
            if ($path !== $packagePath) {
                continue;
            }

            $context = $this->packageLinkDiagnosticContext($element) + [
                'href' => $href,
                'path' => $packagePath,
            ];
            if ($fragment !== '') {
                $context['fragment'] = $fragment;
            }
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-package-link-package-document-reference',
                'OPF package link href must not reference the OPF package document as a linked resource.',
                $context
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function packageLinkManifestResourceDiagnostics(\DOMElement $package, array $manifest, string $base_path, string $rootfile): array
    {
        $diagnostics = [];
        $packagePath = $this->normalizeZipPath($rootfile);
        $spineIdrefs = $this->packageSpineManifestIdrefs($package);
        $manifestByPath = [];
        foreach ($manifest as $id => $item) {
            $path = $this->packageResourceZipPath($base_path, $item['href']);
            if ($path === '') {
                continue;
            }
            $manifestByPath[$path][] = [
                'id' => $id,
                'href' => $item['href'],
                'mediaType' => $item['media-type'],
            ];
        }

        foreach ($this->packageLinkElements($package) as $element) {
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
                continue;
            }
            if ($this->packageLinkHrefPathDiagnosticReason($href) !== '') {
                continue;
            }
            if ($this->packageLinkHrefFragmentDiagnosticReason($href) !== '') {
                continue;
            }

            $path = $this->packageResourceZipPath($base_path, $href);
            if ($path === '' || $path === $packagePath || !isset($manifestByPath[$path])) {
                continue;
            }

            foreach ($manifestByPath[$path] as $manifestItem) {
                $manifestId = (string) $manifestItem['id'];
                $manifestMediaType = (string) $manifestItem['mediaType'];
                if (isset($spineIdrefs[$manifestId]) || $this->isEpubContentDocumentMediaType($manifestMediaType)) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-link-manifest-resource',
                    'Standalone OPF package link resources must not also be listed in the package manifest.',
                    $this->packageLinkDiagnosticContext($element) + [
                        'href' => $href,
                        'path' => $path,
                        'manifestId' => $manifestId,
                        'manifestHref' => (string) $manifestItem['href'],
                        'manifestMediaType' => $manifestMediaType,
                    ]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, true>
     */
    private function packageSpineManifestIdrefs(\DOMElement $package): array
    {
        $spineElement = $this->directOpfChildElement($package, 'spine');
        if ($spineElement === null) {
            return [];
        }

        $idrefs = [];
        foreach ($this->directOpfChildElements($spineElement, 'itemref') as $itemref) {
            $idref = trim($itemref->getAttribute('idref'));
            if ($idref !== '') {
                $idrefs[$idref] = true;
            }
        }

        return $idrefs;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function guideDiagnostics(\ZipArchive $zip, \DOMElement $package, array $manifest, string $base_path): array
    {
        $diagnostics = [];
        $manifestByPath = [];
        foreach ($manifest as $item) {
            $path = $this->packageResourceZipPath($base_path, $item['href']);
            if ($path !== '') {
                $manifestByPath[$path] = true;
            }
        }
        $targetDocuments = [];

        foreach ($package->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'guide' || $this->isOpfPackageElement($child, 'guide')) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-guide-namespace',
                'OPF guide elements must use the OPF namespace.',
                [
                    'element' => $this->qualifiedName($child),
                    'namespace' => $child->namespaceURI ?? '',
                ]
            );
        }

        $guide = $this->directOpfChildElement($package, 'guide');
        if ($guide !== null) {
            foreach ($guide->childNodes as $child) {
                if (!$child instanceof \DOMElement || $this->isOpfPackageElement($child, 'reference')) {
                    continue;
                }

                if ($child->localName === 'reference') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-guide-reference-namespace',
                        'OPF guide reference elements must use the OPF namespace.',
                        $this->guideReferenceDiagnosticContext($child) + ['namespace' => $child->namespaceURI ?? '']
                    );
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-guide-child-element',
                    'OPF guide must contain only reference child elements.',
                    ['element' => $this->qualifiedName($child)]
                );
            }

            foreach ($this->directOpfChildElements($guide, 'reference') as $reference) {
                $type = strtolower(trim($reference->getAttribute('type')));
                $title = trim($reference->getAttribute('title'));
                $href = html_entity_decode(trim($reference->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                $context = $this->guideReferenceDiagnosticContext($reference);

                if ($href !== '') {
                    $diagnostics = array_merge(
                        $diagnostics,
                        $this->guideReferenceHrefUrlDiagnostics($href, $context)
                    );
                }

                $zipPath = $href !== '' ? $this->packageResourceZipPath($base_path, $href) : '';
                if ($zipPath !== '') {
                    $context['path'] = $zipPath;
                }
                $hrefPathReason = $href !== '' ? $this->guideReferenceHrefPathDiagnosticReason($href) : '';
                if ($hrefPathReason !== '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-guide-reference-href-path',
                        'OPF guide reference href must be an absolute URL or a path-relative scheme-less URL.',
                        $context + ['reason' => $hrefPathReason]
                    );
                }
                $hrefFragmentReason = $href !== '' ? $this->guideReferenceHrefFragmentDiagnosticReason($href) : '';
                if ($hrefFragmentReason !== '') {
                    $fragment = $this->urlFragmentIdentifier($href);
                    $fragmentContext = $context + ['reason' => $hrefFragmentReason];
                    if ($fragment !== '') {
                        $fragmentContext['fragment'] = $fragment;
                    }
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-guide-reference-href-fragment',
                        'OPF guide reference href fragments must be non-empty fragment identifiers without whitespace.',
                        $fragmentContext
                    );
                }

                if ($type === '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-guide-reference-type',
                        'OPF guide reference must declare a type.',
                        $context
                    );
                } elseif (!$this->validXmlId($type)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-guide-reference-type',
                        'OPF guide reference type must be a single XML name token.',
                        $context
                    );
                }
                if ($title === '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-guide-reference-title',
                        'OPF guide reference must declare a title.',
                        $context
                    );
                }

                if ($href === '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-guide-reference-href',
                        'OPF guide reference must declare an href.',
                        $context
                    );
                    continue;
                }

                if ($hrefPathReason !== '') {
                    continue;
                }
                if ($hrefFragmentReason !== '') {
                    continue;
                }

                if ($zipPath === '') {
                    continue;
                }

                if ($zip->locateName($zipPath) === false) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-guide-reference-resource',
                        'OPF guide reference href does not resolve to a resource in the EPUB archive.',
                        $context
                    );
                    continue;
                }

                if ($this->isPackageRelativeResourceUrl($href) && !isset($manifestByPath[$zipPath])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-guide-reference-manifest-resource',
                        'OPF guide reference href resolves to an archive resource that is not listed in the package manifest.',
                        $context
                    );
                }

                $fragment = $this->urlFragmentIdentifier($href);
                if ($fragment === '') {
                    continue;
                }

                if (!array_key_exists($zipPath, $targetDocuments)) {
                    $targetDocuments[$zipPath] = false;
                    $targetXml = $zip->getFromName($zipPath);
                    if (is_string($targetXml)) {
                        try {
                            $targetDocuments[$zipPath] = $this->loadXml($targetXml, 'EPUB guide reference target');
                        } catch (\Throwable) {
                            $targetDocuments[$zipPath] = false;
                        }
                    }
                }

                $targetDocument = $targetDocuments[$zipPath];
                if ($targetDocument === false) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'malformed-guide-reference-resource',
                        'OPF guide reference href references a package resource that is not valid XML.',
                        $context + ['fragment' => $fragment]
                    );
                    continue;
                }
                if ($targetDocument instanceof \DOMDocument && !$this->xmlDocumentHasElementId($targetDocument, $fragment)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-guide-reference-fragment',
                        'OPF guide reference href fragment does not resolve to an element id in the referenced resource.',
                        $context + ['fragment' => $fragment]
                    );
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function guideReferenceHrefUrlDiagnostics(string $href, array $context): array
    {
        $lowerHref = strtolower(trim($href));
        if (str_starts_with($lowerHref, 'data:')) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-guide-reference-data-url',
                    'OPF guide reference href must not be a data URL.',
                    $context
                ),
            ];
        }

        if (str_starts_with($lowerHref, 'file:')) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-guide-reference-file-url',
                    'OPF guide reference href must not be a file URL.',
                    $context
                ),
            ];
        }

        return [];
    }

    private function guideReferenceHrefPathDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($href)) {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return 'absolute-path';
        }
        if (str_contains($href, '\\')) {
            return 'backslash';
        }

        [$hrefPath, $suffix] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '' && $suffix !== '') {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($hrefPath);
    }

    private function guideReferenceHrefFragmentDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '' || !str_contains($href, '#') || $this->isAbsoluteUrl($href) || str_starts_with($href, '#')) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($href);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function guideReferenceDiagnosticContext(\DOMElement $reference): array
    {
        $context = ['element' => $this->qualifiedName($reference)];
        $type = strtolower(trim($reference->getAttribute('type')));
        if ($type !== '') {
            $context['type'] = $type;
        }
        $title = trim($reference->getAttribute('title'));
        if ($title !== '') {
            $context['title'] = $title;
        }
        $href = html_entity_decode(trim($reference->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($href !== '') {
            $context['href'] = $href;
        }

        return $context;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function mediaOverlayResourceDiagnostics(\ZipArchive $zip, array $manifest, string $base_path): array
    {
        $overlayContentIds = [];
        foreach ($manifest as $id => $item) {
            $overlayId = trim($item['media-overlay']);
            if ($overlayId !== '') {
                $overlayContentIds[$overlayId][] = $id;
            }
        }

        $diagnostics = [];
        foreach ($overlayContentIds as $overlayId => $contentIds) {
            if (!isset($manifest[$overlayId])) {
                continue;
            }

            $overlayItem = $manifest[$overlayId];
            $mediaType = strtolower(trim($overlayItem['media-type']));
            [$hrefPath] = $this->splitUrlPathSuffix($overlayItem['href']);
            $href = strtolower($hrefPath);
            if (!str_contains($mediaType, 'smil') && !str_ends_with($href, '.smil')) {
                continue;
            }

            $overlayPath = $this->packageResourceZipPath($base_path, $overlayItem['href']);
            if ($overlayPath === '' || $zip->locateName($overlayPath) === false) {
                continue;
            }

            $xml = $zip->getFromName($overlayPath);
            if (!is_string($xml)) {
                continue;
            }

            $baseContext = [
                'overlayId' => $overlayId,
                'overlayHref' => $overlayItem['href'],
                'overlayPath' => $overlayPath,
            ];
            if (count($contentIds) === 1) {
                $baseContext['contentId'] = $contentIds[0];
            } else {
                $baseContext['contentIds'] = array_values($contentIds);
            }

            try {
                $dom = $this->loadXml($xml, 'EPUB SMIL media overlay');
            } catch (\InvalidArgumentException) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'malformed-media-overlay-resource',
                    'SMIL media overlay resource is not valid XML.',
                    $baseContext
                );
                continue;
            }

            $diagnostics = array_merge($diagnostics, $this->mediaOverlayStructureDiagnostics($dom, $baseContext));

            $overlayBasePath = $this->dirname($overlayPath);
            $targetDocuments = [];
            $targetOrderState = [];
            foreach ($dom->getElementsByTagName('*') as $element) {
                if (!$element instanceof \DOMElement) {
                    continue;
                }

                if ($element->localName === 'seq') {
                    $diagnostics = array_merge(
                        $diagnostics,
                        $this->mediaOverlaySequenceTextrefDiagnostics($zip, $element, $baseContext, $overlayBasePath, $targetDocuments)
                    );
                    $diagnostics = array_merge(
                        $diagnostics,
                        $this->mediaOverlayTargetOrderDiagnostics(
                            $zip,
                            html_entity_decode(trim($this->attributeByLocalName($element, 'textref')), ENT_QUOTES | ENT_XML1, 'UTF-8'),
                            $this->mediaOverlayElementContext($baseContext, $element),
                            'textref',
                            'out-of-order-media-overlay-seq-textref',
                            'SMIL media overlay seq textref target appears before the previous local overlay target in the referenced content document.',
                            $overlayBasePath,
                            $targetDocuments,
                            $targetOrderState
                        )
                    );
                    continue;
                }

                if (!in_array($element->localName, ['text', 'audio'], true)) {
                    continue;
                }

                $src = html_entity_decode(trim($element->getAttribute('src')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                $zipPath = $this->localResourceZipPath($overlayBasePath, $src);
                $context = $baseContext + [
                    'src' => $src,
                    'element' => $this->qualifiedName($element),
                ];
                if ($zipPath !== '') {
                    $context['path'] = $zipPath;
                }
                $id = trim($element->getAttribute('id'));
                if ($id !== '') {
                    $context['id'] = $id;
                }

                $srcPathReason = $src !== '' ? $this->mediaOverlayReferencePathDiagnosticReason($src) : '';
                if ($srcPathReason !== '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-media-overlay-' . $element->localName . '-src-path',
                        'SMIL media overlay ' . $element->localName . ' src must be an absolute URL or a path-relative scheme-less URL.',
                        $context + ['reason' => $srcPathReason]
                    );

                    if ($element->localName === 'audio') {
                        $diagnostics = array_merge($diagnostics, $this->mediaOverlayAudioClipDiagnostics($element, $context));
                    }
                    continue;
                }
                if ($element->localName === 'text') {
                    $srcFragmentReason = $this->mediaOverlayReferenceFragmentDiagnosticReason($src);
                    if ($srcFragmentReason !== '') {
                        $fragment = $this->urlFragmentIdentifier($src);
                        $fragmentContext = $context + ['reason' => $srcFragmentReason];
                        if ($fragment !== '') {
                            $fragmentContext['fragment'] = $fragment;
                        }
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-media-overlay-text-src-fragment',
                            'SMIL media overlay text src fragments must be non-empty fragment identifiers without whitespace.',
                            $fragmentContext
                        );
                        continue;
                    }
                }

                $resourceExists = $zipPath !== '' && $zip->locateName($zipPath) !== false;
                if ($zipPath !== '' && !$resourceExists) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-media-overlay-' . $element->localName . '-resource',
                        'SMIL media overlay ' . $element->localName . ' src does not resolve to a resource in the EPUB archive.',
                        $context
                    );
                }

                if ($element->localName === 'text' && $resourceExists) {
                    $fragment = $this->urlFragmentIdentifier($src);
                    if ($fragment !== '') {
                        $targetDocument = $this->mediaOverlayTargetDocument($zip, $zipPath, $targetDocuments);
                        if ($targetDocument instanceof \DOMDocument && !$this->xmlDocumentHasElementId($targetDocument, $fragment)) {
                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                'missing-media-overlay-text-fragment',
                                'SMIL media overlay text src fragment does not resolve to an element id in the referenced resource.',
                                $context + ['fragment' => $fragment]
                            );
                        }
                    }
                    $diagnostics = array_merge(
                        $diagnostics,
                        $this->mediaOverlayTargetOrderDiagnostics(
                            $zip,
                            $src,
                            $context,
                            'src',
                            'out-of-order-media-overlay-text-target',
                            'SMIL media overlay text target appears before the previous local overlay target in the referenced content document.',
                            $overlayBasePath,
                            $targetDocuments,
                            $targetOrderState
                        )
                    );
                }

                if ($element->localName === 'audio') {
                    $diagnostics = array_merge($diagnostics, $this->mediaOverlayAudioClipDiagnostics($element, $context));
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function mediaOverlayManifestDiagnostics(array $manifest): array
    {
        $diagnostics = [];
        foreach ($manifest as $id => $item) {
            $overlayId = trim($item['media-overlay']);
            if ($overlayId === '' || !isset($manifest[$overlayId])) {
                continue;
            }

            if (!$this->isEpubContentDocumentMediaType($item['media-type'])) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-media-overlay-content-media-type',
                    'Manifest media-overlay attributes must only be specified on EPUB content document resources.',
                    [
                        'id' => $id,
                        'href' => $item['href'],
                        'mediaType' => $item['media-type'],
                        'mediaOverlay' => $overlayId,
                    ]
                );
            }

            $overlayItem = $manifest[$overlayId];
            if (!$this->mediaTypeMatches($overlayItem['media-type'], 'application/smil+xml')) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-media-overlay-target-media-type',
                    'Manifest media overlay document items must use the application/smil+xml media type.',
                    [
                        'id' => $id,
                        'mediaOverlay' => $overlayId,
                        'overlayHref' => $overlayItem['href'],
                        'mediaType' => $overlayItem['media-type'],
                    ]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $baseContext
     * @return list<array<string, mixed>>
     */
    private function mediaOverlayStructureDiagnostics(\DOMDocument $dom, array $baseContext): array
    {
        $diagnostics = [];
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'smil') {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-media-overlay-root',
                    'SMIL media overlay document root must be a smil element.',
                    $baseContext
                ),
            ];
        }

        $version = trim($root->getAttribute('version'));
        if ($version === '') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-media-overlay-version',
                'SMIL media overlay root is missing a version attribute.',
                $this->mediaOverlayElementContext($baseContext, $root)
            );
        } elseif ($version !== '3.0') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-media-overlay-version',
                'SMIL media overlay root version must be 3.0.',
                $this->mediaOverlayElementContext($baseContext, $root) + ['version' => $version]
            );
        }

        if ($this->directChildElement($root, 'body') === null) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-media-overlay-body',
                'SMIL media overlay root is missing a body element.',
                $this->mediaOverlayElementContext($baseContext, $root)
            );
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            if ($element->localName === 'seq' && html_entity_decode(trim($this->attributeByLocalName($element, 'textref')), ENT_QUOTES | ENT_XML1, 'UTF-8') === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-media-overlay-seq-textref',
                    'SMIL media overlay seq element is missing the required epub:textref attribute.',
                    $this->mediaOverlayElementContext($baseContext, $element)
                );
            }

            if ($element->localName === 'par' && $this->directChildElement($element, 'text') === null) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-media-overlay-par-text',
                    'SMIL media overlay par element is missing the required text child.',
                    $this->mediaOverlayElementContext($baseContext, $element)
                );
            }

            if ($element->localName === 'text' && html_entity_decode(trim($element->getAttribute('src')), ENT_QUOTES | ENT_XML1, 'UTF-8') === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-media-overlay-text-src',
                    'SMIL media overlay text element is missing the required src attribute.',
                    $this->mediaOverlayElementContext($baseContext, $element)
                );
            }

            if ($element->localName === 'audio' && html_entity_decode(trim($element->getAttribute('src')), ENT_QUOTES | ENT_XML1, 'UTF-8') === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-media-overlay-audio-src',
                    'SMIL media overlay audio element is missing the required src attribute.',
                    $this->mediaOverlayElementContext($baseContext, $element)
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $baseContext
     * @return array<string, mixed>
     */
    private function mediaOverlayElementContext(array $baseContext, \DOMElement $element): array
    {
        $context = $baseContext + ['element' => $this->qualifiedName($element)];
        $id = trim($element->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }

        return $context;
    }

    private function mediaOverlayReferencePathDiagnosticReason(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return '';
        }
        if (str_starts_with($reference, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($reference)) {
            return '';
        }
        if (str_starts_with($reference, '/')) {
            return 'absolute-path';
        }
        if (str_contains($reference, '\\')) {
            return 'backslash';
        }

        [$referencePath, $suffix] = $this->splitUrlPathSuffix($reference);
        if (trim($referencePath) === '' && $suffix !== '') {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($referencePath);
    }

    private function mediaOverlayReferenceFragmentDiagnosticReason(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '' || !str_contains($reference, '#') || $this->isAbsoluteUrl($reference) || str_starts_with($reference, '#')) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($reference);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $baseContext
     * @param array<string, \DOMDocument|false> $targetDocuments
     * @return list<array<string, mixed>>
     */
    private function mediaOverlaySequenceTextrefDiagnostics(
        \ZipArchive $zip,
        \DOMElement $sequence,
        array $baseContext,
        string $overlayBasePath,
        array &$targetDocuments
    ): array {
        $textref = html_entity_decode(trim($this->attributeByLocalName($sequence, 'textref')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($textref === '') {
            return [];
        }

        $zipPath = $this->localResourceZipPath($overlayBasePath, $textref);
        $context = $baseContext + [
            'textref' => $textref,
            'element' => $this->qualifiedName($sequence),
        ];
        if ($zipPath !== '') {
            $context['path'] = $zipPath;
        }
        $id = trim($sequence->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }

        $textrefPathReason = $this->mediaOverlayReferencePathDiagnosticReason($textref);
        if ($textrefPathReason !== '') {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-media-overlay-seq-textref-path',
                    'SMIL media overlay seq textref must be an absolute URL or a path-relative scheme-less URL.',
                    $context + ['reason' => $textrefPathReason]
                ),
            ];
        }
        $textrefFragmentReason = $this->mediaOverlayReferenceFragmentDiagnosticReason($textref);
        if ($textrefFragmentReason !== '') {
            $fragment = $this->urlFragmentIdentifier($textref);
            $fragmentContext = $context + ['reason' => $textrefFragmentReason];
            if ($fragment !== '') {
                $fragmentContext['fragment'] = $fragment;
            }

            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-media-overlay-seq-textref-fragment',
                    'SMIL media overlay seq textref fragments must be non-empty fragment identifiers without whitespace.',
                    $fragmentContext
                ),
            ];
        }

        if ($zipPath === '') {
            return [];
        }

        if ($zip->locateName($zipPath) === false) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'missing-media-overlay-seq-textref-resource',
                    'SMIL media overlay seq textref does not resolve to a resource in the EPUB archive.',
                    $context
                ),
            ];
        }

        $fragment = $this->urlFragmentIdentifier($textref);
        if ($fragment === '') {
            return [];
        }

        $targetDocument = $this->mediaOverlayTargetDocument($zip, $zipPath, $targetDocuments);
        if ($targetDocument instanceof \DOMDocument && !$this->xmlDocumentHasElementId($targetDocument, $fragment)) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'missing-media-overlay-seq-textref-fragment',
                    'SMIL media overlay seq textref fragment does not resolve to an element id in the referenced resource.',
                    $context + ['fragment' => $fragment]
                ),
            ];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, \DOMDocument|false> $targetDocuments
     * @param array<string, array{order: int, fragment: string, element: string, id?: string, src?: string, textref?: string}> $targetOrderState
     * @return list<array<string, mixed>>
     */
    private function mediaOverlayTargetOrderDiagnostics(
        \ZipArchive $zip,
        string $target,
        array $context,
        string $targetKey,
        string $code,
        string $message,
        string $overlayBasePath,
        array &$targetDocuments,
        array &$targetOrderState
    ): array {
        if ($target === '') {
            return [];
        }
        if ($this->mediaOverlayReferencePathDiagnosticReason($target) !== '') {
            return [];
        }

        $zipPath = $this->localResourceZipPath($overlayBasePath, $target);
        if ($zipPath === '' || $zip->locateName($zipPath) === false) {
            return [];
        }

        $fragment = $this->urlFragmentIdentifier($target);
        if ($fragment === '') {
            return [];
        }

        $targetDocument = $this->mediaOverlayTargetDocument($zip, $zipPath, $targetDocuments);
        if (!$targetDocument instanceof \DOMDocument) {
            return [];
        }

        $order = $this->xmlDocumentElementOrder($targetDocument, $fragment);
        if ($order === null) {
            return [];
        }

        $current = [
            'order' => $order,
            'fragment' => $fragment,
            'element' => (string) ($context['element'] ?? ''),
            $targetKey => $target,
        ];
        if (isset($context['id']) && is_scalar($context['id']) && (string) $context['id'] !== '') {
            $current['id'] = (string) $context['id'];
        }

        $previous = $targetOrderState[$zipPath] ?? null;
        if (is_array($previous) && $order < $previous['order']) {
            return [
                $this->epubDiagnostic(
                    'error',
                    $code,
                    $message,
                    $context + [
                        $targetKey => $target,
                        'path' => $zipPath,
                        'fragment' => $fragment,
                        'targetOrder' => $order,
                        'previousFragment' => $previous['fragment'],
                        'previousElement' => $previous['element'],
                        'previousTargetOrder' => $previous['order'],
                    ] + (isset($previous['id']) ? ['previousId' => $previous['id']] : [])
                      + (isset($previous['src']) ? ['previousSrc' => $previous['src']] : [])
                      + (isset($previous['textref']) ? ['previousTextref' => $previous['textref']] : [])
                ),
            ];
        }

        $targetOrderState[$zipPath] = $current;

        return [];
    }

    /**
     * @param array<string, \DOMDocument|false> $targetDocuments
     */
    private function mediaOverlayTargetDocument(\ZipArchive $zip, string $zipPath, array &$targetDocuments): \DOMDocument|false
    {
        if (!array_key_exists($zipPath, $targetDocuments)) {
            $targetDocuments[$zipPath] = false;
            $targetXml = $zip->getFromName($zipPath);
            if (is_string($targetXml)) {
                try {
                    $targetDocuments[$zipPath] = $this->loadXml($targetXml, 'EPUB media overlay text target');
                } catch (\InvalidArgumentException) {
                    $targetDocuments[$zipPath] = false;
                }
            }
        }

        return $targetDocuments[$zipPath];
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function mediaOverlayAudioClipDiagnostics(\DOMElement $audio, array $context): array
    {
        $diagnostics = [];
        $seconds = [];
        foreach (['clipBegin', 'clipEnd'] as $attribute) {
            $value = trim($audio->getAttribute($attribute));
            if ($value === '') {
                continue;
            }

            $parsed = $this->smilClockSeconds($value);
            if ($parsed === null) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-media-overlay-audio-clip-time',
                    'SMIL media overlay audio clip time is not a supported clock value.',
                    $context + ['attribute' => $attribute, 'value' => $value]
                );
                continue;
            }

            $seconds[$attribute] = $parsed;
        }

        if (isset($seconds['clipBegin'], $seconds['clipEnd']) && $seconds['clipEnd'] <= $seconds['clipBegin']) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-media-overlay-audio-clip-range',
                'SMIL media overlay audio clipEnd must be after clipBegin.',
                $context + [
                    'clipBegin' => trim($audio->getAttribute('clipBegin')),
                    'clipEnd' => trim($audio->getAttribute('clipEnd')),
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<\DOMElement>
     */
    private function packageLinkElements(\DOMElement $package): array
    {
        $links = [];
        $metadataElement = $this->directOpfChildElement($package, 'metadata');
        if ($metadataElement !== null) {
            foreach ($this->directOpfChildElements($metadataElement, 'link') as $link) {
                $links[] = $link;
            }
        }

        foreach ($this->primaryPackageDescendantElements($package) as $element) {
            if (!$this->isOpfPackageElement($element, 'link')) {
                continue;
            }
            $parent = $element->parentNode;
            if (!$parent instanceof \DOMElement) {
                continue;
            }
            if ($this->isOpfPackageElement($parent, 'collection')) {
                $links[] = $element;
                continue;
            }
            $collection = $parent->parentNode;
            if ($this->isOpfPackageElement($parent, 'metadata') && $collection instanceof \DOMElement && $this->isOpfPackageElement($collection, 'collection')) {
                $links[] = $element;
            }
        }

        return $links;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageLinkDiagnosticContext(\DOMElement $element): array
    {
        $context = ['element' => $this->qualifiedName($element)];
        foreach (['id', 'rel'] as $attribute) {
            $value = trim($element->getAttribute($attribute));
            if ($value !== '') {
                $context[$attribute] = $value;
            }
        }

        $parent = $element->parentNode;
        if ($parent instanceof \DOMElement) {
            $context['parent'] = $this->qualifiedName($parent);
        }
        $collection = $this->nearestOpfCollectionAncestor($element);
        if ($collection instanceof \DOMElement) {
            $collectionId = trim($collection->getAttribute('id'));
            if ($collectionId !== '') {
                $context['collectionId'] = $collectionId;
            }
            $collectionParent = $collection->parentNode;
            if ($collectionParent instanceof \DOMElement && $this->isOpfPackageElement($collectionParent, 'collection')) {
                $parentCollectionId = trim($collectionParent->getAttribute('id'));
                if ($parentCollectionId !== '') {
                    $context['parentCollectionId'] = $parentCollectionId;
                }
            }
        }

        return $context;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function navDocumentDiagnostics(array $manifest): array
    {
        $diagnostics = [];
        $navItems = [];
        foreach ($manifest as $id => $item) {
            if (in_array('nav', $item['properties'], true)) {
                $navItems[$id] = $item;
            }
        }

        if ($navItems === []) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'missing-nav-document',
                    'EPUB3 package manifest has no item with the nav property.'
                ),
            ];
        }

        if (count($navItems) > 1) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'multiple-nav-documents',
                'EPUB3 package manifest has more than one item with the nav property.',
                ['ids' => array_keys($navItems)]
            );
        }

        foreach ($navItems as $id => $item) {
            $mediaType = strtolower(trim($item['media-type']));
            if ($mediaType !== 'application/xhtml+xml') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-nav-document-media-type',
                    'EPUB3 navigation document item must use the application/xhtml+xml media type.',
                    ['id' => $id, 'mediaType' => $item['media-type']]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function navResourceDiagnostics(\ZipArchive $zip, array $manifest, string $base_path): array
    {
        $diagnostics = [];
        foreach ($manifest as $id => $item) {
            if (!in_array('nav', $item['properties'], true)) {
                continue;
            }
            if (!$this->mediaTypeMatches($item['media-type'], 'application/xhtml+xml')) {
                continue;
            }

            $path = $this->packageResourceZipPath($base_path, $item['href']);
            if ($path === '' || $zip->locateName($path) === false) {
                continue;
            }
            $xml = $zip->getFromName($path);
            if (!is_string($xml)) {
                continue;
            }

            $context = [
                'id' => $id,
                'href' => $item['href'],
                'path' => $path,
            ];
            try {
                $dom = $this->loadXml($xml, 'EPUB nav document');
            } catch (\Throwable) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'malformed-nav-document',
                    'EPUB3 navigation document is not valid XML.',
                    $context
                );
                continue;
            }

            $declaredPrefixes = $this->xhtmlPropertyPrefixNames($dom);
            $navs = $this->navElements($dom);
            $diagnostics = array_merge($diagnostics, $this->navDocumentXhtmlDiagnostics($dom, $navs, $context));
            $diagnostics = array_merge($diagnostics, $this->navElementTypeDiagnostics($navs, $context, $declaredPrefixes));
            $tocNavs = array_values(array_filter(
                $navs,
                fn (\DOMElement $nav): bool => $this->tokenListContains(strtolower($this->attributeByLocalName($nav, 'type')), 'toc')
            ));
            $landmarkNavs = array_values(array_filter(
                $navs,
                fn (\DOMElement $nav): bool => $this->tokenListContains(strtolower($this->attributeByLocalName($nav, 'type')), 'landmarks')
            ));
            $pageListNavs = array_values(array_filter(
                $navs,
                fn (\DOMElement $nav): bool => $this->tokenListContains(strtolower($this->attributeByLocalName($nav, 'type')), 'page-list')
                    || $this->tokenListContains(strtolower($this->attributeByLocalName($nav, 'type')), 'pagebreaks')
            ));

            if ($tocNavs === []) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-nav-toc',
                    'EPUB3 navigation document has no toc nav element.',
                    $context
                );
            } else {
                if (count($tocNavs) > 1) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'multiple-nav-toc',
                        'EPUB3 navigation document has more than one toc nav element.',
                        $context + ['count' => count($tocNavs)]
                    );
                }
                foreach ($tocNavs as $tocNav) {
                    $diagnostics = array_merge($diagnostics, $this->tocNavStructureDiagnostics($tocNav, $context));
                    if (!$this->navElementHasOrderedListLinkedAnchor($tocNav)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'missing-nav-toc-entry',
                            'EPUB3 toc nav element has no linked entries.',
                            $context + $this->navElementDiagnosticContext($tocNav)
                        );
                    }
                }
            }
            if (count($landmarkNavs) > 1) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'multiple-nav-landmarks',
                    'EPUB3 navigation document has more than one landmarks nav element.',
                    $context + ['count' => count($landmarkNavs)]
                );
            }
            if (count($pageListNavs) > 1) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'multiple-nav-page-list',
                    'EPUB3 navigation document has more than one page-list nav element.',
                    $context + ['count' => count($pageListNavs)]
                );
            }
            foreach ($landmarkNavs as $landmarkNav) {
                $diagnostics = array_merge($diagnostics, $this->landmarkNavStructureDiagnostics($landmarkNav, $context, $path, $declaredPrefixes));
            }
            foreach ($pageListNavs as $pageListNav) {
                $diagnostics = array_merge($diagnostics, $this->pageListNavStructureDiagnostics($pageListNav, $context, $path, $declaredPrefixes));
            }

            $targetDocuments = [];
            foreach ($navs as $nav) {
                foreach ($nav->getElementsByTagName('*') as $element) {
                    if (!$element instanceof \DOMElement || $element->localName !== 'a') {
                        continue;
                    }
                    $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if ($href === '') {
                        continue;
                    }

                    $linkContext = $context + $this->navElementDiagnosticContext($nav) + [
                        'linkHref' => $href,
                    ];
                    $label = $this->xhtmlNavLabelText($element);
                    if ($label !== '') {
                        $linkContext['label'] = $label;
                    }
                    if ($this->navElementIsPageList($nav)) {
                        $value = $this->pageListAnchorValue($element);
                        if ($value !== '') {
                            $linkContext['value'] = $value;
                        }
                    }

                    $hrefPathReason = $this->navLinkHrefPathDiagnosticReason($href);
                    if ($hrefPathReason !== '') {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-nav-link-href-path',
                            'EPUB3 navigation link href must be an absolute URL or a path-relative scheme-less URL.',
                            $linkContext + ['reason' => $hrefPathReason]
                        );
                        continue;
                    }
                    $targetPath = '';
                    if ($this->isPackageRelativeResourceUrl($href)) {
                        $targetPath = $this->localResourceZipPath($this->dirname($path), $href);
                        if ($targetPath !== '') {
                            $linkContext['targetPath'] = $targetPath;
                        }
                    }
                    $hrefFragmentReason = $this->navLinkHrefFragmentDiagnosticReason($href);
                    if ($hrefFragmentReason !== '') {
                        $fragment = $this->urlFragmentIdentifier($href);
                        $fragmentContext = $linkContext + ['reason' => $hrefFragmentReason];
                        if ($fragment !== '') {
                            $fragmentContext['fragment'] = $fragment;
                        }
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-nav-link-href-fragment',
                            'EPUB3 navigation link href fragments must be non-empty fragment identifiers without whitespace.',
                            $fragmentContext
                        );
                        continue;
                    }
                    if (!$this->isPackageRelativeResourceUrl($href)) {
                        continue;
                    }

                    if ($targetPath === '') {
                        continue;
                    }

                    if ($zip->locateName($targetPath) === false) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'missing-nav-link-resource',
                            'EPUB3 navigation link href does not resolve to a resource in the EPUB archive.',
                            $linkContext
                        );
                        continue;
                    }

                    $fragment = $this->urlFragmentIdentifier($href);
                    if ($fragment === '') {
                        continue;
                    }

                    if (!array_key_exists($targetPath, $targetDocuments)) {
                        $targetXml = $zip->getFromName($targetPath);
                        $targetDocuments[$targetPath] = null;
                        if (is_string($targetXml)) {
                            try {
                                $targetDocuments[$targetPath] = $this->loadXml($targetXml, 'EPUB nav link target');
                            } catch (\Throwable) {
                                $targetDocuments[$targetPath] = null;
                            }
                        }
                    }

                    $targetDocument = $targetDocuments[$targetPath];
                    $targetElement = $targetDocument instanceof \DOMDocument
                        ? $this->xmlDocumentElementById($targetDocument, $fragment)
                        : null;
                    if ($targetDocument instanceof \DOMDocument && !$targetElement instanceof \DOMElement) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'missing-nav-link-fragment',
                            'EPUB3 navigation link href fragment does not resolve to an element id in the referenced resource.',
                            $linkContext + ['fragment' => $fragment]
                        );
                        continue;
                    }

                    if ($targetElement instanceof \DOMElement && $this->navElementIsPageList($nav)) {
                        if (!$this->xhtmlElementHasPagebreakSemantics($targetElement)) {
                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                'nav-page-list-target-not-pagebreak',
                                'EPUB3 page-list navigation links should target elements with pagebreak semantics.',
                                $linkContext + $this->pagebreakTargetDiagnosticContext($targetElement, $fragment)
                            );
                            continue;
                        }

                        $pageListValue = isset($linkContext['value']) && is_string($linkContext['value'])
                            ? $linkContext['value']
                            : $label;
                        $targetValue = $this->pagebreakTargetValue($targetElement);
                        if ($pageListValue !== '' && $targetValue !== '' && $pageListValue !== $targetValue) {
                            $diagnostics[] = $this->epubDiagnostic(
                                'warning',
                                'nav-page-list-value-mismatch',
                                'EPUB3 page-list navigation link value should match the referenced pagebreak value.',
                                $linkContext + $this->pagebreakTargetDiagnosticContext($targetElement, $fragment) + [
                                    'pageListValue' => $pageListValue,
                                    'targetPagebreakValue' => $targetValue,
                                ]
                            );
                        }
                    }
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function tocNavStructureDiagnostics(\DOMElement $nav, array $context): array
    {
        $diagnostics = [];
        foreach ($this->navAnchors($nav) as $anchor) {
            if ($this->navAnchorListItemAncestor($anchor, $nav) instanceof \DOMElement) {
                continue;
            }

            $linkContext = $context + $this->navElementDiagnosticContext($nav);
            $href = html_entity_decode(trim($anchor->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href !== '') {
                $linkContext['linkHref'] = $href;
            }
            $label = $this->xhtmlNavLabelText($anchor);
            if ($label !== '') {
                $linkContext['label'] = $label;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-nav-toc-link-parent',
                'EPUB3 toc nav links must be contained in list item entries.',
                $linkContext
            );
        }

        return array_merge($diagnostics, $this->navListItemParentDiagnostics($nav, 'toc', $context));
    }

    /**
     * @param list<\DOMElement> $navs
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function navDocumentXhtmlDiagnostics(\DOMDocument $dom, array $navs, array $context): array
    {
        $diagnostics = [];
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'html') {
            $rootContext = $context + [
                'element' => $root instanceof \DOMElement ? $root->localName : '',
                'namespace' => $root instanceof \DOMElement ? ($root->namespaceURI ?? '') : '',
                'expectedElement' => 'html',
                'expectedNamespace' => self::XHTML_NAMESPACE,
            ];
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-nav-document-root',
                'EPUB3 navigation document must use an XHTML html root element.',
                $rootContext
            );
        } elseif (($root->namespaceURI ?? '') !== self::XHTML_NAMESPACE) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-nav-document-root-namespace',
                'EPUB3 navigation document root element must use the XHTML namespace.',
                $context + [
                    'element' => $root->localName,
                    'namespace' => $root->namespaceURI ?? '',
                    'expectedNamespace' => self::XHTML_NAMESPACE,
                ]
            );
        }

        foreach ($navs as $nav) {
            if (($nav->namespaceURI ?? '') === self::XHTML_NAMESPACE) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-nav-element-namespace',
                'EPUB3 navigation document nav elements must use the XHTML namespace.',
                $context + $this->navElementDiagnosticContext($nav) + [
                    'element' => $nav->localName,
                    'namespace' => $nav->namespaceURI ?? '',
                    'expectedNamespace' => self::XHTML_NAMESPACE,
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<\DOMElement>
     */
    private function navElements(\DOMDocument $dom): array
    {
        $navs = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'nav') {
                $navs[] = $element;
            }
        }

        return $navs;
    }

    private function navElementHasOrderedListLinkedAnchor(\DOMElement $nav): bool
    {
        return $this->navOrderedListLinkedAnchors($nav) !== [];
    }

    private function navAnchorHasListItemAncestor(\DOMElement $anchor, \DOMElement $nav): bool
    {
        return $this->navAnchorListItemAncestor($anchor, $nav) instanceof \DOMElement;
    }

    private function navAnchorListItemAncestor(\DOMElement $anchor, \DOMElement $nav): ?\DOMElement
    {
        $parent = $anchor->parentNode;
        while ($parent instanceof \DOMElement) {
            if ($parent->isSameNode($nav)) {
                return null;
            }
            if ($parent->localName === 'li') {
                return $parent;
            }

            $parent = $parent->parentNode;
        }

        return null;
    }

    private function navElementIsPageList(\DOMElement $nav): bool
    {
        $type = strtolower($this->attributeByLocalName($nav, 'type'));

        return $this->tokenListContains($type, 'page-list') || $this->tokenListContains($type, 'pagebreaks');
    }

    /**
     * @param list<\DOMElement> $navs
     * @param array<string, mixed> $context
     * @param array<string, true> $declaredPrefixes
     * @return list<array<string, mixed>>
     */
    private function navElementTypeDiagnostics(array $navs, array $context, array $declaredPrefixes): array
    {
        $diagnostics = [];
        foreach ($navs as $nav) {
            $type = strtolower(trim($this->attributeByLocalName($nav, 'type')));
            if ($type === '') {
                continue;
            }

            $tokens = array_values(array_filter(preg_split('/\s+/', $type) ?: [], static fn (string $token): bool => $token !== ''));
            foreach ($tokens as $token) {
                if (!$this->validPropertyValue($token)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-nav-type',
                        'EPUB3 navigation element epub:type values must be valid property data type values.',
                        $context + $this->navElementDiagnosticContext($nav) + ['value' => $token]
                    );
                    continue;
                }

                if (!$this->propertyValuePrefixIsDeclared($token, $declaredPrefixes)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-nav-type-prefix',
                        'EPUB3 navigation element epub:type prefix must be reserved or declared in the navigation document prefix attribute.',
                        $context + $this->navElementDiagnosticContext($nav) + [
                            'value' => $token,
                            'prefix' => $this->propertyValuePrefix($token),
                        ]
                    );
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function navElementDiagnosticContext(\DOMElement $nav): array
    {
        $context = [];
        $id = trim($nav->getAttribute('id'));
        if ($id !== '') {
            $context['navId'] = $id;
        }
        $type = trim($this->attributeByLocalName($nav, 'type'));
        if ($type !== '') {
            $context['type'] = $type;
        }

        return $context;
    }

    private function navLinkHrefPathDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($href)) {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return 'absolute-path';
        }
        if (str_contains($href, '\\')) {
            return 'backslash';
        }

        [$hrefPath, $suffix] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '' && $suffix !== '') {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($hrefPath);
    }

    private function navLinkHrefFragmentDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '' || !str_contains($href, '#') || $this->isAbsoluteUrl($href) || str_starts_with($href, '#')) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($href);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, true> $declaredPrefixes
     * @return list<array<string, mixed>>
     */
    private function landmarkNavStructureDiagnostics(\DOMElement $nav, array $context, string $navPath, array $declaredPrefixes): array
    {
        $diagnostics = $this->flatSpecializedNavDiagnostics($nav, 'landmarks', $context);
        $diagnostics = array_merge($diagnostics, $this->specializedNavLinkParentDiagnostics($nav, 'landmarks', $context));
        $diagnostics = array_merge($diagnostics, $this->specializedNavMissingHrefDiagnostics($nav, 'landmarks', $context));
        $diagnostics = array_merge($diagnostics, $this->specializedNavMissingLinkElementDiagnostics($nav, 'landmarks', $context));
        $anchors = $this->navOrderedListLinkedAnchors($nav);
        if ($anchors === []) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-nav-landmark-entry',
                'EPUB3 landmarks nav element has no linked entries.',
                $context + $this->navElementDiagnosticContext($nav)
            );

            return $diagnostics;
        }

        $seen = [];
        foreach ($anchors as $anchor) {
            $href = html_entity_decode(trim($anchor->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $type = strtolower(trim($this->attributeByLocalName($anchor, 'type')));
            $linkContext = $context + $this->navElementDiagnosticContext($nav) + [
                'linkHref' => $href,
            ];
            $label = $this->xhtmlNavLabelText($anchor);
            if ($label !== '') {
                $linkContext['label'] = $label;
            } else {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-nav-landmark-label',
                    'EPUB3 landmarks links must provide visible text or an accessible text alternative.',
                    $linkContext
                );
            }

            $types = array_values(array_filter(preg_split('/\s+/', $type) ?: [], static fn (string $token): bool => $token !== ''));
            if ($types === []) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-nav-landmark-link-type',
                    'EPUB3 landmarks links must declare an epub:type value.',
                    $linkContext
                );
                continue;
            }

            foreach ($types as $token) {
                if (!$this->validPropertyValue($token)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-nav-landmark-link-type',
                        'EPUB3 landmarks link epub:type values must be valid property data type values.',
                        array_merge($linkContext, ['type' => $token])
                    );
                    continue;
                }

                if (!$this->propertyValuePrefixIsDeclared($token, $declaredPrefixes)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-nav-landmark-link-type-prefix',
                        'EPUB3 landmarks link epub:type prefix must be reserved or declared in the navigation document prefix attribute.',
                        array_merge($linkContext, [
                            'type' => $token,
                            'prefix' => $this->propertyValuePrefix($token),
                        ])
                    );
                }
            }

            if ($this->navLinkHrefPathDiagnosticReason($href) !== '') {
                continue;
            }
            $target = $this->normalizedNavigationReference($navPath, $href);
            foreach ($types as $token) {
                if (!$this->validPropertyValue($token) || !$this->propertyValuePrefixIsDeclared($token, $declaredPrefixes)) {
                    continue;
                }

                $key = $token . "\0" . $target;
                if (isset($seen[$key])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-nav-landmark-link',
                        'EPUB3 landmarks nav contains duplicate epub:type and target pairs.',
                        array_merge($linkContext, [
                            'type' => $token,
                            'target' => $target,
                            'firstLinkHref' => $seen[$key],
                        ])
                    );
                    continue;
                }
                $seen[$key] = $href;
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, true> $declaredPrefixes
     * @return list<array<string, mixed>>
     */
    private function pageListNavStructureDiagnostics(\DOMElement $nav, array $context, string $navPath, array $declaredPrefixes): array
    {
        $diagnostics = $this->flatSpecializedNavDiagnostics($nav, 'page-list', $context);
        $diagnostics = array_merge($diagnostics, $this->specializedNavLinkParentDiagnostics($nav, 'page-list', $context));
        $diagnostics = array_merge($diagnostics, $this->specializedNavMissingHrefDiagnostics($nav, 'page-list', $context));
        $diagnostics = array_merge($diagnostics, $this->specializedNavMissingLinkElementDiagnostics($nav, 'page-list', $context));
        $anchors = $this->navOrderedListLinkedAnchors($nav);
        if ($anchors === []) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-nav-page-list-entry',
                'EPUB3 page-list nav element has no linked entries.',
                $context + $this->navElementDiagnosticContext($nav)
            );

            return $diagnostics;
        }

        $seen = [];
        foreach ($anchors as $anchor) {
            $href = html_entity_decode(trim($anchor->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $linkContext = $context + $this->navElementDiagnosticContext($nav) + [
                'linkHref' => $href,
            ];
            $label = $this->xhtmlNavLabelText($anchor);
            if ($label !== '') {
                $linkContext['label'] = $label;
            } else {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-nav-page-list-label',
                    'EPUB3 page-list links must provide visible text or an accessible text alternative.',
                    $linkContext
                );
            }
            $value = $this->pageListAnchorValue($anchor);
            if ($value !== '') {
                $linkContext['value'] = $value;
            }

            $type = strtolower(trim($this->attributeByLocalName($anchor, 'type')));
            $types = array_values(array_filter(preg_split('/\s+/', $type) ?: [], static fn (string $token): bool => $token !== ''));
            foreach ($types as $token) {
                if (!$this->validPropertyValue($token)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-nav-page-list-link-type',
                        'EPUB3 page-list link epub:type values must be valid property data type values.',
                        array_merge($linkContext, ['type' => $token])
                    );
                    continue;
                }

                if (!$this->propertyValuePrefixIsDeclared($token, $declaredPrefixes)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-nav-page-list-link-type-prefix',
                        'EPUB3 page-list link epub:type prefix must be reserved or declared in the navigation document prefix attribute.',
                        array_merge($linkContext, [
                            'type' => $token,
                            'prefix' => $this->propertyValuePrefix($token),
                        ])
                    );
                }
            }

            if ($this->navLinkHrefPathDiagnosticReason($href) !== '') {
                continue;
            }
            $target = $this->normalizedNavigationReference($navPath, $href);
            $linkContext['target'] = $target;
            if (isset($seen[$target])) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-nav-page-list-target',
                    'EPUB3 page-list nav contains duplicate targets.',
                    $linkContext + ['firstLinkHref' => $seen[$target]]
                );
                continue;
            }
            $seen[$target] = $href;
        }

        return $diagnostics;
    }

    private function pageListAnchorValue(\DOMElement $anchor): string
    {
        $value = trim($this->attributeByLocalName($anchor, 'value'));
        if ($value !== '') {
            return $value;
        }

        $parent = $anchor->parentNode;
        while ($parent instanceof \DOMElement) {
            if ($parent->localName === 'li') {
                return trim($this->attributeByLocalName($parent, 'value'));
            }
            if ($parent->localName === 'nav') {
                return '';
            }
            $parent = $parent->parentNode;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function specializedNavLinkParentDiagnostics(\DOMElement $nav, string $navType, array $context): array
    {
        $diagnostics = [];
        foreach ($this->navAnchors($nav) as $anchor) {
            if ($this->navAnchorListItemAncestor($anchor, $nav) instanceof \DOMElement) {
                continue;
            }

            $linkContext = $context + $this->navElementDiagnosticContext($nav);
            $href = html_entity_decode(trim($anchor->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href !== '') {
                $linkContext['linkHref'] = $href;
            }
            $label = $this->xhtmlNavLabelText($anchor);
            if ($label !== '') {
                $linkContext['label'] = $label;
            }
            if ($navType === 'page-list') {
                $value = $this->pageListAnchorValue($anchor);
                if ($value !== '') {
                    $linkContext['value'] = $value;
                }
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                $navType === 'page-list' ? 'invalid-nav-page-list-link-parent' : 'invalid-nav-landmark-link-parent',
                $navType === 'page-list'
                    ? 'EPUB3 page-list nav links must be contained in list item entries.'
                    : 'EPUB3 landmarks nav links must be contained in list item entries.',
                $linkContext
            );
        }

        return array_merge($diagnostics, $this->navListItemParentDiagnostics($nav, $navType, $context));
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function navListItemParentDiagnostics(\DOMElement $nav, string $navType, array $context): array
    {
        $diagnostics = [];
        foreach ($this->navListItems($nav) as $item) {
            if ($this->navListItemHasOrderedListParent($item)) {
                continue;
            }

            $labelElement = $this->firstNavListItemLabelElement($item, 'a');
            if (!$labelElement instanceof \DOMElement) {
                $labelElement = $this->firstNavListItemLabelElement($item, 'span');
            }
            if (!$labelElement instanceof \DOMElement) {
                continue;
            }

            $linkContext = $context + $this->navElementDiagnosticContext($nav) + [
                'parentElement' => $item->parentNode instanceof \DOMElement ? $item->parentNode->localName : '',
                'expectedParentElement' => 'ol',
            ];
            if ($labelElement->localName === 'a') {
                $href = html_entity_decode(trim($labelElement->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($href !== '') {
                    $linkContext['linkHref'] = $href;
                }
            }
            $label = $this->xhtmlNavLabelText($labelElement);
            if ($label !== '') {
                $linkContext['label'] = $label;
            }
            $entryType = strtolower(trim($this->attributeByLocalName($labelElement, 'type')));
            if ($entryType === '') {
                $entryType = strtolower(trim($this->attributeByLocalName($item, 'type')));
            }
            if ($entryType !== '') {
                $linkContext['entryType'] = $entryType;
            }
            if ($navType === 'page-list') {
                $value = $labelElement->localName === 'a'
                    ? $this->pageListAnchorValue($labelElement)
                    : trim($this->attributeByLocalName($labelElement, 'value'));
                if ($value === '') {
                    $value = trim($this->attributeByLocalName($item, 'value'));
                }
                if ($value !== '') {
                    $linkContext['value'] = $value;
                }
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                match ($navType) {
                    'page-list' => 'invalid-nav-page-list-list-item-parent',
                    'landmarks' => 'invalid-nav-landmark-list-item-parent',
                    default => 'invalid-nav-toc-list-item-parent',
                },
                match ($navType) {
                    'page-list' => 'EPUB3 page-list nav list item entries must be contained in ordered lists.',
                    'landmarks' => 'EPUB3 landmarks nav list item entries must be contained in ordered lists.',
                    default => 'EPUB3 toc nav list item entries must be contained in ordered lists.',
                },
                $linkContext
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function specializedNavMissingLinkElementDiagnostics(\DOMElement $nav, string $navType, array $context): array
    {
        $diagnostics = [];
        foreach ($this->navListItems($nav) as $item) {
            if (!$this->navListItemHasOrderedListParent($item)) {
                continue;
            }
            if ($this->firstNavListItemLabelElement($item, 'a') instanceof \DOMElement) {
                continue;
            }

            $labelElement = $this->firstNavListItemLabelElement($item, 'span');
            if (!$labelElement instanceof \DOMElement) {
                continue;
            }

            $label = $this->xhtmlNavLabelText($labelElement);
            if ($label === '') {
                continue;
            }

            $linkContext = $context + $this->navElementDiagnosticContext($nav) + [
                'label' => $label,
            ];
            $type = strtolower(trim($this->attributeByLocalName($labelElement, 'type')));
            if ($type === '') {
                $type = strtolower(trim($this->attributeByLocalName($item, 'type')));
            }
            if ($type !== '') {
                $linkContext['entryType'] = $type;
            }
            if ($navType === 'page-list') {
                $value = trim($this->attributeByLocalName($labelElement, 'value'));
                if ($value === '') {
                    $value = trim($this->attributeByLocalName($item, 'value'));
                }
                if ($value !== '') {
                    $linkContext['value'] = $value;
                }
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                $navType === 'page-list' ? 'missing-nav-page-list-link-href' : 'missing-nav-landmark-link-href',
                $navType === 'page-list'
                    ? 'EPUB3 page-list links must declare an href target.'
                    : 'EPUB3 landmarks links must declare an href target.',
                $linkContext
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function specializedNavMissingHrefDiagnostics(\DOMElement $nav, string $navType, array $context): array
    {
        $diagnostics = [];
        foreach ($this->navAnchors($nav) as $anchor) {
            if (trim($anchor->getAttribute('href')) !== '') {
                continue;
            }

            $linkContext = $context + $this->navElementDiagnosticContext($nav);
            $label = $this->xhtmlNavLabelText($anchor);
            if ($label !== '') {
                $linkContext['label'] = $label;
            }
            if ($navType === 'page-list') {
                $value = $this->pageListAnchorValue($anchor);
                if ($value !== '') {
                    $linkContext['value'] = $value;
                }
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                $navType === 'page-list' ? 'missing-nav-page-list-link-href' : 'missing-nav-landmark-link-href',
                $navType === 'page-list'
                    ? 'EPUB3 page-list links must declare an href target.'
                    : 'EPUB3 landmarks links must declare an href target.',
                $linkContext
            );
        }

        return $diagnostics;
    }

    private function xhtmlElementHasPagebreakSemantics(\DOMElement $element): bool
    {
        $epubType = strtolower($this->epubTypeAttribute($element));
        $role = strtolower($this->attributeByLocalName($element, 'role'));

        return $this->tokenListContains($epubType, 'pagebreak') || $this->tokenListContains($role, 'doc-pagebreak');
    }

    private function pagebreakTargetValue(\DOMElement $element): string
    {
        foreach (['title', 'aria-label'] as $attribute) {
            $value = $this->normalizedXhtmlText($this->attributeByLocalName($element, $attribute));
            if ($value !== '') {
                return $value;
            }
        }

        return $this->normalizedXhtmlText($element->textContent);
    }

    /**
     * @return array<string, mixed>
     */
    private function pagebreakTargetDiagnosticContext(\DOMElement $element, string $fragment): array
    {
        $context = [
            'fragment' => $fragment,
            'targetElement' => $element->localName,
        ];
        $epubType = trim($this->epubTypeAttribute($element));
        if ($epubType !== '') {
            $context['targetEpubType'] = $epubType;
        }
        $role = trim($this->attributeByLocalName($element, 'role'));
        if ($role !== '') {
            $context['targetRole'] = $role;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function flatSpecializedNavDiagnostics(\DOMElement $nav, string $navType, array $context): array
    {
        $olCount = 0;
        foreach ($nav->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'ol') {
                $olCount++;
            }
        }

        if ($olCount <= 1) {
            return [];
        }

        $code = $navType === 'page-list' ? 'non-flat-nav-page-list' : 'non-flat-nav-landmarks';
        return [
            $this->epubDiagnostic(
                'warning',
                $code,
                'EPUB3 specialized navigation element should contain only a single ordered list.',
                $context + $this->navElementDiagnosticContext($nav) + ['orderedListCount' => $olCount]
            ),
        ];
    }

    /**
     * @return list<\DOMElement>
     */
    private function navListItems(\DOMElement $nav): array
    {
        $items = [];
        foreach ($nav->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'li') {
                $items[] = $element;
            }
        }

        return $items;
    }

    private function navListItemHasOrderedListParent(\DOMElement $item): bool
    {
        return $item->parentNode instanceof \DOMElement && $item->parentNode->localName === 'ol';
    }

    /**
     * @return list<\DOMElement>
     */
    private function navLinkedAnchors(\DOMElement $nav): array
    {
        $anchors = [];
        foreach ($this->navAnchors($nav) as $element) {
            if (trim($element->getAttribute('href')) !== '') {
                $anchors[] = $element;
            }
        }

        return $anchors;
    }

    /**
     * @return list<\DOMElement>
     */
    private function navOrderedListLinkedAnchors(\DOMElement $nav): array
    {
        $anchors = [];
        foreach ($this->navLinkedAnchors($nav) as $anchor) {
            $item = $this->navAnchorListItemAncestor($anchor, $nav);
            if ($item instanceof \DOMElement && $this->navListItemHasOrderedListParent($item)) {
                $anchors[] = $anchor;
            }
        }

        return $anchors;
    }

    /**
     * @return list<\DOMElement>
     */
    private function navAnchors(\DOMElement $nav): array
    {
        $anchors = [];
        foreach ($nav->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'a') {
                $anchors[] = $element;
            }
        }

        return $anchors;
    }

    private function normalizedNavigationReference(string $navPath, string $href): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return $href;
        }

        [, $suffix] = $this->splitUrlPathSuffix($href);
        $targetPath = $this->localResourceZipPath($this->dirname($navPath), $href);

        return $targetPath === '' ? $href : $targetPath . $suffix;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, true> $declaredPrefixes
     * @return list<array<string, mixed>>
     */
    private function manifestDiagnostics(\ZipArchive $zip, \DOMElement $manifestElement, array $manifest, string $base_path, string $rootfile, bool $isEpub3Package, array $declaredPrefixes): array
    {
        $diagnostics = [];
        $ids = [];
        $paths = [];
        $coverImageItems = [];
        $itemElements = $this->directOpfChildElements($manifestElement, 'item');
        foreach ($manifestElement->childNodes as $child) {
            if (!$child instanceof \DOMElement || $this->isOpfPackageElement($child, 'item')) {
                continue;
            }

            if ($child->localName === 'item') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-manifest-item-namespace',
                    'OPF manifest item elements must use the OPF namespace.',
                    [
                        'element' => $this->qualifiedName($child),
                        'namespace' => $child->namespaceURI ?? '',
                    ]
                );
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-manifest-child-element',
                'OPF manifest must contain only item child elements.',
                ['element' => $this->qualifiedName($child)]
            );
        }

        if ($itemElements === []) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'empty-manifest',
                'OPF manifest must contain at least one item element.'
            );
        }

        foreach ($itemElements as $itemElement) {
            $id = trim($itemElement->getAttribute('id'));
            $href = html_entity_decode(trim($itemElement->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $mediaType = trim($itemElement->getAttribute('media-type'));
            $properties = $this->attributeTokenList($itemElement, 'properties');
            $normalizedProperties = array_map('strtolower', $properties);
            if (in_array('cover-image', $normalizedProperties, true)) {
                $coverImageContext = [];
                if ($id !== '') {
                    $coverImageContext['id'] = $id;
                }
                if ($href !== '') {
                    $coverImageContext['href'] = $href;
                    $path = $this->packageResourceZipPath($base_path, $href);
                    if ($path !== '') {
                        $coverImageContext['path'] = $path;
                    }
                }
                if ($mediaType !== '') {
                    $coverImageContext['mediaType'] = $mediaType;
                }
                $coverImageItems[] = $coverImageContext;
                if ($mediaType !== '' && $this->validMediaType($mediaType) && !$this->mediaTypeIsImage($mediaType)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-cover-image-property-media-type',
                        'Manifest cover-image properties must be declared on raster or vector image resources.',
                        $coverImageContext
                    );
                }
            }
            if ($id === '') {
                $diagnostics[] = $this->epubDiagnostic('error', 'missing-manifest-id', 'Manifest item is missing an id.');
            } else {
                if (!$this->validXmlId($id)) {
                    $context = ['id' => $id];
                    if ($href !== '') {
                        $context['href'] = $href;
                    }
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-manifest-id',
                        'Manifest item id must be an XML NCName.',
                        $context
                    );
                }

                if (isset($ids[$id])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-manifest-id',
                        'Manifest item id is duplicated.',
                        ['id' => $id]
                    );
                } else {
                    $ids[$id] = true;
                }
            }

            if ($href === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-manifest-href',
                    'Manifest item is missing an href.',
                    $id === '' ? [] : ['id' => $id]
                );
            } else {
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->manifestHrefUrlDiagnostics($href, $id, $mediaType, $properties)
                );
                if (str_contains($href, '#')) {
                    $context = ['href' => $href];
                    if ($id !== '') {
                        $context['id'] = $id;
                    }
                    if ($mediaType !== '') {
                        $context['mediaType'] = $mediaType;
                    }
                    $path = $this->packageResourceZipPath($base_path, $href);
                    if ($path !== '') {
                        $context['path'] = $path;
                    }
                    $fragment = $this->urlFragmentIdentifier($href);
                    if ($fragment !== '') {
                        $context['fragment'] = $fragment;
                    }
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-manifest-href-fragment',
                        'Manifest item href must not contain a fragment identifier.',
                        $context
                    );
                }
                $hrefPathReason = $this->manifestHrefPathDiagnosticReason($href);
                if ($hrefPathReason !== '') {
                    $context = ['href' => $href, 'reason' => $hrefPathReason];
                    if ($id !== '') {
                        $context['id'] = $id;
                    }
                    if ($mediaType !== '') {
                        $context['mediaType'] = $mediaType;
                    }
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-manifest-href-path',
                        'Manifest item href must be an absolute URL or a path-relative scheme-less URL.',
                        $context
                    );
                } else {
                    $path = $this->packageResourcePath($base_path, $href);
                    if (isset($paths[$path]) && $id !== '') {
                        $diagnostics[] = $this->epubDiagnostic(
                            'warning',
                            'duplicate-manifest-href',
                            'Manifest item href is used by more than one item.',
                            ['path' => $path, 'id' => $id, 'firstId' => $paths[$path]]
                        );
                    } elseif ($id !== '') {
                        $paths[$path] = $id;
                    }

                    $zipPath = $this->packageResourceZipPath($base_path, $href);
                    if ($zipPath !== '' && $zip->locateName($zipPath) === false) {
                        $context = ['href' => $href, 'path' => $zipPath];
                        if ($id !== '') {
                            $context['id'] = $id;
                        }
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'missing-manifest-resource',
                            'Manifest item href does not resolve to a package resource in the EPUB archive.',
                            $context
                        );
                    }
                }
            }
            $diagnostics = array_merge(
                $diagnostics,
                $this->manifestRestrictedResourceDiagnostics($rootfile, $base_path, $id, $href, $mediaType)
            );

            if ($mediaType === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-manifest-media-type',
                    'Manifest item is missing a media-type.',
                    $id === '' ? [] : ['id' => $id]
                );
            } elseif (!$this->validMediaType($mediaType)) {
                $context = ['mediaType' => $mediaType];
                if ($id !== '') {
                    $context['id'] = $id;
                }
                if ($href !== '') {
                    $context['href'] = $href;
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-manifest-media-type',
                    'Manifest item media-type must be a valid media type.',
                    $context
                );
            }

            foreach ($this->duplicateAttributeTokens($itemElement, 'properties') as $property) {
                $context = ['property' => $property];
                if ($id !== '') {
                    $context['id'] = $id;
                }
                if ($href !== '') {
                    $context['href'] = $href;
                }
                if ($mediaType !== '') {
                    $context['mediaType'] = $mediaType;
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-manifest-property',
                    'Manifest item properties must not contain duplicate property tokens.',
                    $context
                );
            }

            foreach ($properties as $property) {
                $context = ['property' => $property];
                if ($id !== '') {
                    $context['id'] = $id;
                }
                if ($href !== '') {
                    $context['href'] = $href;
                }
                if ($mediaType !== '') {
                    $context['mediaType'] = $mediaType;
                }
                if (!$this->validPropertyValue($property)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-manifest-property',
                        'Manifest item properties values must be valid property data type values.',
                        $context
                    );
                } elseif (!$this->propertyValuePrefixIsDeclared($property, $declaredPrefixes)) {
                    [$propertyPrefix] = explode(':', $property, 2);
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-manifest-property-prefix',
                        'Manifest item properties prefix must be reserved or declared in package prefix.',
                        $context + ['prefix' => $propertyPrefix]
                    );
                }
            }

            foreach ([
                'fallback' => 'missing-manifest-fallback',
                'fallback-style' => 'missing-manifest-fallback-style',
                'media-overlay' => 'missing-manifest-media-overlay',
            ] as $attribute => $code) {
                $target = trim($itemElement->getAttribute($attribute));
                if ($target === '') {
                    continue;
                }

                if (!$this->validXmlId($target)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-manifest-' . $attribute . '-idref',
                        'Manifest item reference attributes must be XML IDREF values.',
                        ['id' => $id, 'attribute' => $attribute, 'target' => $target]
                    );
                }

                if (!isset($manifest[$target])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        $code,
                        'Manifest item references a missing manifest target.',
                        ['id' => $id, 'attribute' => $attribute, 'target' => $target]
                    );
                    continue;
                }

                if ($attribute === 'fallback-style' && !$this->mediaTypeMatches($manifest[$target]['media-type'], 'text/css')) {
                    $context = [
                        'id' => $id,
                        'attribute' => $attribute,
                        'target' => $target,
                        'targetHref' => $manifest[$target]['href'],
                        'targetMediaType' => $manifest[$target]['media-type'],
                    ];
                    if ($href !== '') {
                        $context['href'] = $href;
                    }
                    if ($mediaType !== '') {
                        $context['mediaType'] = $mediaType;
                    }
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-manifest-fallback-style-media-type',
                        'Manifest fallback-style targets must reference CSS resources.',
                        $context
                    );
                }
            }

            if ($isEpub3Package && $id !== '') {
                $fallbackCoreMediaDiagnostic = $this->manifestFallbackCoreMediaTypeDiagnostic($id, $manifest, $base_path);
                if ($fallbackCoreMediaDiagnostic !== null) {
                    $diagnostics[] = $fallbackCoreMediaDiagnostic;
                }
            }

            if ($isEpub3Package && $id !== '' && $href !== '' && $mediaType !== '') {
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->requiredManifestPropertyDiagnostics($zip, $base_path, $id, $href, $mediaType, $properties)
                );
            }
        }

        foreach ($this->manifestFallbackCycles($manifest) as $cycle) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'manifest-fallback-cycle',
                'Manifest fallback chain contains a cycle.',
                ['cycle' => $cycle]
            );
        }

        if (count($coverImageItems) > 1) {
            $ids = [];
            foreach ($coverImageItems as $item) {
                if (isset($item['id']) && is_string($item['id']) && $item['id'] !== '') {
                    $ids[] = $item['id'];
                }
            }
            $context = ['count' => count($coverImageItems)];
            if ($ids !== []) {
                $context['ids'] = $ids;
            }
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'multiple-cover-image-manifest-items',
                'EPUB3 package manifest must not declare more than one cover-image item.',
                $context
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array<string, mixed>|null
     */
    private function manifestFallbackCoreMediaTypeDiagnostic(string $id, array $manifest, string $base_path, array $extraContext = [], bool $reportMissingFallback = false): ?array
    {
        $item = $manifest[$id] ?? null;
        if ($item === null) {
            return null;
        }

        $mediaType = trim($item['media-type']);
        if (
            (trim($item['fallback']) === '' && !$reportMissingFallback)
            || $mediaType === ''
            || !$this->validMediaType($mediaType)
            || $this->isEpubCoreMediaType($mediaType)
        ) {
            return null;
        }

        $context = [
            'id' => $id,
            'href' => $item['href'],
            'path' => $this->packageResourceZipPath($base_path, $item['href']),
            'mediaType' => $item['media-type'],
        ];
        $context += $extraContext;

        $seen = [$id => true];
        $chain = [$id];
        $current = $id;
        while (true) {
            $fallback = trim($manifest[$current]['fallback']);
            if ($fallback === '') {
                $terminalMediaType = trim($manifest[$current]['media-type']);
                if ($terminalMediaType === '' || !$this->validMediaType($terminalMediaType)) {
                    return null;
                }

                return $this->epubDiagnostic(
                    'error',
                    'missing-manifest-fallback-core-media-type',
                    'Manifest fallback chains for foreign resources must contain at least one Core Media Type resource.',
                    $context + $this->manifestFallbackTerminalContext($manifest, $base_path, $current, $chain, $current === $id ? 'missing-fallback' : 'chain-ended')
                );
            }

            if (!$this->validXmlId($fallback) || !isset($manifest[$fallback]) || isset($seen[$fallback])) {
                return null;
            }

            $chain[] = $fallback;
            $seen[$fallback] = true;
            $fallbackItem = $manifest[$fallback];
            $fallbackMediaType = trim($fallbackItem['media-type']);
            if (
                $fallbackMediaType !== ''
                && $this->validMediaType($fallbackMediaType)
                && $this->isEpubCoreMediaType($fallbackMediaType)
            ) {
                return null;
            }

            $current = $fallback;
        }
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param list<string> $chain
     * @return array<string, mixed>
     */
    private function manifestFallbackTerminalContext(array $manifest, string $base_path, string $terminalIdref, array $chain, string $reason): array
    {
        $context = [
            'reason' => $reason,
            'chain' => $chain,
        ];
        $terminal = $manifest[$terminalIdref] ?? null;
        if ($terminal !== null && $terminalIdref !== $chain[0]) {
            $context += [
                'fallbackIdref' => $terminalIdref,
                'fallbackHref' => $terminal['href'],
                'fallbackPath' => $this->packageResourceZipPath($base_path, $terminal['href']),
                'fallbackMediaType' => $terminal['media-type'],
            ];
        }

        return $context;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function malformedSpineXhtmlDiagnostics(string $xhtml, string $xhtmlPath, string $idref): array
    {
        try {
            $this->loadXml($xhtml, 'EPUB spine XHTML resource');
        } catch (\InvalidArgumentException) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'malformed-spine-xhtml',
                    'EPUB spine XHTML resource is not valid XML.',
                    [
                        'idref' => $idref,
                        'path' => $xhtmlPath,
                    ]
                ),
            ];
        }

        return [];
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, array{id: string, href: string, path: string, mediaType: string, properties: list<string>, fallback?: string, fallbackStyle?: string, mediaOverlay?: string}> $manifestResourcesByPath
     * @return list<array<string, mixed>>
     */
    private function xhtmlImageFallbackDiagnostics(string $xhtml, string $xhtmlPath, string $resourceBasePath, array $manifest, array $manifestResourcesByPath, string $packageBasePath): array
    {
        try {
            $dom = $this->loadXml($xhtml, 'EPUB XHTML image fallback diagnostics');
        } catch (\InvalidArgumentException) {
            return [];
        }

        $diagnostics = [];
        $seen = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $elementName = strtolower($element->localName);
            if ($elementName === 'picture') {
                $pictureHasCoreFallback = $this->xhtmlPictureHasCoreMediaTypeImageFallback($element, $resourceBasePath, $manifest, $manifestResourcesByPath);
                foreach ($this->xhtmlPictureSourceElements($element) as $source) {
                    if ($pictureHasCoreFallback) {
                        continue;
                    }

                    foreach ($this->xhtmlImageReferenceUrls($source) as $reference) {
                        $diagnostic = $this->xhtmlImageReferenceFallbackDiagnostic(
                            $reference,
                            'source',
                            $xhtmlPath,
                            $resourceBasePath,
                            $manifest,
                            $manifestResourcesByPath,
                            $packageBasePath
                        );
                        if ($diagnostic === null) {
                            continue;
                        }

                        $key = implode("\0", [
                            (string) ($diagnostic['sourcePath'] ?? ''),
                            (string) ($diagnostic['id'] ?? ''),
                            (string) ($diagnostic['element'] ?? ''),
                            (string) ($diagnostic['attribute'] ?? ''),
                            (string) ($diagnostic['sourceHref'] ?? ''),
                        ]);
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $diagnostics[] = $diagnostic;
                        }
                    }
                }

                $fallbackImage = $this->xhtmlPictureFallbackImageElement($element);
                if ($fallbackImage === null) {
                    continue;
                }

                foreach ($this->xhtmlImageReferenceUrls($fallbackImage) as $reference) {
                    $diagnostic = $this->xhtmlImageReferenceFallbackDiagnostic(
                        $reference,
                        'img',
                        $xhtmlPath,
                        $resourceBasePath,
                        $manifest,
                        $manifestResourcesByPath,
                        $packageBasePath
                    );
                    if ($diagnostic === null) {
                        continue;
                    }

                    $key = implode("\0", [
                        (string) ($diagnostic['sourcePath'] ?? ''),
                        (string) ($diagnostic['id'] ?? ''),
                        (string) ($diagnostic['element'] ?? ''),
                        (string) ($diagnostic['attribute'] ?? ''),
                        (string) ($diagnostic['sourceHref'] ?? ''),
                    ]);
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $diagnostics[] = $diagnostic;
                    }
                }
                continue;
            }

            if ($elementName !== 'img') {
                continue;
            }

            $parent = $element->parentNode;
            if ($parent instanceof \DOMElement && strtolower($parent->localName) === 'picture') {
                continue;
            }

            foreach ($this->xhtmlImageReferenceUrls($element) as $reference) {
                $diagnostic = $this->xhtmlImageReferenceFallbackDiagnostic(
                    $reference,
                    'img',
                    $xhtmlPath,
                    $resourceBasePath,
                    $manifest,
                    $manifestResourcesByPath,
                    $packageBasePath
                );
                if ($diagnostic === null) {
                    continue;
                }

                $key = implode("\0", [
                    (string) ($diagnostic['sourcePath'] ?? ''),
                    (string) ($diagnostic['id'] ?? ''),
                    (string) ($diagnostic['element'] ?? ''),
                    (string) ($diagnostic['attribute'] ?? ''),
                    (string) ($diagnostic['sourceHref'] ?? ''),
                ]);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $diagnostics[] = $diagnostic;
            }
        }

        return $diagnostics;
    }

    /**
     * @param array{attribute: string, url: string, descriptor?: string, type?: string} $reference
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, array{id: string, href: string, path: string, mediaType: string, properties: list<string>, fallback?: string, fallbackStyle?: string, mediaOverlay?: string}> $manifestResourcesByPath
     * @return array<string, mixed>|null
     */
    private function xhtmlImageReferenceFallbackDiagnostic(array $reference, string $elementName, string $xhtmlPath, string $resourceBasePath, array $manifest, array $manifestResourcesByPath, string $packageBasePath): ?array
    {
        $url = $reference['url'];
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return null;
        }

        $targetPath = $this->localResourceZipPath($resourceBasePath, $url);
        if ($targetPath === '') {
            return null;
        }

        $resource = $manifestResourcesByPath[$targetPath] ?? null;
        if (!is_array($resource)) {
            return null;
        }

        $id = (string) ($resource['id'] ?? '');
        if ($id === '' || !isset($manifest[$id]) || trim($manifest[$id]['fallback']) !== '') {
            return null;
        }

        $context = [
            'sourcePath' => $xhtmlPath,
            'element' => $elementName,
            'attribute' => $reference['attribute'],
            'sourceHref' => $url,
            'targetPath' => $targetPath,
        ];
        if (($reference['descriptor'] ?? '') !== '') {
            $context['descriptor'] = $reference['descriptor'];
        }
        if (($reference['type'] ?? '') !== '') {
            $context['type'] = $reference['type'];
        }

        return $this->manifestFallbackCoreMediaTypeDiagnostic(
            $id,
            $manifest,
            $packageBasePath,
            $context,
            true
        );
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, array{id: string, href: string, path: string, mediaType: string, properties: list<string>, fallback?: string, fallbackStyle?: string, mediaOverlay?: string}> $manifestResourcesByPath
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, bool> $diagnosticSeen
     * @param array<string, bool> $resourceScanSeen
     * @return list<array<string, mixed>>
     */
    private function xhtmlHyperlinkedSpineTargetDiagnostics(\ZipArchive $zip, string $xhtml, string $xhtmlPath, string $resourceBasePath, array $manifest, array $manifestResourcesByPath, array $spineItems, string $packageBasePath, array &$diagnosticSeen, array &$resourceScanSeen): array
    {
        $spinePaths = $this->spineResourcePaths($spineItems, $manifest, $packageBasePath);
        $diagnostics = [];
        $queuedPaths = [$xhtmlPath => true];
        $queue = [[
            'xhtml' => $xhtml,
            'path' => $xhtmlPath,
            'basePath' => $resourceBasePath,
        ]];

        for ($index = 0; $index < count($queue); $index++) {
            $entry = $queue[$index];
            $sourceXhtml = (string) ($entry['xhtml'] ?? '');
            $sourcePath = (string) ($entry['path'] ?? '');
            $sourceBasePath = (string) ($entry['basePath'] ?? '');
            if ($sourceXhtml === '' || $sourcePath === '' || isset($resourceScanSeen[$sourcePath])) {
                continue;
            }
            $resourceScanSeen[$sourcePath] = true;

            try {
                $dom = $this->loadXml($sourceXhtml, 'EPUB XHTML hyperlink spine target diagnostics');
            } catch (\InvalidArgumentException) {
                continue;
            }

            foreach ($dom->getElementsByTagName('*') as $element) {
                if (!$element instanceof \DOMElement) {
                    continue;
                }

                $elementName = strtolower($element->localName);
                if (!in_array($elementName, ['a', 'area'], true)) {
                    continue;
                }

                $href = html_entity_decode(trim($this->attributeByLocalName($element, 'href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
                    continue;
                }

                $targetPath = $this->localResourceZipPath($sourceBasePath, $href);
                if ($targetPath === '') {
                    continue;
                }

                $hrefFragmentReason = $this->xhtmlHyperlinkHrefFragmentDiagnosticReason($href);
                if ($hrefFragmentReason !== '') {
                    $key = implode("\0", [$sourcePath, $elementName, $href, $targetPath, $hrefFragmentReason]);
                    if (!isset($diagnosticSeen[$key])) {
                        $diagnosticSeen[$key] = true;
                        $context = [
                            'sourcePath' => $sourcePath,
                            'element' => $elementName,
                            'attribute' => 'href',
                            'sourceHref' => $href,
                            'targetPath' => $targetPath,
                            'reason' => $hrefFragmentReason,
                        ];
                        $fragment = $this->urlFragmentIdentifier($href);
                        if ($fragment !== '') {
                            $context['fragment'] = $fragment;
                        }
                        $label = trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
                        if ($label !== '') {
                            $context['text'] = $label;
                        }
                        $alt = html_entity_decode(trim($this->attributeByLocalName($element, 'alt')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        if ($alt !== '') {
                            $context['alt'] = $alt;
                        }

                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-spine-hyperlink-href-fragment',
                            'EPUB spine XHTML hyperlink href fragments must be non-empty fragment identifiers without whitespace.',
                            $context
                        );
                    }

                    continue;
                }

                $resource = $manifestResourcesByPath[$targetPath] ?? null;
                if (!is_array($resource)) {
                    continue;
                }

                $resourceId = (string) ($resource['id'] ?? '');
                $mediaType = (string) ($resource['mediaType'] ?? '');
                if ($resourceId === '' || $mediaType === '' || !isset($manifest[$resourceId])) {
                    continue;
                }

                if (!isset($spinePaths[$targetPath])) {
                    $key = implode("\0", [$sourcePath, $elementName, $href, $targetPath, $resourceId]);
                    if (!isset($diagnosticSeen[$key])) {
                        $diagnosticSeen[$key] = true;
                        $context = [
                            'sourcePath' => $sourcePath,
                            'element' => $elementName,
                            'attribute' => 'href',
                            'sourceHref' => $href,
                            'targetPath' => $targetPath,
                            'id' => $resourceId,
                            'href' => (string) ($resource['href'] ?? ''),
                            'mediaType' => $mediaType,
                        ];
                        $label = trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
                        if ($label !== '') {
                            $context['text'] = $label;
                        }
                        $alt = html_entity_decode(trim($this->attributeByLocalName($element, 'alt')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        if ($alt !== '') {
                            $context['alt'] = $alt;
                        }

                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'missing-spine-hyperlink-target',
                            'EPUB content documents hyperlinked from spine resources, recursively, must be listed in the OPF spine.',
                            $context
                        );
                    }
                }

                if (!$this->isEpubContentDocumentMediaType($mediaType) || isset($resourceScanSeen[$targetPath]) || isset($queuedPaths[$targetPath])) {
                    continue;
                }

                $targetXhtml = $zip->getFromName($targetPath);
                if (!is_string($targetXhtml) || $targetXhtml === '') {
                    continue;
                }

                $targetXhtml = $this->normalizeEpubSwitches($targetXhtml);
                $targetBasePath = $this->dirname($targetPath);
                $targetMetadata = $this->xhtmlMetadata($targetXhtml, $targetBasePath);
                $queue[] = [
                    'xhtml' => $targetXhtml,
                    'path' => $targetPath,
                    'basePath' => $this->xhtmlResourceBasePath($targetMetadata, $targetBasePath),
                ];
                $queuedPaths[$targetPath] = true;
            }
        }

        return $diagnostics;
    }

    private function xhtmlHyperlinkHrefFragmentDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '' || !str_contains($href, '#') || $this->isAbsoluteUrl($href) || str_starts_with($href, '#')) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($href);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @return array<string, array{idref: string, linear: bool, id?: string}>
     */
    private function spineResourcePaths(array $spineItems, array $manifest, string $basePath): array
    {
        $paths = [];
        foreach ($spineItems as $spineItem) {
            $idref = $spineItem['idref'];
            if (!isset($manifest[$idref])) {
                continue;
            }

            $path = $this->packageResourceZipPath($basePath, $manifest[$idref]['href']);
            if ($path === '') {
                continue;
            }

            $entry = [
                'idref' => $idref,
                'linear' => $spineItem['linear'],
            ];
            if (isset($spineItem['id']) && $spineItem['id'] !== '') {
                $entry['id'] = $spineItem['id'];
            }
            $paths[$path] = $entry;

            $readable = $this->readableSpineManifestItem($manifest, $idref, $basePath);
            if ($readable === null || $readable['path'] === $path) {
                continue;
            }

            $fallbackEntry = $entry;
            $fallbackEntry['fallbackIdref'] = $readable['idref'];
            $paths[$readable['path']] = $fallbackEntry;
        }

        return $paths;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, array{id: string, href: string, path: string, mediaType: string, properties: list<string>, fallback?: string, fallbackStyle?: string, mediaOverlay?: string}> $manifestResourcesByPath
     */
    private function xhtmlPictureHasCoreMediaTypeImageFallback(\DOMElement $picture, string $resourceBasePath, array $manifest, array $manifestResourcesByPath): bool
    {
        $image = $this->xhtmlPictureFallbackImageElement($picture);
        if ($image === null) {
            return false;
        }

        foreach ($this->xhtmlImageReferenceUrls($image) as $reference) {
            $url = $reference['url'];
            if (!$this->isPackageRelativeResourceUrl($url)) {
                continue;
            }

            $targetPath = $this->localResourceZipPath($resourceBasePath, $url);
            if ($targetPath === '') {
                continue;
            }

            $resource = $manifestResourcesByPath[$targetPath] ?? null;
            if (!is_array($resource)) {
                continue;
            }

            $id = (string) ($resource['id'] ?? '');
            if (!isset($manifest[$id])) {
                continue;
            }

            $mediaType = trim($manifest[$id]['media-type']);
            if ($mediaType !== '' && $this->validMediaType($mediaType) && $this->isEpubCoreMediaType($mediaType)) {
                return true;
            }
        }

        return false;
    }

    private function xhtmlPictureFallbackImageElement(\DOMElement $picture): ?\DOMElement
    {
        foreach ($picture->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'img') {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function xhtmlPictureSourceElements(\DOMElement $picture): array
    {
        $sources = [];
        foreach ($picture->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->localName) === 'source') {
                $sources[] = $child;
            }
        }

        return $sources;
    }

    /**
     * @return list<array{attribute: string, url: string, descriptor?: string, type?: string}>
     */
    private function xhtmlImageReferenceUrls(\DOMElement $element): array
    {
        $references = [];
        $type = html_entity_decode(trim($this->attributeByLocalName($element, 'type')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $src = html_entity_decode(trim($this->attributeByLocalName($element, 'src')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($src !== '') {
            $reference = [
                'attribute' => 'src',
                'url' => $src,
            ];
            if ($type !== '') {
                $reference['type'] = $type;
            }
            $references[] = $reference;
        }

        $srcset = html_entity_decode(trim($this->attributeByLocalName($element, 'srcset')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($srcset !== '') {
            foreach ($this->srcsetCandidates($srcset) as $candidate) {
                $reference = [
                    'attribute' => 'srcset',
                    'url' => $candidate['url'],
                ];
                if ($candidate['descriptor'] !== '') {
                    $reference['descriptor'] = $candidate['descriptor'];
                }
                if ($type !== '') {
                    $reference['type'] = $type;
                }
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manifestRestrictedResourceDiagnostics(string $rootfile, string $base_path, string $id, string $href, string $mediaType): array
    {
        if ($href === '') {
            return [];
        }
        if ($this->manifestHrefPathDiagnosticReason($href) !== '') {
            return [];
        }

        $path = $this->packageResourceZipPath($base_path, $href);
        if ($path === '') {
            return [];
        }

        $context = [
            'href' => $href,
            'path' => $path,
        ];
        if ($id !== '') {
            $context['id'] = $id;
        }
        if ($mediaType !== '') {
            $context['mediaType'] = $mediaType;
        }

        if ($path === $this->normalizeZipPath($rootfile)) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'manifest-package-document-resource',
                    'The selected OPF package document must not be listed in the manifest.',
                    $context
                ),
            ];
        }

        if ($path === 'mimetype' || str_starts_with($path, 'META-INF/')) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'manifest-reserved-ocf-resource',
                    'OCF reserved container files must not be listed in the OPF manifest.',
                    $context
                ),
            ];
        }

        return [];
    }

    private function manifestHrefPathDiagnosticReason(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (str_starts_with($href, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($href)) {
            return '';
        }
        if (str_starts_with($href, '/')) {
            return 'absolute-path';
        }
        if (str_contains($href, '\\')) {
            return 'backslash';
        }
        [$hrefPath] = $this->splitUrlPathSuffix($href);
        if (trim($hrefPath) === '') {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($hrefPath);
    }

    private function encodedDotSegmentPathDiagnosticReason(string $path): string
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || !str_contains($segment, '%')) {
                continue;
            }
            $decoded = $this->decodeUrlPathPercentEscapes($segment);
            if ($decoded === '.' || $decoded === '..') {
                return 'encoded-dot-segment';
            }
        }

        return '';
    }

    /**
     * @param list<string> $properties
     * @return list<array<string, mixed>>
     */
    private function manifestHrefUrlDiagnostics(string $href, string $id, string $mediaType, array $properties): array
    {
        $diagnostics = [];
        $lowerHref = strtolower(trim($href));
        $context = ['href' => $href];
        if ($id !== '') {
            $context['id'] = $id;
        }
        if ($mediaType !== '') {
            $context['mediaType'] = $mediaType;
        }

        if (str_starts_with($lowerHref, 'data:')) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-manifest-data-url',
                    'Manifest item href must not be a data URL.',
                    $context
                ),
            ];
        }

        if (str_starts_with($lowerHref, 'file:')) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-manifest-file-url',
                    'Manifest item href must not be a file URL.',
                    $context
                ),
            ];
        }

        if (!$this->isRemoteResourceUrl($href)) {
            return [];
        }

        $declared = array_flip(array_map('strtolower', $properties));
        if (!isset($declared['remote-resources'])) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-manifest-remote-resources-property',
                'Remote manifest resources must declare the remote-resources property.',
                $context
            );
        }

        if ($mediaType !== '' && $this->validMediaType($mediaType) && !$this->remoteManifestResourceMediaTypeAllowed($mediaType)) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-manifest-remote-resource-media-type',
                'Manifest item href points to a remote resource whose media type is not allowed for remote publication resources.',
                $context
            );
        }

        if (str_starts_with($lowerHref, 'http://')) {
            $diagnostics[] = $this->epubDiagnostic(
                'warning',
                'insecure-manifest-remote-resource',
                'Remote manifest resources should use https URLs.',
                $context
            );
        }

        return $diagnostics;
    }

    private function remoteManifestResourceMediaTypeAllowed(string $mediaType): bool
    {
        $baseType = explode(';', $this->normalizedMediaType($mediaType), 2)[0];

        return str_starts_with($baseType, 'audio/')
            || str_starts_with($baseType, 'video/')
            || str_starts_with($baseType, 'font/')
            || in_array($baseType, [
                'application/font-sfnt',
                'application/font-woff',
                'application/vnd.ms-opentype',
                'application/javascript',
                'application/ecmascript',
                'application/x-javascript',
                'text/javascript',
                'text/ecmascript',
            ], true);
    }

    /**
     * @param list<string> $properties
     * @return list<array<string, mixed>>
     */
    private function requiredManifestPropertyDiagnostics(
        \ZipArchive $zip,
        string $base_path,
        string $id,
        string $href,
        string $mediaType,
        array $properties
    ): array {
        $path = $this->packageResourceZipPath($base_path, $href);
        if ($path === '' || $zip->locateName($path) === false) {
            return [];
        }

        $stat = $zip->statName($path);
        if (is_array($stat) && isset($stat['size']) && (int) $stat['size'] > $this->resourceMaxBytes()) {
            return [];
        }

        $bytes = $zip->getFromName($path);
        if (!is_string($bytes) || strlen($bytes) > $this->resourceMaxBytes()) {
            return [];
        }

        $requiredProperties = $this->requiredManifestPropertiesForResource($bytes, $mediaType);
        if ($requiredProperties === []) {
            return [];
        }

        $declared = array_flip(array_map('strtolower', $properties));
        $diagnostics = [];
        foreach ($requiredProperties as $property) {
            if (isset($declared[$property])) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-manifest-required-property',
                'Manifest item is missing a required EPUB resource property.',
                [
                    'id' => $id,
                    'href' => $href,
                    'path' => $path,
                    'mediaType' => $mediaType,
                    'property' => $property,
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<string>
     */
    private function requiredManifestPropertiesForResource(string $bytes, string $mediaType): array
    {
        $required = [];
        $isXhtml = $this->mediaTypeMatches($mediaType, 'application/xhtml+xml');
        $isSvg = $this->mediaTypeMatches($mediaType, 'image/svg+xml');
        $isCss = $this->mediaTypeMatches($mediaType, 'text/css');
        $isSmil = $this->mediaTypeMatches($mediaType, 'application/smil+xml');

        if (($isXhtml || $isSvg) && $this->resourceContainsMathMl($bytes)) {
            $required[] = 'mathml';
        }
        if ($isXhtml && $this->resourceContainsInlineSvg($bytes)) {
            $required[] = 'svg';
        }
        if (($isXhtml || $isSvg) && $this->resourceContainsScriptedContent($bytes)) {
            $required[] = 'scripted';
        }
        if ($isXhtml && $this->resourceContainsEpubSwitch($bytes)) {
            $required[] = 'switch';
        }
        if (($isXhtml || $isSvg || $isCss || $isSmil) && $this->resourceContainsRemoteReference($bytes)) {
            $required[] = 'remote-resources';
        }

        return array_values(array_unique($required));
    }

    private function resourceContainsMathMl(string $bytes): bool
    {
        return preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?math(?:\s|>|\/)/i', $bytes) === 1;
    }

    private function resourceContainsInlineSvg(string $bytes): bool
    {
        return preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?svg(?:\s|>|\/)/i', $bytes) === 1;
    }

    private function resourceContainsScriptedContent(string $bytes): bool
    {
        return preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?script(?:\s|>|\/)/i', $bytes) === 1
            || preg_match('/<\s*(?:[A-Za-z_][\w.-]*:)?form(?:\s|>|\/)/i', $bytes) === 1
            || preg_match('/\son[A-Za-z][A-Za-z0-9_.:-]*\s*=/i', $bytes) === 1
            || preg_match('/(?<![\w-])(?:href|src|action|formaction)\s*=\s*(["\'])\s*javascript:/i', $bytes) === 1;
    }

    private function resourceContainsEpubSwitch(string $bytes): bool
    {
        if (stripos($bytes, 'switch') === false) {
            return false;
        }

        try {
            $dom = $this->loadXml($bytes, 'EPUB manifest resource switch property scan');
        } catch (\InvalidArgumentException) {
            return preg_match('/<\s*[A-Za-z_][\w.-]*:switch(?:\s|>|\/)/i', $bytes) === 1;
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $this->isEpubSwitchElement($element, 'switch')) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsRemoteReference(string $bytes): bool
    {
        if ($this->resourceContainsRemoteBaseRelativeResourceReference($bytes)) {
            return true;
        }

        if (preg_match_all('/(?<![\w-])(?:src|poster|data|action|formaction|background|textref)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if (preg_match_all('/(?<![\w-])(?:srcset|imagesrcset)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->srcsetContainsRemoteResource(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if (preg_match_all('/(?<![\w-])style\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->cssContainsRemoteReference(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if ($this->resourceContainsRemoteSvgHrefReference($bytes)) {
            return true;
        }

        if ($this->resourceContainsRemoteHtmlLinkHrefReference($bytes)) {
            return true;
        }

        return $this->cssContainsRemoteReference($bytes);
    }

    private function resourceContainsRemoteBaseRelativeResourceReference(string $bytes): bool
    {
        if (!$this->resourceContainsRemoteHtmlBaseHrefReference($bytes)) {
            return false;
        }

        if (preg_match_all('/(?<![\w-])(?:src|poster|data|action|formaction|background|textref)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->isPackageRelativeResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        if (preg_match_all('/(?<![\w-])(?:srcset|imagesrcset)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                foreach ($this->srcsetCandidates(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $candidate) {
                    if ($this->isPackageRelativeResourceUrl($candidate['url'])) {
                        return true;
                    }
                }
            }
        }

        if (preg_match_all('/(?<![\w-])style\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->cssContainsPackageRelativeReference(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return $this->resourceContainsPackageRelativeSvgHrefReference($bytes)
            || $this->resourceContainsPackageRelativeHtmlLinkHrefReference($bytes)
            || $this->cssContainsPackageRelativeReference($bytes);
    }

    private function resourceContainsRemoteHtmlBaseHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?base\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsRemoteHtmlLinkHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?link\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsPackageRelativeHtmlLinkHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?link\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isPackageRelativeResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsRemoteSvgHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?(?:altGlyph|clipPath|color-profile|cursor|feImage|filter|font-face-uri|glyphRef|image|linearGradient|marker|mask|mpath|pattern|radialGradient|script|textPath|tref|use)\b[^>]*\b(?:href|xlink:href)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function resourceContainsPackageRelativeSvgHrefReference(string $bytes): bool
    {
        if (preg_match_all('/<\s*(?:[A-Za-z_][\w.-]*:)?(?:altGlyph|clipPath|color-profile|cursor|feImage|filter|font-face-uri|glyphRef|image|linearGradient|marker|mask|mpath|pattern|radialGradient|script|textPath|tref|use)\b[^>]*\b(?:href|xlink:href)\s*=\s*(["\'])(.*?)\1/is', $bytes, $matches, PREG_SET_ORDER) === false) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->isPackageRelativeResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function cssContainsRemoteReference(string $css): bool
    {
        if (preg_match_all('/url\(\s*(?:(["\'])(.*?)\1|([^)]*?))\s*\)/is', $css, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $url = $this->cssUrlMatchValue($match);
                if ($this->isRemoteResourceUrl($url)) {
                    return true;
                }
            }
        }

        if (preg_match_all('/@import\s+(["\'])([^"\']+)\1/is', $css, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->isRemoteResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function cssContainsPackageRelativeReference(string $css): bool
    {
        if (preg_match_all('/url\(\s*(?:(["\'])(.*?)\1|([^)]*?))\s*\)/is', $css, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $url = $this->cssUrlMatchValue($match);
                if ($this->isPackageRelativeResourceUrl($url)) {
                    return true;
                }
            }
        }

        if (preg_match_all('/@import\s+(["\'])([^"\']+)\1/is', $css, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($this->isPackageRelativeResourceUrl(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $match
     */
    private function cssUrlMatchValue(array $match): string
    {
        $url = ($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '');

        return html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, true> $declaredPrefixes
     * @return list<array<string, mixed>>
     */
    private function spineDiagnostics(\ZipArchive $zip, \DOMElement $spineElement, array $manifest, array $declaredPrefixes, string $base_path): array
    {
        $diagnostics = [];
        $spineId = trim($spineElement->getAttribute('id'));
        if ($spineId !== '' && !$this->validXmlId($spineId)) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-spine-id',
                'OPF spine id must be an XML NCName.',
                ['id' => $spineId]
            );
        }

        $toc = trim($spineElement->getAttribute('toc'));
        if ($toc !== '') {
            if (!$this->validXmlId($toc)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-spine-toc-idref',
                    'Spine toc attribute must be an XML IDREF.',
                    ['idref' => $toc]
                );
            }
            if (!isset($manifest[$toc])) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-spine-toc',
                    'Spine toc attribute references a missing manifest item.',
                    ['idref' => $toc]
                );
            } elseif (!$this->mediaTypeMatches($manifest[$toc]['media-type'], 'application/x-dtbncx+xml')) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-spine-toc-media-type',
                    'Spine toc attribute must reference an NCX manifest item.',
                    [
                        'idref' => $toc,
                        'href' => $manifest[$toc]['href'],
                        'mediaType' => $manifest[$toc]['media-type'],
                    ]
                );
            }
        }

        $pageProgressionDirection = trim($spineElement->getAttribute('page-progression-direction'));
        if ($pageProgressionDirection !== '' && !in_array(strtolower($pageProgressionDirection), ['ltr', 'rtl', 'default'], true)) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-spine-page-progression-direction',
                'Spine page-progression-direction must be ltr, rtl, or default.',
                ['value' => $pageProgressionDirection]
            );
        }

        $itemrefIds = [];
        $itemrefIdrefs = [];
        $linearItemrefCount = 0;
        $itemrefElements = $this->directOpfChildElements($spineElement, 'itemref');
        foreach ($spineElement->childNodes as $child) {
            if (!$child instanceof \DOMElement || $this->isOpfPackageElement($child, 'itemref')) {
                continue;
            }

            if ($child->localName === 'itemref') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-spine-itemref-namespace',
                    'OPF spine itemref elements must use the OPF namespace.',
                    [
                        'element' => $this->qualifiedName($child),
                        'namespace' => $child->namespaceURI ?? '',
                    ]
                );
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-spine-child-element',
                'OPF spine must contain only itemref child elements.',
                ['element' => $this->qualifiedName($child)]
            );
        }
        if ($itemrefElements === []) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'empty-spine',
                'OPF spine must contain at least one itemref element.'
            );
        }

        foreach ($itemrefElements as $itemrefElement) {
            $id = trim($itemrefElement->getAttribute('id'));
            $idref = trim($itemrefElement->getAttribute('idref'));
            if ($id !== '') {
                if (!$this->validXmlId($id)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-spine-itemref-id',
                        'Spine itemref id must be an XML ID.',
                        ['id' => $id] + ($idref === '' ? [] : ['idref' => $idref])
                    );
                }
                if (isset($itemrefIds[$id])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-spine-itemref-id',
                        'Spine itemref id is duplicated.',
                        ['id' => $id]
                    );
                } else {
                    $itemrefIds[$id] = true;
                }
            }

            $linear = trim($itemrefElement->getAttribute('linear'));
            if ($linear !== '' && !in_array(strtolower($linear), ['yes', 'no'], true)) {
                $context = ['value' => $linear];
                if ($id !== '') {
                    $context['id'] = $id;
                }
                if ($idref !== '') {
                    $context['idref'] = $idref;
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-spine-itemref-linear',
                    'Spine itemref linear attribute must be yes or no.',
                    $context
                );
            }
            foreach ($this->duplicateAttributeTokens($itemrefElement, 'properties') as $property) {
                $context = ['property' => $property];
                if ($id !== '') {
                    $context['id'] = $id;
                }
                if ($idref !== '') {
                    $context['idref'] = $idref;
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-spine-itemref-property',
                    'Spine itemref properties must not contain duplicate property tokens.',
                    $context
                );
            }
            foreach ($this->attributeTokenList($itemrefElement, 'properties') as $property) {
                $context = ['property' => $property];
                if ($id !== '') {
                    $context['id'] = $id;
                }
                if ($idref !== '') {
                    $context['idref'] = $idref;
                }
                if (!$this->validPropertyValue($property)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-spine-itemref-property',
                        'Spine itemref properties values must be valid property data type values.',
                        $context
                    );
                } elseif (!$this->propertyValuePrefixIsDeclared($property, $declaredPrefixes)) {
                    [$propertyPrefix] = explode(':', $property, 2);
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'undeclared-spine-itemref-property-prefix',
                        'Spine itemref properties prefix must be reserved or declared in package prefix.',
                        $context + ['prefix' => $propertyPrefix]
                    );
                }
            }
            if ($idref === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-spine-itemref-idref',
                    'Spine itemref is missing an idref.',
                    $id === '' ? [] : ['id' => $id]
                );
                continue;
            }
            if ($linear === '' || strtolower($linear) === 'yes') {
                ++$linearItemrefCount;
            }
            if (!$this->validXmlId($idref)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-spine-itemref-idref',
                    'Spine itemref idref must be an XML IDREF.',
                    ['idref' => $idref] + ($id === '' ? [] : ['id' => $id])
                );
            }
            if (!isset($manifest[$idref])) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-spine-idref',
                    'Spine itemref references a missing manifest item.',
                    ['idref' => $idref] + ($id === '' ? [] : ['id' => $id])
                );
                continue;
            }

            if (isset($itemrefIdrefs[$idref])) {
                $context = ['idref' => $idref];
                if ($id !== '') {
                    $context['id'] = $id;
                }
                $first = $itemrefIdrefs[$idref];
                if (($first['id'] ?? '') !== '') {
                    $context['firstId'] = $first['id'];
                }
                if (($first['href'] ?? '') !== '') {
                    $context['href'] = $first['href'];
                }
                if (($first['mediaType'] ?? '') !== '') {
                    $context['mediaType'] = $first['mediaType'];
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-spine-itemref-idref',
                    'Spine itemrefs must not reference the same manifest item more than once.',
                    $context
                );
            } else {
                $manifestItem = $manifest[$idref];
                $itemrefIdrefs[$idref] = [
                    'id' => $id,
                    'href' => $manifestItem['href'],
                    'mediaType' => $manifestItem['media-type'],
                ];
            }

            $resourceDiagnostic = $this->spineResourceAvailabilityDiagnostic($zip, $idref, $id, $manifest, $base_path);
            if ($resourceDiagnostic !== null) {
                $diagnostics[] = $resourceDiagnostic;
            }

            $fallbackDiagnostic = $this->spineFallbackContentDocumentDiagnostic($idref, $id, $manifest, $base_path);
            if ($fallbackDiagnostic !== null) {
                $diagnostics[] = $fallbackDiagnostic;
            }
        }

        if ($itemrefElements !== [] && $linearItemrefCount === 0) {
            $context = [
                'itemrefCount' => count($itemrefElements),
                'linearItemrefCount' => 0,
            ];
            if ($spineId !== '') {
                $context['id'] = $spineId;
            }
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-linear-spine-itemref',
                'OPF spine must contain at least one linear itemref.',
                $context
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array<string, mixed>|null
     */
    private function spineResourceAvailabilityDiagnostic(\ZipArchive $zip, string $idref, string $itemrefId, array $manifest, string $base_path): ?array
    {
        $item = $manifest[$idref] ?? null;
        if ($item === null || !$this->isEpubContentDocumentMediaType($item['media-type'])) {
            return null;
        }

        $href = trim($item['href']);
        if ($href === '') {
            return null;
        }

        $context = [
            'idref' => $idref,
            'href' => $item['href'],
            'mediaType' => $item['media-type'],
        ];
        if ($itemrefId !== '') {
            $context['id'] = $itemrefId;
        }

        $hrefPathReason = $this->manifestHrefPathDiagnosticReason($href);
        if ($hrefPathReason !== '') {
            return $this->epubDiagnostic(
                'error',
                'invalid-spine-resource-href',
                'Spine itemref content document href must resolve to a package-relative EPUB archive resource.',
                $context + ['reason' => $hrefPathReason]
            );
        }

        if (!$this->isPackageRelativeResourceUrl($href)) {
            return $this->epubDiagnostic(
                'error',
                'invalid-spine-resource-href',
                'Spine itemref content document href must resolve to a package-relative EPUB archive resource.',
                $context + ['reason' => $this->nonPackageSpineHrefReason($href)]
            );
        }

        $path = $this->packageResourceZipPath($base_path, $href);
        if ($path === '') {
            return null;
        }

        if ($zip->locateName($path) === false) {
            return $this->epubDiagnostic(
                'error',
                'missing-spine-resource',
                'Spine itemref content document href does not resolve to a package resource in the EPUB archive.',
                $context + ['path' => $path]
            );
        }

        return null;
    }

    private function nonPackageSpineHrefReason(string $href): string
    {
        $href = strtolower(trim($href));
        if (str_starts_with($href, 'data:')) {
            return 'data-url';
        }
        if (str_starts_with($href, 'file:')) {
            return 'file-url';
        }
        if ($this->isRemoteResourceUrl($href)) {
            return 'remote-url';
        }

        return 'absolute-url';
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array<string, mixed>|null
     */
    private function spineFallbackContentDocumentDiagnostic(string $idref, string $itemrefId, array $manifest, string $base_path): ?array
    {
        $item = $manifest[$idref] ?? null;
        if ($item === null || $this->isEpubContentDocumentMediaType($item['media-type'])) {
            return null;
        }

        $context = [
            'idref' => $idref,
            'href' => $item['href'],
            'path' => $this->packageResourceZipPath($base_path, $item['href']),
            'mediaType' => $item['media-type'],
        ];
        if ($itemrefId !== '') {
            $context['id'] = $itemrefId;
        }

        $seen = [$idref => true];
        $chain = [$idref];
        $current = $idref;
        while (true) {
            $fallback = trim($manifest[$current]['fallback']);
            if ($fallback === '') {
                return $this->epubDiagnostic(
                    'error',
                    'missing-spine-fallback-content-document',
                    'Spine foreign content documents must provide a manifest fallback chain to an EPUB content document.',
                    $context + $this->spineFallbackTerminalContext($manifest, $base_path, $current, $chain, $current === $idref ? 'missing-fallback' : 'chain-ended')
                );
            }

            if (!isset($manifest[$fallback])) {
                return $this->epubDiagnostic(
                    'error',
                    'missing-spine-fallback-content-document',
                    'Spine foreign content documents must provide a manifest fallback chain to an EPUB content document.',
                    $context + ['reason' => 'missing-target', 'target' => $fallback, 'chain' => $chain]
                );
            }

            if (isset($seen[$fallback])) {
                $chain[] = $fallback;
                return $this->epubDiagnostic(
                    'error',
                    'missing-spine-fallback-content-document',
                    'Spine foreign content documents must provide a manifest fallback chain to an EPUB content document.',
                    $context + ['reason' => 'cycle', 'target' => $fallback, 'chain' => $chain]
                );
            }

            $chain[] = $fallback;
            $seen[$fallback] = true;
            $fallbackItem = $manifest[$fallback];
            if ($this->isEpubContentDocumentMediaType($fallbackItem['media-type'])) {
                return null;
            }

            $current = $fallback;
        }
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param list<string> $chain
     * @return array<string, mixed>
     */
    private function spineFallbackTerminalContext(array $manifest, string $base_path, string $terminalIdref, array $chain, string $reason): array
    {
        $context = [
            'reason' => $reason,
            'chain' => $chain,
        ];
        $terminal = $manifest[$terminalIdref] ?? null;
        if ($terminal !== null && $terminalIdref !== $chain[0]) {
            $context += [
                'fallbackIdref' => $terminalIdref,
                'fallbackHref' => $terminal['href'],
                'fallbackPath' => $this->packageResourceZipPath($base_path, $terminal['href']),
                'fallbackMediaType' => $terminal['media-type'],
            ];
        }

        return $context;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function bindingDiagnostics(\DOMElement $package, array $manifest): array
    {
        $diagnostics = [];
        foreach ($package->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'bindings' || $this->isOpfPackageElement($child, 'bindings')) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-bindings-namespace',
                'OPF bindings elements must use the OPF namespace.',
                [
                    'element' => $this->qualifiedName($child),
                    'namespace' => $child->namespaceURI ?? '',
                ]
            );
        }

        $bindingsElement = $this->directOpfChildElement($package, 'bindings');
        if ($bindingsElement !== null) {
            $diagnostics[] = $this->epubDiagnostic(
                'warning',
                'deprecated-bindings',
                'OPF bindings are deprecated in EPUB 3.3.'
            );
            $mediaTypes = [];
            foreach ($bindingsElement->childNodes as $child) {
                if (!$child instanceof \DOMElement || $this->isOpfPackageElement($child, 'mediaType')) {
                    continue;
                }

                if ($child->localName === 'mediaType') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-binding-media-type-namespace',
                        'OPF binding mediaType elements must use the OPF namespace.',
                        $this->bindingMediaTypeDiagnosticContext($child) + ['namespace' => $child->namespaceURI ?? '']
                    );
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-bindings-child-element',
                    'OPF bindings must contain only mediaType child elements.',
                    ['element' => $this->qualifiedName($child)]
                );
            }

            foreach ($this->directOpfChildElements($bindingsElement, 'mediaType') as $mediaTypeElement) {
                $type = trim($mediaTypeElement->getAttribute('media-type'));
                $handler = trim($mediaTypeElement->getAttribute('handler'));
                if ($type === '') {
                    $diagnostics[] = $this->epubDiagnostic('error', 'missing-binding-media-type', 'Binding mediaType entry is missing media-type.');
                } else {
                    $context = ['mediaType' => $type] + ($handler === '' ? [] : ['handler' => $handler]);
                    if (!$this->validMediaType($type)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-binding-media-type',
                            'Binding media-type must be a valid media type.',
                            $context
                        );
                    }
                    if ($this->isEpubCoreMediaType($type)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'binding-core-media-type',
                            'Binding media-type must not be an EPUB core media type.',
                            $context
                        );
                    }

                    $mediaTypeKey = $this->normalizedMediaType($type);
                    if (isset($mediaTypes[$mediaTypeKey])) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'duplicate-binding-media-type',
                            'Binding media-type values must be unique within a bindings element.',
                            $context + ['firstHandler' => $mediaTypes[$mediaTypeKey]]
                        );
                    } else {
                        $mediaTypes[$mediaTypeKey] = $handler;
                    }
                }
                if ($handler === '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-binding-handler',
                        'Binding mediaType entry is missing handler.',
                        $type === '' ? [] : ['mediaType' => $type]
                    );
                    continue;
                }
                if (!$this->validXmlId($handler)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-binding-handler-idref',
                        'Binding handler must be an XML IDREF.',
                        ['handler' => $handler] + ($type === '' ? [] : ['mediaType' => $type])
                    );
                }
                if (!isset($manifest[$handler])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-binding-handler-target',
                        'Binding handler references a missing manifest item.',
                        ['mediaType' => $type, 'handler' => $handler]
                    );
                    continue;
                }

                $handlerItem = $manifest[$handler];
                if (!$this->mediaTypeMatches($handlerItem['media-type'], 'application/xhtml+xml')) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-binding-handler-media-type',
                        'Binding handler must reference an XHTML content document manifest item.',
                        [
                            'mediaType' => $type,
                            'handler' => $handler,
                            'handlerMediaType' => $handlerItem['media-type'],
                        ]
                    );
                }
                if (!in_array('scripted', $handlerItem['properties'], true)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-binding-handler-scripted-property',
                        'Binding handler manifest item must declare the scripted property.',
                        [
                            'mediaType' => $type,
                            'handler' => $handler,
                        ]
                    );
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, string>
     */
    private function bindingMediaTypeDiagnosticContext(\DOMElement $mediaTypeElement): array
    {
        $context = ['element' => $this->qualifiedName($mediaTypeElement)];
        $type = trim($mediaTypeElement->getAttribute('media-type'));
        if ($type !== '') {
            $context['mediaType'] = $type;
        }
        $handler = trim($mediaTypeElement->getAttribute('handler'));
        if ($handler !== '') {
            $context['handler'] = $handler;
        }

        return $context;
    }

    /**
     * @param array<string, true> $declaredPrefixes
     * @return list<array<string, mixed>>
     */
    private function collectionDiagnostics(\DOMElement $package, array $declaredPrefixes): array
    {
        $diagnostics = [];
        $seenCollectionIds = [];
        $seenCollectionLinkIds = [];
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'collection') {
                continue;
            }
            if (!$this->isOpfPackageElement($element, 'collection')) {
                $context = $this->collectionDiagnosticContext($element);
                $role = trim($element->getAttribute('role'));
                if ($role !== '') {
                    $context['role'] = $role;
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-package-collection-namespace',
                    'OPF package collection elements must use the OPF namespace.',
                    $context + ['namespace' => $element->namespaceURI ?? '']
                );
                continue;
            }

            $context = $this->collectionDiagnosticContext($element);
            $collectionId = (string) ($context['id'] ?? '');
            $direction = trim($element->getAttribute('dir'));
            if ($direction !== '' && !in_array(strtolower($direction), ['ltr', 'rtl', 'auto'], true)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-collection-dir',
                    'OPF collection dir attribute must be ltr, rtl, or auto.',
                    $context + ['dir' => $direction]
                );
            }
            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language !== '' && !$this->validXmlLanguageTag($language)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-collection-xml-language',
                    'OPF collection xml:lang attribute must be a valid language tag.',
                    $context + ['lang' => $language]
                );
            }
            if ($collectionId !== '') {
                if (!$this->validXmlId($collectionId)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-collection-id',
                        'OPF collection id attributes must be XML NCNames.',
                        $context
                    );
                } elseif (isset($seenCollectionIds[$collectionId])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-collection-id',
                        'OPF collection id attributes must be unique within the package.',
                        $context + ['previousElement' => $seenCollectionIds[$collectionId]['element']]
                    );
                } else {
                    $seenCollectionIds[$collectionId] = $context;
                }
            }

            $role = trim($element->getAttribute('role'));
            if ($role === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-collection-role',
                    'OPF collection is missing the required role attribute.',
                    $context
                );
            } else {
                foreach ($this->duplicateAttributeTokens($element, 'role') as $roleToken) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-collection-role',
                        'OPF collection role values must not repeat token values.',
                        $context + ['role' => $roleToken, 'roles' => $role]
                    );
                }
                foreach ($this->tokenList($role) as $roleToken) {
                    if ($roleToken === '') {
                        continue;
                    }
                    if (!$this->validCollectionRoleToken($roleToken)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-collection-role',
                            'OPF collection role must be an NMTOKEN or absolute IRI.',
                            $context + ['role' => $roleToken]
                        );
                        continue;
                    }
                    if ($this->collectionRoleUsesReservedIdpfHost($roleToken)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-collection-role-idpf-host',
                            'OPF collection custom role IRIs must not use the idpf.org host.',
                            $context + ['role' => $roleToken]
                        );
                    }
                }
            }

            if (!$this->collectionHasDirectMember($element)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-collection-member',
                    'OPF collection must contain at least one link or nested collection member.',
                    $context
                );
            }

            $diagnostics = array_merge($diagnostics, $this->collectionContentModelDiagnostics($element, $context));

            foreach ($element->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                if (
                    $this->isOpfPackageElement($child, 'metadata')
                    || $this->isOpfPackageElement($child, 'link')
                    || $this->isOpfPackageElement($child, 'collection')
                ) {
                    continue;
                }

                if ($child->localName === 'collection') {
                    continue;
                }

                if (!in_array($child->localName, ['metadata', 'link'], true)) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-collection-child-element',
                        'OPF collection must contain only metadata, link, or nested collection child elements.',
                        $this->collectionChildDiagnosticContext($child, $context)
                    );
                    continue;
                }

                $code = $child->localName === 'metadata'
                    ? 'invalid-collection-metadata-namespace'
                    : 'invalid-collection-link-namespace';
                $message = $child->localName === 'metadata'
                    ? 'OPF collection metadata elements must use the OPF namespace.'
                    : 'OPF collection link elements must use the OPF namespace.';
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    $code,
                    $message,
                    $this->collectionChildDiagnosticContext($child, $context) + ['namespace' => $child->namespaceURI ?? '']
                );
            }

            foreach ($this->directOpfChildElements($element, 'metadata') as $metadataElement) {
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->collectionMetadataIdentityDiagnostics($metadataElement, $context)
                );

                foreach ($metadataElement->childNodes as $metadataChild) {
                    if (!$metadataChild instanceof \DOMElement) {
                        continue;
                    }
                    if (!$this->isOpfPackageElement($metadataChild, 'meta') && !$this->isOpfPackageElement($metadataChild, 'link')) {
                        if (in_array($metadataChild->localName, ['meta', 'link'], true)) {
                            $code = $metadataChild->localName === 'meta'
                                ? 'invalid-collection-meta-namespace'
                                : 'invalid-collection-link-namespace';
                            $message = $metadataChild->localName === 'meta'
                                ? 'OPF collection metadata meta elements must use the OPF namespace.'
                                : 'OPF collection link elements must use the OPF namespace.';
                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                $code,
                                $message,
                                $this->collectionChildDiagnosticContext($metadataChild, $context) + ['namespace' => $metadataChild->namespaceURI ?? '']
                            );
                            continue;
                        }

                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-collection-metadata-child-element',
                            'OPF collection metadata must contain only meta or link child elements.',
                            $this->collectionChildDiagnosticContext($metadataChild, $context)
                        );
                        continue;
                    }
                    $metadataContext = [
                        'element' => $this->qualifiedName($metadataChild),
                    ];
                    if (isset($context['id'])) {
                        $metadataContext['collectionId'] = $context['id'];
                    }
                    if (isset($context['parentId'])) {
                        $metadataContext['parentCollectionId'] = $context['parentId'];
                    }
                    $metadataId = trim($metadataChild->getAttribute('id'));
                    if ($metadataId !== '') {
                        $metadataContext['id'] = $metadataId;
                    }
                    $property = trim($metadataChild->getAttribute('property'));
                    if ($property !== '') {
                        $metadataContext['property'] = $property;
                    }
                    $metadataDirection = trim($metadataChild->getAttribute('dir'));
                    if ($metadataDirection !== '' && !in_array(strtolower($metadataDirection), ['ltr', 'rtl', 'auto'], true)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-collection-metadata-dir',
                            'OPF collection metadata dir attribute must be ltr, rtl, or auto.',
                            $this->collectionMetadataLanguageDiagnosticContext($metadataChild, $metadataContext) + ['dir' => $metadataDirection]
                        );
                    }
                    $metadataLanguage = trim($metadataChild->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
                    if ($metadataLanguage !== '' && !$this->validXmlLanguageTag($metadataLanguage)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-collection-metadata-xml-language',
                            'OPF collection metadata xml:lang attribute must be a valid language tag.',
                            $this->collectionMetadataLanguageDiagnosticContext($metadataChild, $metadataContext) + ['lang' => $metadataLanguage]
                        );
                    }
                    if ($property !== '') {
                        if (!$this->validPropertyValue($property)) {
                            $value = $this->metadataElementText($metadataChild);
                            if ($value !== '') {
                                $metadataContext['value'] = $value;
                            }

                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                'invalid-collection-metadata-property',
                                'OPF collection metadata property attributes must be valid property data type values.',
                                $metadataContext
                            );
                        } elseif (!$this->propertyValuePrefixIsDeclared($property, $declaredPrefixes)) {
                            [$propertyPrefix] = explode(':', $property, 2);
                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                'undeclared-collection-metadata-property-prefix',
                                'OPF collection metadata property prefix must be reserved or declared in package prefix.',
                                $metadataContext + ['prefix' => $propertyPrefix]
                            );
                        }
                    }
                    $scheme = trim($metadataChild->getAttribute('scheme'));
                    if ($scheme !== '') {
                        $metadataContext['scheme'] = $scheme;
                        if (!$this->validPropertyValue($scheme)) {
                            $value = $this->metadataElementText($metadataChild);
                            if ($value !== '') {
                                $metadataContext['value'] = $value;
                            }

                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                'invalid-collection-metadata-scheme',
                                'OPF collection metadata scheme attributes must be valid property data type values.',
                                $metadataContext
                            );
                        } elseif (!$this->propertyValuePrefixIsDeclared($scheme, $declaredPrefixes)) {
                            [$schemePrefix] = explode(':', $scheme, 2);
                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                'undeclared-collection-metadata-scheme-prefix',
                                'OPF collection metadata scheme prefix must be reserved or declared in package prefix.',
                                $metadataContext + ['prefix' => $schemePrefix]
                            );
                        }
                    }

                    if (
                        $metadataChild->localName === 'meta'
                        && $property !== ''
                        && $this->metadataElementText($metadataChild) === ''
                    ) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'empty-collection-metadata-value',
                            'OPF collection metadata values must not be empty.',
                            $metadataContext
                        );
                    }

                    if ($metadataChild->localName === 'meta') {
                        $name = trim($metadataChild->getAttribute('name'));
                        if ($name !== '') {
                            $opf2Context = $metadataContext + ['name' => $name];
                            $refines = trim($metadataChild->getAttribute('refines'));
                            if ($refines !== '') {
                                $opf2Context['refines'] = $refines;
                            }
                            $value = $this->metadataElementText($metadataChild);
                            if ($value !== '') {
                                $opf2Context['value'] = $value;
                            }
                            $content = trim($metadataChild->getAttribute('content'));
                            if ($content !== '') {
                                $opf2Context['content'] = $content;
                            }
                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                'invalid-collection-opf2-meta',
                                'OPF collection metadata must not include OPF2-style meta elements.',
                                $opf2Context
                            );
                        } elseif ($property === '') {
                            $value = $this->metadataElementText($metadataChild);
                            if ($value !== '') {
                                $metadataContext['value'] = $value;
                            }

                            $diagnostics[] = $this->epubDiagnostic(
                                'error',
                                'missing-collection-metadata-property',
                                'OPF collection metadata meta elements must include a property attribute.',
                                $metadataContext
                            );
                        }
                    }

                    if (!$metadataChild->hasAttribute('refines')) {
                        continue;
                    }

                    $refines = trim($metadataChild->getAttribute('refines'));
                    if (!$this->validMetadataRefinesValue($refines)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-collection-metadata-refines',
                            'OPF collection metadata refines attributes must be fragment references to XML IDs.',
                            $metadataContext + $this->collectionRefinesDiagnosticContext($refines)
                        );
                        continue;
                    }

                    $target = substr($refines, 1);
                    if ($this->collectionHasElementId($element, $target)) {
                        continue;
                    }

                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'collection-metadata-refines-outside',
                        'OPF collection metadata refines attribute must not reference elements outside the containing collection.',
                        $metadataContext + $this->collectionRefinesDiagnosticContext($refines) + ['target' => $target]
                    );
                }
            }

            foreach ($this->directOpfChildElements($element, 'link') as $linkElement) {
                $linkContext = $this->collectionLinkDiagnosticContext($linkElement, $context);
                $linkId = (string) ($linkContext['id'] ?? '');
                if ($linkId !== '') {
                    if (!$this->validXmlId($linkId)) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'invalid-collection-link-id',
                            'OPF collection link id attributes must be XML NCNames.',
                            $linkContext
                        );
                    } elseif (isset($seenCollectionLinkIds[$linkId])) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'duplicate-collection-link-id',
                            'OPF collection link id attributes must be unique within collection links.',
                            $linkContext + ['previousElement' => $seenCollectionLinkIds[$linkId]['element']]
                        );
                    } else {
                        $seenCollectionLinkIds[$linkId] = $linkContext;
                    }
                }

                if (!trim($linkElement->getAttribute('refines'))) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-collection-link-refines',
                    'OPF collection link elements must not include a refines attribute.',
                    $linkContext + ['refines' => trim($linkElement->getAttribute('refines'))]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $collectionContext
     * @return list<array<string, mixed>>
     */
    private function collectionContentModelDiagnostics(\DOMElement $collection, array $collectionContext): array
    {
        $diagnostics = [];
        $seenMetadata = false;
        $seenMember = false;
        $seenLink = false;
        $lastMemberContext = null;
        $lastLinkContext = null;
        $expectedOrder = ['metadata', 'collection', 'link'];

        foreach ($collection->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $isMetadata = $this->isOpfPackageElement($child, 'metadata');
            $isCollection = $this->isOpfPackageElement($child, 'collection');
            $isLink = $this->isOpfPackageElement($child, 'link');
            if (!$isMetadata && !$isCollection && !$isLink) {
                continue;
            }

            $childContext = $this->collectionChildDiagnosticContext($child, $collectionContext);
            if ($isMetadata) {
                if ($seenMetadata) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'duplicate-collection-metadata',
                        'OPF collection must not contain more than one direct metadata child.',
                        $childContext
                    );
                }
                if ($seenMember) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-collection-child-order',
                        'OPF collection metadata must precede nested collection and link members.',
                        $this->collectionChildOrderDiagnosticContext($childContext, $lastMemberContext, $expectedOrder)
                    );
                }
                $seenMetadata = true;
                continue;
            }

            if ($isCollection && $seenLink) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-collection-child-order',
                    'OPF collection nested collection members must precede link members.',
                    $this->collectionChildOrderDiagnosticContext($childContext, $lastLinkContext, $expectedOrder)
                );
            }

            if ($isLink) {
                $seenLink = true;
                $lastLinkContext = $childContext;
            }
            $seenMember = true;
            $lastMemberContext = $childContext;
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $previousContext
     * @param list<string> $expectedOrder
     * @return array<string, mixed>
     */
    private function collectionChildOrderDiagnosticContext(array $context, ?array $previousContext, array $expectedOrder): array
    {
        $context['expectedOrder'] = $expectedOrder;
        if (is_array($previousContext)) {
            $context['previousElement'] = $previousContext['element'] ?? '';
            foreach (['id', 'role', 'rel', 'href'] as $key) {
                if (isset($previousContext[$key])) {
                    $context['previous' . ucfirst($key)] = $previousContext[$key];
                }
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $metadataContext
     * @return array<string, mixed>
     */
    private function collectionMetadataLanguageDiagnosticContext(\DOMElement $metadataChild, array $metadataContext): array
    {
        $value = $this->metadataElementText($metadataChild);
        if ($value !== '') {
            $metadataContext['value'] = $value;
        }

        return $metadataContext;
    }

    /**
     * @param array<string, mixed> $collectionContext
     * @return array<string, mixed>
     */
    private function collectionLinkDiagnosticContext(\DOMElement $linkElement, array $collectionContext): array
    {
        $context = ['element' => $this->qualifiedName($linkElement)];
        if (isset($collectionContext['id'])) {
            $context['collectionId'] = $collectionContext['id'];
        }
        if (isset($collectionContext['parentId'])) {
            $context['parentCollectionId'] = $collectionContext['parentId'];
        }
        foreach (['id', 'href', 'rel'] as $attribute) {
            $value = trim($linkElement->getAttribute($attribute));
            if ($value !== '') {
                $context[$attribute] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $collectionContext
     * @return array<string, mixed>
     */
    private function collectionChildDiagnosticContext(\DOMElement $element, array $collectionContext): array
    {
        $context = ['element' => $this->qualifiedName($element)];
        if (isset($collectionContext['id'])) {
            $context['collectionId'] = $collectionContext['id'];
        }
        if (isset($collectionContext['parentId'])) {
            $context['parentCollectionId'] = $collectionContext['parentId'];
        }
        foreach (['id', 'role', 'property', 'name', 'rel', 'refines', 'scheme'] as $attribute) {
            $value = trim($element->getAttribute($attribute));
            if ($value !== '') {
                $context[$attribute] = $value;
            }
        }
        $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($href !== '') {
            $context['href'] = $href;
        }
        $content = trim($element->getAttribute('content'));
        if ($content !== '') {
            $context['content'] = $content;
        }
        $value = $this->metadataElementText($element);
        if ($value !== '') {
            $context['value'] = $value;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $collectionContext
     * @return list<array<string, mixed>>
     */
    private function collectionMetadataIdentityDiagnostics(\DOMElement $metadataElement, array $collectionContext): array
    {
        $diagnostics = [];
        $seen = [];
        foreach ($metadataElement->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            if (!$this->isOpfPackageElement($element, 'meta') && !$this->isOpfPackageElement($element, 'link')) {
                continue;
            }

            $id = trim($element->getAttribute('id'));
            if ($id === '') {
                continue;
            }

            $context = $this->metadataIdentityDiagnosticContext($element, $id);
            if (isset($collectionContext['element'])) {
                $context['collectionElement'] = $collectionContext['element'];
            }
            if (isset($collectionContext['id'])) {
                $context['collectionId'] = $collectionContext['id'];
            }
            if (isset($collectionContext['parentId'])) {
                $context['parentCollectionId'] = $collectionContext['parentId'];
            }

            if (!$this->validXmlId($id)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-collection-metadata-id',
                    'OPF collection metadata id attributes must be XML NCNames.',
                    $context
                );
                continue;
            }

            if (isset($seen[$id])) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-collection-metadata-id',
                    'OPF collection metadata id attributes must be unique within the metadata element.',
                    $context + ['previousElement' => $seen[$id]['element']]
                );
                continue;
            }

            $seen[$id] = $context;
        }

        return $diagnostics;
    }

    /**
     * @return array<string, string>
     */
    private function collectionRefinesDiagnosticContext(string $refines): array
    {
        $context = ['refines' => $refines];
        if (str_starts_with($refines, '#') && strlen($refines) > 1) {
            $context['target'] = substr($refines, 1);
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionDiagnosticContext(\DOMElement $collection): array
    {
        $context = ['element' => $this->qualifiedName($collection)];
        $id = trim($collection->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }

        $parent = $collection->parentNode;
        if ($parent instanceof \DOMElement && $parent->localName === 'collection') {
            $parentId = trim($parent->getAttribute('id'));
            if ($parentId !== '') {
                $context['parentId'] = $parentId;
            }
        }

        return $context;
    }

    private function validCollectionRoleToken(string $role): bool
    {
        return preg_match('/^[\p{L}\p{N}_.:-]+$/u', $role) === 1
            || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $role) === 1;
    }

    private function validXmlId(string $value): bool
    {
        return preg_match('/^[\p{L}_][\p{L}\p{N}._-]*$/u', $value) === 1;
    }

    private function validPropertyValue(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || preg_match('/\s/u', $value) === 1) {
            return false;
        }

        if (!str_contains($value, ':')) {
            return $this->validXmlId($value);
        }

        [$prefix, $reference] = explode(':', $value, 2);

        return $prefix !== ''
            && $reference !== ''
            && $this->validXmlId($prefix);
    }

    private function validXmlLanguageTag(string $value): bool
    {
        if (preg_match('/[\s_]/', $value) === 1) {
            return false;
        }

        return preg_match('/^(?:[A-Za-z]{2,8}|[xXiI])(?:-[A-Za-z0-9]{1,8})*$/', $value) === 1;
    }

    private function absoluteIriLike(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) === 1;
    }

    private function validMediaType(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9!#$&^_.+*-]+\/[A-Za-z0-9!#$&^_.+*-]+(?:\s*;\s*[A-Za-z0-9!#$&^_.+*-]+=(?:"[^"]*"|[A-Za-z0-9!#$&^_.+*-]+))*$/', trim($value)) === 1;
    }

    private function normalizedMediaType(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s*;\s*/', ';', $value) ?? $value;
        $value = preg_replace('/\s*=\s*/', '=', $value) ?? $value;

        return $value;
    }

    private function isEpubCoreMediaType(string $mediaType): bool
    {
        $normalized = $this->normalizedMediaType($mediaType);
        if ($normalized === 'audio/ogg;codecs=opus') {
            return true;
        }

        $baseType = explode(';', $normalized, 2)[0];

        return in_array($baseType, [
            'image/gif',
            'image/jpeg',
            'image/png',
            'image/svg+xml',
            'image/webp',
            'audio/mpeg',
            'audio/mp4',
            'text/css',
            'font/ttf',
            'application/font-sfnt',
            'font/otf',
            'application/vnd.ms-opentype',
            'font/woff',
            'application/font-woff',
            'font/woff2',
            'application/xhtml+xml',
            'application/javascript',
            'application/ecmascript',
            'text/javascript',
            'application/x-dtbncx+xml',
            'application/smil+xml',
        ], true);
    }

    private function mediaTypeIsImage(string $mediaType): bool
    {
        if (!$this->validMediaType($mediaType)) {
            return false;
        }

        $baseType = explode(';', $this->normalizedMediaType($mediaType), 2)[0];

        return str_starts_with($baseType, 'image/');
    }

    private function collectionRoleUsesReservedIdpfHost(string $role): bool
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//', $role) !== 1) {
            return false;
        }

        $host = parse_url($role, PHP_URL_HOST);

        return is_string($host) && str_contains(strtolower($host), 'idpf.org');
    }

    private function collectionHasDirectMember(\DOMElement $collection): bool
    {
        foreach ($collection->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && ($this->isOpfPackageElement($child, 'link') || $this->isOpfPackageElement($child, 'collection'))
            ) {
                return true;
            }
        }

        return false;
    }

    private function collectionHasElementId(\DOMElement $collection, string $id): bool
    {
        if (trim($collection->getAttribute('id')) === $id) {
            return true;
        }

        foreach ($collection->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && trim($element->getAttribute('id')) === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<list<string>>
     */
    private function manifestFallbackCycles(array $manifest): array
    {
        $cycles = [];
        foreach (array_keys($manifest) as $id) {
            $path = [];
            $seen = [];
            $current = $id;
            while (isset($manifest[$current])) {
                if (isset($seen[$current])) {
                    $start = array_search($current, $path, true);
                    if ($start !== false) {
                        $cycle = array_slice($path, (int) $start);
                        $cycle[] = $current;
                        $cycleNodes = array_slice($cycle, 0, -1);
                        sort($cycleNodes, SORT_STRING);
                        $key = implode('>', $cycleNodes);
                        $cycles[$key] ??= $cycle;
                    }
                    break;
                }
                $seen[$current] = true;
                $path[] = $current;
                $fallback = trim($manifest[$current]['fallback']);
                if ($fallback === '') {
                    break;
                }
                $current = $fallback;
            }
        }

        return array_values($cycles);
    }

    /**
     * @return array<string, mixed>
     */
    private function epubDiagnostic(string $severity, string $code, string $message, array $context = []): array
    {
        return ['severity' => $severity, 'code' => $code, 'message' => $message] + $context;
    }

    private function packageResourcePath(string $base_path, string $href): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return $href;
        }

        return $this->packageResourceZipPath($base_path, $href);
    }

    private function packageResourceZipPath(string $base_path, string $href): string
    {
        return $this->localResourceZipPath($base_path, $href);
    }

    private function localResourceZipPath(string $base_path, string $href): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return '';
        }

        [$path] = $this->splitUrlPathSuffix($href);
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $path = $this->decodeUrlPathPercentEscapes($path);

        return $this->normalizeZipPath($base_path . '/' . $path);
    }

    private function decodeUrlPathPercentEscapes(string $path): string
    {
        return (string) preg_replace_callback(
            '/%[0-9A-Fa-f]{2}/',
            static function (array $match): string {
                $byte = hexdec(substr((string) $match[0], 1));
                if (in_array($byte, [0x2f, 0x5c, 0x3f, 0x23], true)) {
                    return (string) $match[0];
                }

                return chr($byte);
            },
            $path
        );
    }

    private function urlFragmentIdentifier(string $url): string
    {
        $position = strpos($url, '#');
        if ($position === false) {
            return '';
        }

        $fragment = substr($url, $position + 1);
        if ($fragment === '') {
            return '';
        }

        return rawurldecode($fragment);
    }

    private function xmlDocumentHasElementId(\DOMDocument $dom, string $id): bool
    {
        return $this->xmlDocumentElementById($dom, $id) instanceof \DOMElement;
    }

    private function xmlDocumentElementById(\DOMDocument $dom, string $id): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $this->attributeByLocalName($element, 'id') === $id) {
                return $element;
            }
        }

        return null;
    }

    private function xmlDocumentElementOrder(\DOMDocument $dom, string $id): ?int
    {
        $order = 0;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $this->attributeByLocalName($element, 'id') === $id) {
                return $order;
            }
            $order++;
        }

        return null;
    }

    private function smilClockSeconds(string $value): ?float
    {
        $value = strtolower(trim($value));
        if (str_starts_with($value, 'npt=')) {
            $value = trim(substr($value, 4));
        }

        if (preg_match('/^(\d+):([0-5]\d):([0-5]\d(?:\.\d+)?)$/', $value, $match) === 1) {
            return ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (float) $match[3];
        }

        if (preg_match('/^(\d+):([0-5]\d(?:\.\d+)?)$/', $value, $match) === 1) {
            return ((int) $match[1] * 60) + (float) $match[2];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)(h|min|s|ms)$/', $value, $match) === 1) {
            $amount = (float) $match[1];
            return match ($match[2]) {
                'h' => $amount * 3600,
                'min' => $amount * 60,
                's' => $amount,
                'ms' => $amount / 1000,
            };
        }

        return null;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>, fallback?: string, fallbackStyle?: string, mediaOverlay?: string}>
     */
    private function manifestResources(string $base_path, array $manifest): array
    {
        $resources = [];
        foreach ($manifest as $id => $item) {
            $path = $this->packageResourcePath($base_path, $item['href']);
            $resource = [
                'id' => $id,
                'href' => $item['href'],
                'path' => $path,
                'mediaType' => $this->manifestResourceMediaType($path, $item['media-type']),
                'properties' => $item['properties'],
            ];
            if ($item['fallback'] !== '') {
                $resource['fallback'] = $item['fallback'];
            }
            if ($item['fallback-style'] !== '') {
                $resource['fallbackStyle'] = $item['fallback-style'];
            }
            if ($item['media-overlay'] !== '') {
                $resource['mediaOverlay'] = $item['media-overlay'];
            }
            $resources[] = $resource;
        }

        return $resources;
    }

    private function manifestResourceMediaType(string $path, string $declaredMediaType): string
    {
        return $this->resourceMediaTypeFromPath($path, $declaredMediaType);
    }

    /**
     * @param list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>}> $manifest_resources
     * @return array<string, array{id: string, href: string, path: string, mediaType: string, properties: list<string>}>
     */
    private function manifestResourcesByPath(array $manifest_resources): array
    {
        $resources = [];
        foreach ($manifest_resources as $resource) {
            $path = (string) ($resource['path'] ?? '');
            if ($path !== '') {
                $resources[$path] = $resource;
            }
        }

        return $resources;
    }

    /**
     * @param array<string, array{id: string, href: string, path: string, mediaType: string, properties: list<string>, fallback?: string, fallbackStyle?: string, mediaOverlay?: string}> $manifestResourcesByPath
     * @return list<array<string, mixed>>
     */
    private function packageLinkResourceEntries(\ZipArchive $zip, \DOMElement $package, string $base_path, string $rootfile, array $manifestResourcesByPath): array
    {
        $resources = [];
        $seen = [];
        $packagePath = $this->normalizeZipPath($rootfile);
        $index = 0;
        foreach ($this->packageLinkElements($package) as $element) {
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
                continue;
            }
            if ($this->packageLinkHrefPathDiagnosticReason($href) !== '' || $this->packageLinkHrefFragmentDiagnosticReason($href) !== '') {
                continue;
            }
            $path = $this->packageResourceZipPath($base_path, $href);
            if ($path === '' || $path === $packagePath || isset($manifestResourcesByPath[$path]) || isset($seen[$path]) || $zip->locateName($path) === false) {
                continue;
            }
            $seen[$path] = true;
            $index++;
            $id = trim($element->getAttribute('id'));
            if ($id === '') {
                $id = 'package-link-resource-' . $index;
            }
            $mediaType = $this->packageLinkResourceMediaType($path, trim($element->getAttribute('media-type')));
            $resource = [
                'id' => $id,
                'href' => $href,
                'path' => $path,
                'mediaType' => $mediaType,
                'properties' => $this->attributeTokenList($element, 'properties'),
            ];
            foreach (['rel', 'refines', 'hreflang'] as $attribute) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $resource[$attribute] = $value;
                }
            }
            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language === '') {
                $language = trim($element->getAttribute('lang'));
            }
            if ($language !== '') {
                $resource['lang'] = $language;
            }
            $direction = strtolower(trim($element->getAttribute('dir')));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $resource['dir'] = $direction;
            }
            foreach ($this->packageLinkDiagnosticContext($element) as $key => $value) {
                if (!isset($resource[$key]) && in_array($key, ['parent', 'collectionId', 'parentCollectionId'], true)) {
                    $resource[$key] = $value;
                }
            }

            $resources[] = $resource;
        }

        return $resources;
    }

    private function packageLinkResourceMediaType(string $path, string $declaredMediaType): string
    {
        return $this->resourceMediaTypeFromPath($path, $declaredMediaType);
    }

    private function resourceMediaTypeFromPath(string $path, string $declaredMediaType): string
    {
        if ($declaredMediaType !== '') {
            return $declaredMediaType;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'aac' => 'audio/aac',
            'css' => 'text/css',
            'flac' => 'audio/flac',
            'gif' => 'image/gif',
            'htm', 'html', 'xhtml' => 'application/xhtml+xml',
            'jpeg', 'jpg' => 'image/jpeg',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'm4a' => 'audio/mp4',
            'mp3', 'mpga' => 'audio/mpeg',
            'mp4', 'm4v' => 'video/mp4',
            'ncx' => 'application/x-dtbncx+xml',
            'oga', 'ogg', 'opus' => 'audio/ogg',
            'ogv' => 'video/ogg',
            'otf' => 'font/otf',
            'png' => 'image/png',
            'smil' => 'application/smil+xml',
            'svg' => 'image/svg+xml',
            'ttf' => 'font/ttf',
            'vtt' => 'text/vtt',
            'wav' => 'audio/wav',
            'webm' => 'video/webm',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'xml' => 'application/xml',
            default => 'application/octet-stream',
        };
    }

    /**
     * @param list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>}> $assetResources
     * @param list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>}> $packageLinkResources
     * @return list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>}>
     */
    private function resourceEntriesForPayloadExtraction(array $assetResources, array $packageLinkResources): array
    {
        $resources = [];
        $seen = [];
        foreach ([$assetResources, $packageLinkResources] as $resourceList) {
            foreach ($resourceList as $resource) {
                $path = (string) ($resource['path'] ?? '');
                if ($path === '' || isset($seen[$path])) {
                    continue;
                }
                $seen[$path] = true;
                $resources[] = $resource;
            }
        }

        return $resources;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array{idref: string, item: array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}, path: string}|null
     */
    private function readableSpineManifestItem(array $manifest, string $idref, string $base_path): ?array
    {
        $seen = [];
        $current = $idref;

        while (isset($manifest[$current]) && !isset($seen[$current])) {
            $seen[$current] = true;
            $item = $manifest[$current];
            $path = $this->packageResourceZipPath($base_path, $item['href']);
            if ($path !== '' && $this->isReadableSpineResource($path, $item['media-type'])) {
                return [
                    'idref' => $current,
                    'item' => $item,
                    'path' => $path,
                ];
            }

            $fallback = trim($item['fallback']);
            if ($fallback === '') {
                return null;
            }
            $current = $fallback;
        }

        return null;
    }

    private function isReadableSpineResource(string $path, string $media_type): bool
    {
        $path = strtolower($path);
        $media_type = strtolower($media_type);

        return str_contains($media_type, 'html')
            || str_ends_with($path, '.xhtml')
            || str_ends_with($path, '.html')
            || str_ends_with($path, '.htm');
    }

    /**
     * @param list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>}> $manifest_resources
     * @return list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>}>
     */
    private function assetResourceEntries(array $manifest_resources): array
    {
        return array_values(array_filter(
            $manifest_resources,
            fn (array $resource): bool => !$this->isDocumentResource($resource['path'], $resource['mediaType'], $resource['properties'])
        ));
    }

    /**
     * @param list<string> $properties
     */
    private function isDocumentResource(string $path, string $media_type, array $properties): bool
    {
        $path = strtolower($path);
        $media_type = strtolower($media_type);
        $properties = array_map('strtolower', $properties);

        return in_array('nav', $properties, true)
            || str_contains($media_type, 'html')
            || str_contains($media_type, 'x-dtbncx')
            || str_ends_with($path, '.xhtml')
            || str_ends_with($path, '.html')
            || str_ends_with($path, '.htm')
            || str_ends_with($path, '.ncx');
    }

    /**
     * @param list<array{id: string, href: string, path: string, mediaType: string, properties: list<string>}> $resources
     * @param list<string> $referenced_resources
     * @return array{
     *     payloads: array<string, array{mediaType: string, properties: list<string>, encoding: string, bytes: int, data: string}>,
     *     skipped: list<array{path: string, reason: string, bytes?: int}>,
     *     bytes: int
     * }
     */
    private function extractedResourcePayloads(\ZipArchive $zip, array $resources, array &$referenced_resources): array
    {
        $payloads = [];
        $skipped = [];
        $total_bytes = 0;
        $max_bytes = $this->resourceMaxBytes();
        $total_max_bytes = $this->resourceTotalMaxBytes();

        foreach ($resources as $resource) {
            $path = $resource['path'];
            $stat = $zip->statName($path);
            $declared_size = is_array($stat) && isset($stat['size']) ? (int) $stat['size'] : null;
            if ($declared_size !== null && $declared_size > $max_bytes) {
                $skipped[] = ['path' => $path, 'reason' => 'resource-limit', 'bytes' => $declared_size];
                continue;
            }
            if ($declared_size !== null && $total_bytes + $declared_size > $total_max_bytes) {
                $skipped[] = ['path' => $path, 'reason' => 'total-limit', 'bytes' => $declared_size];
                continue;
            }

            $bytes = $zip->getFromName($path);
            if (!is_string($bytes)) {
                $skipped[] = ['path' => $path, 'reason' => 'missing'];
                continue;
            }

            $size = strlen($bytes);
            if ($size > $max_bytes) {
                $skipped[] = ['path' => $path, 'reason' => 'resource-limit', 'bytes' => $size];
                continue;
            }
            if ($total_bytes + $size > $total_max_bytes) {
                $skipped[] = ['path' => $path, 'reason' => 'total-limit', 'bytes' => $size];
                continue;
            }

            $resource_references = [];
            $payload_bytes = $this->resourcePayloadBytes($bytes, $resource, $resource_references);
            $payload_size = strlen($payload_bytes);
            if ($payload_size > $max_bytes) {
                $skipped[] = ['path' => $path, 'reason' => 'resource-limit', 'bytes' => $payload_size];
                continue;
            }
            if ($total_bytes + $payload_size > $total_max_bytes) {
                $skipped[] = ['path' => $path, 'reason' => 'total-limit', 'bytes' => $payload_size];
                continue;
            }

            $payloads[$path] = [
                'mediaType' => $resource['mediaType'],
                'properties' => $resource['properties'],
                'encoding' => 'base64',
                'bytes' => $payload_size,
                'data' => base64_encode($payload_bytes),
            ];
            foreach ($resource_references as $resource_reference) {
                $referenced_resources[] = $resource_reference;
            }
            $total_bytes += $payload_size;
        }

        return ['payloads' => $payloads, 'skipped' => $skipped, 'bytes' => $total_bytes];
    }

    /**
     * @param array{id: string, href: string, path: string, mediaType: string, properties: list<string>} $resource
     * @param list<string> $referenced_resources
     */
    private function resourcePayloadBytes(string $bytes, array $resource, array &$referenced_resources): string
    {
        if (!$this->resourceLooksLikeCss($resource['path'], $resource['mediaType'])) {
            return $bytes;
        }

        return $this->rewriteCssResourceReferences($bytes, $this->dirname($resource['path']), $referenced_resources);
    }

    private function resourceLooksLikeCss(string $path, string $mediaType): bool
    {
        $mediaType = strtolower($mediaType);
        $path = strtolower($path);

        return str_contains($mediaType, 'css') || str_ends_with($path, '.css');
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<string>
     */
    private function imageResources(string $base_path, array $manifest): array
    {
        $resources = [];
        foreach ($manifest as $item) {
            $href = $this->packageResourceZipPath($base_path, $item['href']);
            if ($href === '') {
                continue;
            }
            $media_type = strtolower($item['media-type']);
            if (str_starts_with($media_type, 'image/') || $this->pathLooksLikeImage($href)) {
                $resources[] = $href;
            }
        }

        return array_values(array_unique($resources));
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, mixed> $metadata
     * @param list<array{type: string, title: string, href: string}> $guideReferences
     */
    private function coverImageResource(string $base_path, array $manifest, array $metadata, array $guideReferences): string
    {
        foreach ($manifest as $item) {
            $properties = array_map('strtolower', $item['properties']);
            if (in_array('cover-image', $properties, true) && $this->mediaTypeIsImage($item['media-type'])) {
                $href = $this->packageResourceZipPath($base_path, $item['href']);
                if ($href !== '') {
                    return $href;
                }
            }
        }

        $legacy_cover_id = $metadata['epubCoverItemId'] ?? null;
        if (is_string($legacy_cover_id) && isset($manifest[$legacy_cover_id])) {
            $href = $this->packageResourceZipPath($base_path, $manifest[$legacy_cover_id]['href']);
            if ($href !== '') {
                return $href;
            }
        }

        foreach ($guideReferences as $reference) {
            if ($reference['type'] === 'cover' && $this->pathLooksLikeImage($reference['href'])) {
                return $reference['href'];
            }
        }

        return '';
    }

    /**
     * @return list<array{type: string, title: string, href: string}>
     */
    private function guideReferences(\DOMElement $package, string $base_path): array
    {
        $references = [];
        $child = $this->directOpfChildElement($package, 'guide');
        if ($child !== null) {
            foreach ($this->directOpfChildElements($child, 'reference') as $reference) {
                $href = html_entity_decode(trim($reference->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($href === '') {
                    continue;
                }
                $references[] = [
                    'type' => strtolower(trim($reference->getAttribute('type'))),
                    'title' => trim($reference->getAttribute('title')),
                    'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                ];
            }
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $navigation
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array<string, mixed>
     */
    private function navigationWithGuideDerivedLandmarks(
        array $navigation,
        \ZipArchive $zip,
        \DOMElement $package,
        array $spineItems,
        array $manifest,
        string $basePath
    ): array {
        if (($navigation['landmarks'] ?? []) !== []) {
            return $navigation;
        }
        if ((int) ($navigation['landmarkNavCount'] ?? 0) > 0) {
            return $navigation;
        }

        $landmarks = $this->guideReferenceLandmarkEntries($zip, $package, $spineItems, $manifest, $basePath);
        if ($landmarks !== []) {
            $navigation['landmarks'] = $landmarks;
        }

        return $navigation;
    }

    /**
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array{text: string, href: string, level: int, type: string}>
     */
    private function guideReferenceLandmarkEntries(
        \ZipArchive $zip,
        \DOMElement $package,
        array $spineItems,
        array $manifest,
        string $basePath
    ): array {
        $linearTargetPaths = $this->linearSpineTargetPaths($spineItems, $manifest, $basePath);
        if ($linearTargetPaths === []) {
            return [];
        }

        $guide = $this->directOpfChildElement($package, 'guide');
        if ($guide === null) {
            return [];
        }

        $entries = [];
        $seen = [];
        $targetDocuments = [];
        foreach ($this->directOpfChildElements($guide, 'reference') as $reference) {
            $guideType = strtolower(trim($reference->getAttribute('type')));
            if ($guideType === '' || !$this->validXmlId($guideType)) {
                continue;
            }

            $landmarkType = $this->landmarkTypeForGuideReference($guideType);
            if ($landmarkType === '') {
                continue;
            }

            $href = html_entity_decode(trim($reference->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if (!$this->validGuideReferenceLandmarkHref($href)) {
                continue;
            }

            $landmarkHref = $this->rewriteRelativeResourceUrl($href, $basePath);
            [$targetPath] = $this->splitUrlPathSuffix($landmarkHref);
            $targetPath = $this->normalizeZipPath($targetPath);
            if ($targetPath === '' || !isset($linearTargetPaths[$targetPath])) {
                continue;
            }

            $fragment = $this->urlFragmentIdentifier($href);
            if ($fragment !== '' && !$this->guideReferenceFragmentExists($zip, $targetPath, $fragment, $targetDocuments)) {
                continue;
            }

            $key = $landmarkType . "\0" . $landmarkHref;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $entries[] = [
                'text' => trim($reference->getAttribute('title')) ?: ucfirst($landmarkType),
                'href' => $landmarkHref,
                'level' => 1,
                'type' => $landmarkType,
            ];
        }

        return $entries;
    }

    /**
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array<string, true>
     */
    private function linearSpineTargetPaths(array $spineItems, array $manifest, string $basePath): array
    {
        $paths = [];
        foreach ($spineItems as $spineItem) {
            $idref = $spineItem['idref'];
            if (!$spineItem['linear'] || !isset($manifest[$idref])) {
                continue;
            }

            $readableItem = $this->readableSpineManifestItem($manifest, $idref, $basePath);
            if ($readableItem === null) {
                continue;
            }

            $path = $this->normalizeZipPath($readableItem['path']);
            if ($path !== '') {
                $paths[$path] = true;
            }
        }

        return $paths;
    }

    private function landmarkTypeForGuideReference(string $type): string
    {
        return $type === 'text' ? 'bodymatter' : $type;
    }

    private function validGuideReferenceLandmarkHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
            return false;
        }
        if (str_starts_with(strtolower($href), 'data:') || str_starts_with(strtolower($href), 'file:')) {
            return false;
        }

        return $this->guideReferenceHrefPathDiagnosticReason($href) === ''
            && $this->guideReferenceHrefFragmentDiagnosticReason($href) === '';
    }

    /**
     * @param array<string, \DOMDocument|false> $targetDocuments
     */
    private function guideReferenceFragmentExists(\ZipArchive $zip, string $targetPath, string $fragment, array &$targetDocuments): bool
    {
        if (!array_key_exists($targetPath, $targetDocuments)) {
            $targetDocuments[$targetPath] = false;
            $targetXml = $zip->getFromName($targetPath);
            if (is_string($targetXml)) {
                try {
                    $targetDocuments[$targetPath] = $this->loadXml($targetXml, 'EPUB guide reference landmark target');
                } catch (\Throwable) {
                    $targetDocuments[$targetPath] = false;
                }
            }
        }

        $targetDocument = $targetDocuments[$targetPath];

        return $targetDocument instanceof \DOMDocument
            && $this->xmlDocumentHasElementId($targetDocument, $fragment);
    }

    /**
     * @return list<array{mediaType: string, handler: string}>
     */
    private function bindings(\DOMElement $package): array
    {
        $bindings = [];
        $child = $this->directOpfChildElement($package, 'bindings');
        if ($child !== null) {
            foreach ($this->directOpfChildElements($child, 'mediaType') as $mediaType) {
                $type = trim($mediaType->getAttribute('media-type'));
                $handler = trim($mediaType->getAttribute('handler'));
                if ($type === '' || $handler === '') {
                    continue;
                }
                $bindings[] = [
                    'mediaType' => $type,
                    'handler' => $handler,
                ];
            }
        }

        return $bindings;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collections(\DOMElement $package, string $base_path): array
    {
        $collections = [];
        foreach ($this->directOpfChildElements($package, 'collection') as $child) {
            $collection = $this->collectionEntry($child, $base_path);
            if ($collection !== null) {
                $collections[] = $collection;
            }
        }

        return $collections;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collectionEntry(\DOMElement $collection, string $base_path): ?array
    {
        $role = trim($collection->getAttribute('role'));
        if ($role === '') {
            return null;
        }

        $entry = ['role' => $role];
        foreach ([
            'id' => trim($collection->getAttribute('id')),
            'dir' => strtolower(trim($collection->getAttribute('dir'))),
            'lang' => trim($collection->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang')),
        ] as $key => $value) {
            if ($value === '') {
                continue;
            }
            if ($key === 'dir' && !in_array($value, ['ltr', 'rtl', 'auto'], true)) {
                continue;
            }
            $entry[$key] = $value;
        }

        $metadata = [];
        $links = [];
        $children = [];
        foreach ($collection->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($this->isOpfPackageElement($child, 'metadata')) {
                foreach ($child->childNodes as $metadataEntry) {
                    if (!$metadataEntry instanceof \DOMElement) {
                        continue;
                    }
                    if (!$this->isOpfPackageElement($metadataEntry, 'meta') && !$this->isOpfPackageElement($metadataEntry, 'link')) {
                        continue;
                    }
                    if ($this->isOpfPackageElement($metadataEntry, 'link')) {
                        $href = html_entity_decode(trim($metadataEntry->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                        if ($href === '') {
                            continue;
                        }
                        $record = [
                            'name' => 'link',
                            'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                        ];
                        foreach ([
                            'rel' => 'rel',
                            'hreflang' => 'hreflang',
                            'mediaType' => 'media-type',
                            'properties' => 'properties',
                            'refines' => 'refines',
                            'id' => 'id',
                        ] as $key => $attribute) {
                            $attributeValue = trim($metadataEntry->getAttribute($attribute));
                            if ($attributeValue === '') {
                                continue;
                            }
                            $record[$key] = $key === 'properties'
                                ? array_values(array_filter(preg_split('/\s+/', $attributeValue) ?: [], static fn (string $token): bool => $token !== ''))
                                : $attributeValue;
                        }
                        $direction = strtolower(trim($metadataEntry->getAttribute('dir')));
                        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                            $record['dir'] = $direction;
                        }
                        $language = trim($metadataEntry->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
                        if ($language === '') {
                            $language = trim($metadataEntry->getAttribute('lang'));
                        }
                        if ($language !== '') {
                            $record['lang'] = $language;
                        }
                        $metadata[] = $record;
                        continue;
                    }
                    $value = trim(preg_replace('/\s+/', ' ', $metadataEntry->textContent) ?? $metadataEntry->textContent);
                    if ($value === '') {
                        continue;
                    }
                    $record = [
                        'name' => $this->qualifiedName($metadataEntry),
                        'value' => $value,
                    ];
                    foreach (['id', 'property', 'refines', 'scheme'] as $attribute) {
                        $attributeValue = trim($metadataEntry->getAttribute($attribute));
                        if ($attributeValue !== '') {
                            $record[$attribute] = $attributeValue;
                        }
                    }
                    $direction = strtolower(trim($metadataEntry->getAttribute('dir')));
                    if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                        $record['dir'] = $direction;
                    }
                    $language = trim($metadataEntry->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
                    if ($language === '') {
                        $language = trim($metadataEntry->getAttribute('lang'));
                    }
                    if ($language !== '') {
                        $record['lang'] = $language;
                    }
                    $metadata[] = $record;
                }
                continue;
            }
            if ($this->isOpfPackageElement($child, 'link')) {
                $href = html_entity_decode(trim($child->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($href === '') {
                    continue;
                }
                $link = [
                    'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                ];
                foreach ([
                    'rel' => 'rel',
                    'hreflang' => 'hreflang',
                    'mediaType' => 'media-type',
                    'properties' => 'properties',
                    'refines' => 'refines',
                    'id' => 'id',
                ] as $key => $attribute) {
                    $value = trim($child->getAttribute($attribute));
                    if ($value === '') {
                        continue;
                    }
                    $link[$key] = $key === 'properties'
                        ? array_values(array_filter(preg_split('/\s+/', $value) ?: [], static fn (string $token): bool => $token !== ''))
                        : $value;
                }
                $language = trim($child->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
                if ($language === '') {
                    $language = trim($child->getAttribute('lang'));
                }
                if ($language !== '') {
                    $link['lang'] = $language;
                }
                $direction = strtolower(trim($child->getAttribute('dir')));
                if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                    $link['dir'] = $direction;
                }
                $links[] = $link;
                continue;
            }
            if ($this->isOpfPackageElement($child, 'collection')) {
                $nested = $this->collectionEntry($child, $base_path);
                if ($nested !== null) {
                    $children[] = $nested;
                }
            }
        }
        if ($metadata !== []) {
            $entry['metadata'] = $metadata;
        }
        if ($links !== []) {
            $entry['links'] = $links;
        }
        if ($children !== []) {
            $entry['collections'] = $children;
        }

        return $entry;
    }

    private function qualifiedName(\DOMElement $element): string
    {
        return $element->prefix === null || $element->prefix === ''
            ? $element->localName
            : $element->prefix . ':' . $element->localName;
    }

    private function directChildElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function directOpfChildElement(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isOpfPackageElement($child, $localName)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function directChildElements(\DOMElement $parent, string $localName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * @return list<\DOMElement>
     */
    private function directOpfChildElements(\DOMElement $parent, string $localName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isOpfPackageElement($child, $localName)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function isOpfPackageElement(\DOMElement $element, string $localName): bool
    {
        $namespace = $element->namespaceURI ?? '';

        return $element->localName === $localName
            && ($namespace === self::OPF_NAMESPACE || $namespace === '');
    }

    private function packageHasElementId(\DOMElement $package, string $id): bool
    {
        return $this->packageElementById($package, $id) instanceof \DOMElement;
    }

    private function packageElementById(\DOMElement $package, string $id): ?\DOMElement
    {
        foreach ($this->primaryPackageDescendantElements($package) as $element) {
            if (trim($element->getAttribute('id')) === $id) {
                return $element;
            }
        }

        return null;
    }

    private function packageUniqueIdentifierElement(\DOMElement $package, string $id): ?\DOMElement
    {
        $metadataElement = $this->directOpfChildElement($package, 'metadata');
        if (!$metadataElement instanceof \DOMElement) {
            return null;
        }

        foreach ($metadataElement->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && $child->localName === 'identifier'
                && $child->namespaceURI === self::DC_NAMESPACE
                && trim($child->getAttribute('id')) === $id
            ) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function primaryPackageDescendantElements(\DOMElement $package): array
    {
        $elements = [];
        foreach ($package->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isPrimaryPackageBranch($package, $child)) {
                continue;
            }

            $elements[] = $child;
            foreach ($child->getElementsByTagName('*') as $descendant) {
                if ($descendant instanceof \DOMElement) {
                    $elements[] = $descendant;
                }
            }
        }

        return $elements;
    }

    private function isPrimaryPackageBranch(\DOMElement $package, \DOMElement $child): bool
    {
        if (!$this->isOpfPackageElement($child, $child->localName)) {
            return false;
        }

        if (!in_array($child->localName, ['metadata', 'manifest', 'spine', 'guide', 'bindings', 'collection'], true)) {
            return false;
        }

        if (!in_array($child->localName, ['metadata', 'manifest', 'spine', 'guide', 'bindings'], true)) {
            return true;
        }

        $primary = $this->directOpfChildElement($package, $child->localName);

        return $primary instanceof \DOMElement && $child->isSameNode($primary);
    }

    /**
     * @return list<array{idref: string, linear: bool, properties: list<string>, id?: string}>
     */
    private function spineItems(\DOMElement $package): array
    {
        $items = [];
        $spineElement = $this->directOpfChildElement($package, 'spine');
        if ($spineElement === null) {
            return $items;
        }

        foreach ($this->directOpfChildElements($spineElement, 'itemref') as $element) {
            $idref = trim($element->getAttribute('idref'));
            if ($idref !== '') {
                $linear = strtolower(trim($element->getAttribute('linear'))) !== 'no';
                $item = [
                    'idref' => $idref,
                    'linear' => $linear,
                    'properties' => array_values(array_filter(
                        preg_split('/\s+/', trim($element->getAttribute('properties'))) ?: [],
                        static fn (string $property): bool => $property !== ''
                    )),
                ];
                $id = trim($element->getAttribute('id'));
                if ($id !== '') {
                    $item['id'] = $id;
                }
                $items[] = $item;
            }
        }

        return $items;
    }

    private function spineTocId(\DOMElement $package): string
    {
        $element = $this->directOpfChildElement($package, 'spine');
        if ($element instanceof \DOMElement) {
            return trim($element->getAttribute('toc'));
        }

        return '';
    }

    /**
     * @return array{id: string, toc: string, pageProgressionDirection: string}
     */
    private function spineMetadata(\DOMElement $package): array
    {
        $element = $this->directOpfChildElement($package, 'spine');
        if ($element instanceof \DOMElement) {
            $direction = strtolower(trim($element->getAttribute('page-progression-direction')));
            if (!in_array($direction, ['ltr', 'rtl', 'default'], true)) {
                $direction = '';
            }

            return [
                'id' => trim($element->getAttribute('id')),
                'toc' => trim($element->getAttribute('toc')),
                'pageProgressionDirection' => $direction,
            ];
        }

        return ['id' => '', 'toc' => '', 'pageProgressionDirection' => ''];
    }

    /**
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, array{viewport?: array{width: int, height: int, content: string, properties?: array<string, string>}, language?: string, direction?: string, rootAttributes?: array<string, mixed>, bodyAttributes?: array<string, mixed>, semanticElements?: list<array<string, mixed>>, headTitle?: string, headBases?: list<array<string, mixed>>, headMetas?: list<array<string, mixed>>, links?: list<array<string, mixed>>, headStyles?: list<array<string, mixed>>, headScripts?: list<array<string, mixed>>}> $xhtmlMetadata
     * @param array<string, array{metadataProperties?: list<array<string, mixed>>, renditionLayout?: string, renditionOrientation?: string, renditionSpread?: string, renditionFlow?: string, viewport?: array{width: int, height: int, content: string, properties?: array<string, string>}}> $renditionMetadata
     * @return list<array{idref: string, linear: bool, properties: list<string>, id?: string, href?: string, path?: string, mediaType?: string, manifestProperties?: list<string>, mediaOverlay?: string, viewport?: array{width: int, height: int, content: string, properties?: array<string, string>}, language?: string, direction?: string, rootAttributes?: array<string, mixed>, bodyAttributes?: array<string, mixed>, semanticElements?: list<array<string, mixed>>, metadataProperties?: list<array<string, mixed>>, pageSpread?: string, renditionLayout?: string, renditionOrientation?: string, renditionSpread?: string, renditionFlow?: string}>
     */
    private function spineItemMetadata(array $spineItems, array $manifest, string $base_path, array $xhtmlMetadata = [], array $renditionMetadata = []): array
    {
        $items = [];
        foreach ($spineItems as $spineItem) {
            $entry = [
                'idref' => $spineItem['idref'],
                'linear' => $spineItem['linear'],
                'properties' => $spineItem['properties'],
            ];
            if (isset($spineItem['id']) && $spineItem['id'] !== '') {
                $entry['id'] = $spineItem['id'];
            }
            $itemrefRenditionConflicts = $this->spineItemItemrefScopedRenditionMetadataConflicts($spineItem, $renditionMetadata);
            foreach ($this->spineItemRenditionProperties($spineItem['properties']) as $key => $value) {
                if (!isset($itemrefRenditionConflicts[$key])) {
                    $entry[$key] = $value;
                }
            }
            $manifestItem = $manifest[$spineItem['idref']] ?? null;
            if (is_array($manifestItem)) {
                $entry['href'] = $manifestItem['href'];
                $path = $this->packageResourcePath($base_path, $manifestItem['href']);
                $entry['path'] = $path;
                $entry['mediaType'] = $this->manifestResourceMediaType($path, $manifestItem['media-type']);
                if ($manifestItem['properties'] !== []) {
                    $entry['manifestProperties'] = $manifestItem['properties'];
                }
                if ($manifestItem['fallback'] !== '') {
                    $entry['fallback'] = $manifestItem['fallback'];
                    $fallbackItem = $manifest[$manifestItem['fallback']] ?? null;
                    if (is_array($fallbackItem)) {
                        $entry['fallbackHref'] = $fallbackItem['href'];
                        $fallbackPath = $this->packageResourcePath($base_path, $fallbackItem['href']);
                        $entry['fallbackPath'] = $fallbackPath;
                        $entry['fallbackMediaType'] = $this->manifestResourceMediaType($fallbackPath, $fallbackItem['media-type']);
                    }
                }
                if ($manifestItem['fallback-style'] !== '') {
                    $entry['fallbackStyle'] = $manifestItem['fallback-style'];
                }
                if ($manifestItem['media-overlay'] !== '') {
                    $entry['mediaOverlay'] = $manifestItem['media-overlay'];
                }
            }
            $metadata = $xhtmlMetadata[$spineItem['idref']] ?? [];
            if (isset($metadata['viewport'])) {
                $entry['viewport'] = $metadata['viewport'];
            }
            if (isset($metadata['language'])) {
                $entry['language'] = $metadata['language'];
            }
            if (isset($metadata['direction'])) {
                $entry['direction'] = $metadata['direction'];
            }
            if (isset($metadata['rootAttributes']) && is_array($metadata['rootAttributes'])) {
                $entry['rootAttributes'] = $metadata['rootAttributes'];
            }
            if (isset($metadata['bodyAttributes']) && is_array($metadata['bodyAttributes'])) {
                $entry['bodyAttributes'] = $metadata['bodyAttributes'];
            }
            if (isset($metadata['semanticElements']) && is_array($metadata['semanticElements'])) {
                $entry['semanticElements'] = $metadata['semanticElements'];
            }
            if (isset($metadata['headTitle']) && is_string($metadata['headTitle']) && trim($metadata['headTitle']) !== '') {
                $entry['headTitle'] = trim($metadata['headTitle']);
            }
            if (isset($metadata['headBases']) && is_array($metadata['headBases'])) {
                $entry['headBases'] = $metadata['headBases'];
            }
            if (isset($metadata['headMetas']) && is_array($metadata['headMetas'])) {
                $entry['headMetas'] = $metadata['headMetas'];
            }
            if (isset($metadata['links']) && is_array($metadata['links'])) {
                $entry['links'] = $metadata['links'];
            }
            if (isset($metadata['headStyles']) && is_array($metadata['headStyles'])) {
                $entry['headStyles'] = $metadata['headStyles'];
            }
            if (isset($metadata['headScripts']) && is_array($metadata['headScripts'])) {
                $entry['headScripts'] = $metadata['headScripts'];
            }
            $scopedRenditionConflicts = $this->spineItemScopedRenditionMetadataConflicts($spineItem, $renditionMetadata);
            foreach (array_keys($itemrefRenditionConflicts) as $key) {
                $scopedRenditionConflicts[$key] = true;
            }
            foreach ($this->spineRenditionTargets($spineItem) as $target) {
                $rendition = $renditionMetadata[$target] ?? [];
                if (isset($rendition['metadataProperties']) && is_array($rendition['metadataProperties'])) {
                    $entry['metadataProperties'] = array_merge($entry['metadataProperties'] ?? [], $rendition['metadataProperties']);
                }
                if (!isset($entry['viewport']) && !isset($scopedRenditionConflicts['viewport']) && isset($rendition['viewport']) && is_array($rendition['viewport'])) {
                    $entry['viewport'] = $rendition['viewport'];
                }
                foreach (['renditionLayout', 'renditionOrientation', 'renditionSpread', 'renditionFlow'] as $key) {
                    if (!isset($entry[$key]) && !isset($scopedRenditionConflicts[$key]) && isset($rendition[$key]) && is_string($rendition[$key])) {
                        $entry[$key] = $rendition[$key];
                    }
                }
            }
            $items[] = $entry;
        }

        return $items;
    }

    /**
     * @param array{idref: string, properties: list<string>, id?: string} $spineItem
     * @param array<string, array{metadataProperties?: list<array<string, mixed>>}> $renditionMetadata
     * @return array<string, true>
     */
    private function spineItemItemrefScopedRenditionMetadataConflicts(array $spineItem, array $renditionMetadata): array
    {
        $itemrefGroups = $this->spineItemRenditionPropertyValueGroups($spineItem['properties']);
        if ($itemrefGroups === []) {
            return [];
        }

        $targets = $this->spineRenditionTargets($spineItem);
        $records = [];
        foreach ($targets as $target) {
            foreach ($renditionMetadata[$target]['metadataProperties'] ?? [] as $record) {
                if (is_array($record)) {
                    $records[] = $record;
                }
            }
        }

        $conflicts = [];
        $targetGroups = $this->scopedRenditionMetadataValueGroups($records);
        $metadataGroups = $this->spineScopedRenditionMetadataPropertyGroups($targets, $targetGroups);
        foreach ($itemrefGroups as $key => $itemrefGroup) {
            $itemrefValues = array_keys($itemrefGroup['values'] ?? []);
            if (count($itemrefValues) !== 1) {
                continue;
            }

            $metadataGroup = $metadataGroups[$this->renditionSpinePropertyGroupName($key)] ?? null;
            if (!is_array($metadataGroup)) {
                continue;
            }

            $metadataValues = array_keys($metadataGroup['values'] ?? []);
            if (count($metadataValues) === 1 && $metadataValues[0] !== $itemrefValues[0]) {
                $conflicts[$key] = true;
            }
        }

        return $conflicts;
    }

    /**
     * @param array{idref: string, id?: string} $spineItem
     * @param array<string, array{metadataProperties?: list<array<string, mixed>>}> $renditionMetadata
     * @return array<string, true>
     */
    private function spineItemScopedRenditionMetadataConflicts(array $spineItem, array $renditionMetadata): array
    {
        $targets = $this->spineRenditionTargets($spineItem);
        if (count($targets) < 2) {
            return [];
        }

        $records = [];
        foreach ($targets as $target) {
            foreach ($renditionMetadata[$target]['metadataProperties'] ?? [] as $record) {
                if (is_array($record)) {
                    $records[] = $record;
                }
            }
        }

        $conflicts = [];
        $targetGroups = $this->scopedRenditionMetadataValueGroups($records);
        foreach ($this->spineScopedRenditionMetadataPropertyGroups($targets, $targetGroups) as $property => $group) {
            if (count($group['values'] ?? []) < 2 || count($group['targets'] ?? []) < 2) {
                continue;
            }

            $key = $this->scopedRenditionMetadataKey($property);
            if ($key !== null) {
                $conflicts[$key] = true;
            }
        }

        return $conflicts;
    }

    /**
     * @param array{idref: string, id?: string} $spineItem
     * @return list<string>
     */
    private function spineRenditionTargets(array $spineItem): array
    {
        $targets = [];
        foreach ([$spineItem['id'] ?? '', $spineItem['idref']] as $target) {
            if ($target !== '' && !in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param list<string> $properties
     */
    private function spineItemRenditionProperties(array $properties): array
    {
        $rendition = [];
        $conflicts = [];
        foreach ($this->spineItemRenditionPropertyValueGroups($properties) as $key => $group) {
            $values = array_keys($group['values'] ?? []);
            if (count($values) !== 1) {
                $conflicts[$key] = true;
                continue;
            }

            $value = $values[0];
            if (isset($conflicts[$key])) {
                continue;
            }
            if (isset($rendition[$key]) && $rendition[$key] !== $value) {
                unset($rendition[$key]);
                $conflicts[$key] = true;
                continue;
            }
            $rendition[$key] = $value;
        }

        return $rendition;
    }

    /**
     * @param list<string> $properties
     * @return array<string, array{values: array<string, true>, properties: list<string>}>
     */
    private function spineItemRenditionPropertyValueGroups(array $properties): array
    {
        $groups = [];
        foreach ($properties as $property) {
            $entry = $this->spineItemRenditionProperty(strtolower(trim($property)));
            if ($entry === null) {
                continue;
            }

            [$key, $value] = $entry;
            $groups[$key]['values'][$value] = true;
            $groups[$key]['properties'][] = $property;
        }

        return $groups;
    }

    /**
     * @return array{string, string}|null
     */
    private function spineItemRenditionProperty(string $property): ?array
    {
        return match ($property) {
            'rendition:page-spread-left', 'page-spread-left' => ['pageSpread', 'left'],
            'rendition:page-spread-right', 'page-spread-right' => ['pageSpread', 'right'],
            'rendition:page-spread-center', 'page-spread-center' => ['pageSpread', 'center'],
            'rendition:layout-reflowable' => ['renditionLayout', 'reflowable'],
            'rendition:layout-pre-paginated' => ['renditionLayout', 'pre-paginated'],
            'rendition:orientation-landscape' => ['renditionOrientation', 'landscape'],
            'rendition:orientation-portrait' => ['renditionOrientation', 'portrait'],
            'rendition:orientation-auto' => ['renditionOrientation', 'auto'],
            'rendition:spread-none' => ['renditionSpread', 'none'],
            'rendition:spread-landscape' => ['renditionSpread', 'landscape'],
            'rendition:spread-portrait' => ['renditionSpread', 'portrait'],
            'rendition:spread-both' => ['renditionSpread', 'both'],
            'rendition:spread-auto' => ['renditionSpread', 'auto'],
            'rendition:flow-paginated' => ['renditionFlow', 'paginated'],
            'rendition:flow-scrolled-continuous' => ['renditionFlow', 'scrolled-continuous'],
            'rendition:flow-scrolled-doc' => ['renditionFlow', 'scrolled-doc'],
            'rendition:flow-auto' => ['renditionFlow', 'auto'],
            default => null,
        };
    }

    /**
     * @return array{viewport?: array{width: int, height: int, content: string}, language?: string, direction?: string, rootAttributes?: array<string, mixed>, bodyAttributes?: array<string, mixed>, semanticElements?: list<array<string, mixed>>, headTitle?: string, headBases?: list<array<string, mixed>>, headMetas?: list<array<string, mixed>>, links?: list<array<string, mixed>>, headStyles?: list<array<string, mixed>>, headScripts?: list<array<string, mixed>>}
     */
    private function xhtmlMetadata(string $xml, string $base_path): array
    {
        try {
            $dom = $this->loadXml($xml, 'EPUB spine XHTML metadata');
        } catch (\InvalidArgumentException) {
            return [];
        }

        $metadata = [];
        $root = $dom->documentElement;
        if ($root instanceof \DOMElement) {
            $language = trim($root->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language === '') {
                $language = trim($root->getAttribute('lang'));
            }
            if ($this->validLanguageTag($language)) {
                $metadata['language'] = $language;
            }

            $direction = strtolower(trim($root->getAttribute('dir')));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $metadata['direction'] = $direction;
            }
        }

        $rootAttributes = $this->xhtmlRootAttributesFromDom($dom);
        if (array_diff_key($rootAttributes, ['lang' => true, 'dir' => true]) !== []) {
            $metadata['rootAttributes'] = $rootAttributes;
        }
        $bodyAttributes = $this->xhtmlBodyAttributesFromDom($dom);
        if ($bodyAttributes !== []) {
            $metadata['bodyAttributes'] = $bodyAttributes;
        }
        $headTitle = $this->xhtmlHeadTitleFromDom($dom);
        if ($headTitle !== '') {
            $metadata['headTitle'] = $headTitle;
        }
        $headBases = $this->xhtmlHeadBasesFromDom($dom, $base_path);
        if ($headBases !== []) {
            $metadata['headBases'] = $headBases;
        }
        $resource_base_path = $this->xhtmlResourceBasePath(['headBases' => $headBases], $base_path);
        $semanticElements = $this->xhtmlSemanticElementsFromDom($dom, $resource_base_path);
        if ($semanticElements !== []) {
            $metadata['semanticElements'] = $semanticElements;
        }
        $viewport = $this->xhtmlViewportFromDom($dom);
        if ($viewport !== null) {
            $metadata['viewport'] = $viewport;
        }
        $headMetas = $this->xhtmlHeadMetasFromDom($dom);
        if ($headMetas !== []) {
            $metadata['headMetas'] = $headMetas;
        }
        $links = $this->xhtmlHeadLinksFromDom($dom, $resource_base_path);
        if ($links !== []) {
            $metadata['links'] = $links;
        }
        $headStyles = $this->xhtmlHeadStylesFromDom($dom, $resource_base_path);
        if ($headStyles !== []) {
            $metadata['headStyles'] = $headStyles;
        }
        $headScripts = $this->xhtmlHeadScriptsFromDom($dom, $resource_base_path);
        if ($headScripts !== []) {
            $metadata['headScripts'] = $headScripts;
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function xhtmlRootAttributesFromDom(\DOMDocument $dom): array
    {
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return [];
        }

        $attributes = [];
        foreach ([
            'id' => 'id',
            'role' => 'role',
            'title' => 'title',
            'ariaLabel' => 'aria-label',
            'prefix' => 'prefix',
        ] as $key => $attribute) {
            $value = trim($this->attributeByLocalName($root, $attribute));
            if ($value !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        $classes = $this->attributeTokenList($root, 'class');
        if ($classes !== []) {
            $attributes['classes'] = $classes;
        }
        $language = trim($root->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($root->getAttribute('lang'));
        }
        if ($this->validLanguageTag($language)) {
            $attributes['lang'] = $language;
        }
        $direction = strtolower(trim($root->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if ($root->hasAttribute('hidden')) {
            $attributes['hidden'] = true;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function xhtmlBodyAttributesFromDom(\DOMDocument $dom): array
    {
        $body = $this->xhtmlBodyElement($dom);
        if (!$body instanceof \DOMElement) {
            return [];
        }

        $attributes = [];
        foreach ([
            'id' => 'id',
            'role' => 'role',
            'title' => 'title',
            'ariaLabel' => 'aria-label',
        ] as $key => $attribute) {
            $value = trim($this->attributeByLocalName($body, $attribute));
            if ($value !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        $classes = $this->attributeTokenList($body, 'class');
        if ($classes !== []) {
            $attributes['classes'] = $classes;
        }
        $epubType = trim($this->epubTypeAttribute($body));
        if ($epubType !== '') {
            $attributes['epubType'] = html_entity_decode($epubType, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        $language = trim($body->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($body->getAttribute('lang'));
        }
        if ($this->validLanguageTag($language)) {
            $attributes['lang'] = $language;
        }
        $direction = strtolower(trim($body->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if ($body->hasAttribute('hidden')) {
            $attributes['hidden'] = true;
        }

        return $attributes;
    }

    private function xhtmlBodyElement(\DOMDocument $dom): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'body') {
                return $element;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xhtmlSemanticElementsFromDom(\DOMDocument $dom, string $base_path): array
    {
        $body = $this->xhtmlBodyElement($dom);
        if (!$body instanceof \DOMElement) {
            return [];
        }

        $elements = [];
        foreach ($body->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $record = $this->xhtmlSemanticElementRecord($element, $base_path);
            if ($record !== null) {
                $elements[] = $record;
            }
        }

        return $elements;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function xhtmlSemanticElementRecord(\DOMElement $element, string $base_path): ?array
    {
        $elementName = strtolower($element->localName);
        $recognizedElement = in_array($elementName, [
            'section',
            'article',
            'aside',
            'figure',
            'figcaption',
            'nav',
            'main',
            'header',
            'footer',
            'address',
            'hgroup',
            'menu',
            'search',
            'dialog',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'p',
            'blockquote',
            'pre',
            'ol',
            'ul',
            'li',
            'dl',
            'dt',
            'dd',
            'hr',
            'span',
            'div',
            'a',
            'img',
            'map',
            'area',
            'details',
            'summary',
            'picture',
            'audio',
            'video',
            'source',
            'track',
            'object',
            'param',
            'embed',
            'applet',
            'strong',
            'b',
            'em',
            'i',
            'code',
            'tt',
            'kbd',
            'mark',
            'dfn',
            'abbr',
            'cite',
            'q',
            'small',
            'sup',
            'sub',
            'samp',
            'var',
            'bdi',
            'bdo',
            'data',
            'u',
            's',
            'strike',
            'ins',
            'del',
            'meter',
            'output',
            'progress',
            'time',
            'ruby',
            'rt',
            'rp',
            'math',
            'svg',
            'style',
            'script',
            'noscript',
            'form',
            'label',
            'input',
            'button',
            'select',
            'option',
            'optgroup',
            'textarea',
            'fieldset',
            'legend',
            'canvas',
            'iframe',
            'trigger',
            'br',
            'wbr',
            'table',
            'caption',
            'colgroup',
            'col',
            'thead',
            'tbody',
            'tfoot',
            'tr',
            'th',
            'td',
        ], true);
        if (!$recognizedElement && !$this->xhtmlElementHasSemanticAttributes($element)) {
            return null;
        }

        $record = ['element' => $elementName];
        foreach ([
            'id' => 'id',
            'role' => 'role',
            'title' => 'title',
            'ariaLabel' => 'aria-label',
            'ariaLabelledby' => 'aria-labelledby',
            'ariaDescribedby' => 'aria-describedby',
            'ariaDetails' => 'aria-details',
            'ariaControls' => 'aria-controls',
            'ariaCurrent' => 'aria-current',
            'ariaHidden' => 'aria-hidden',
            'ariaExpanded' => 'aria-expanded',
            'ariaOwns' => 'aria-owns',
            'ariaFlowto' => 'aria-flowto',
            'ariaLive' => 'aria-live',
            'ariaAtomic' => 'aria-atomic',
            'ariaBusy' => 'aria-busy',
            'ariaDisabled' => 'aria-disabled',
            'ariaPressed' => 'aria-pressed',
            'ariaSelected' => 'aria-selected',
            'ariaRoleDescription' => 'aria-roledescription',
        ] as $key => $attribute) {
            $value = trim($this->attributeByLocalName($element, $attribute));
            if ($value !== '') {
                $record[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        $classes = $this->attributeTokenList($element, 'class');
        if ($classes !== []) {
            $record['classes'] = $classes;
        }
        $htmlAttributes = $this->xhtmlSemanticHtmlAttributes($element, $base_path);
        if ($htmlAttributes !== []) {
            $record['htmlAttributes'] = $htmlAttributes;
        }
        $epubType = trim($this->epubTypeAttribute($element));
        if ($epubType !== '') {
            $record['epubType'] = html_entity_decode($epubType, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($element->getAttribute('lang'));
        }
        if ($this->validLanguageTag($language)) {
            $record['lang'] = $language;
        }
        $direction = strtolower(trim($element->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $record['dir'] = $direction;
        }
        if ($element->hasAttribute('hidden')) {
            $record['hidden'] = true;
        }
        if ($elementName === 'details' && $element->hasAttribute('open')) {
            $record['open'] = true;
        }

        if ($elementName === 'img') {
            foreach ([
                'src' => 'src',
                'alt' => 'alt',
            ] as $key => $attribute) {
                $value = html_entity_decode(trim($element->getAttribute($attribute)), ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($value !== '') {
                    $record[$key] = $key === 'src' ? $this->rewriteRelativeResourceUrl($value, $base_path) : $value;
                }
            }
        }
        $this->addXhtmlSemanticElementSpecificAttributes($record, $element, $base_path);

        $text = $this->xhtmlSemanticElementText($element);
        if (
            count($record) === 1
            && !$this->xhtmlTextOnlySemanticElement($elementName, $text)
            && !$this->xhtmlEmptySemanticElement($elementName)
        ) {
            return null;
        }

        if ($elementName === 'a') {
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href !== '') {
                $record['href'] = $this->rewriteRelativeResourceUrl($href, $base_path);
            }
            foreach ([
                'rel' => 'rel',
                'hreflang' => 'hreflang',
                'type' => 'type',
                'media' => 'media',
                'download' => 'download',
            ] as $key => $attribute) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $record[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }

        if ($text !== '') {
            $record['text'] = $text;
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function addXhtmlSemanticElementSpecificAttributes(array &$record, \DOMElement $element, string $base_path): void
    {
        $elementName = strtolower($element->localName);
        foreach ($this->xhtmlSemanticResourceAttributes($elementName) as $key => $attribute) {
            $value = html_entity_decode(trim($this->attributeByLocalName($element, $attribute)), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($value !== '') {
                $record[$key] = $this->rewriteRelativeResourceUrl($value, $base_path);
            }
        }

        foreach ($this->xhtmlSemanticSrcsetAttributes($elementName) as $key => $attribute) {
            $value = html_entity_decode(trim($this->attributeByLocalName($element, $attribute)), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($value !== '') {
                $referencedResources = [];
                $record[$key] = $this->rewriteSrcsetValue($value, $base_path, $referencedResources);
            }
        }

        foreach ($this->xhtmlSemanticPlainAttributes($elementName) as $key => $attribute) {
            $value = trim($this->xhtmlSemanticPlainAttributeValue($element, $elementName, $attribute));
            if ($value !== '') {
                $record[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        foreach ($this->xhtmlSemanticPositiveIntegerAttributes($elementName) as $key => $attribute) {
            $value = trim($this->attributeByLocalName($element, $attribute));
            if ($value !== '' && ctype_digit($value) && (int) $value > 0) {
                $record[$key] = (int) $value;
            }
        }

        foreach ($this->xhtmlSemanticBooleanAttributes($elementName) as $key => $attribute) {
            if ($element->hasAttribute($attribute)) {
                $record[$key] = true;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function xhtmlSemanticHtmlAttributes(\DOMElement $element, string $base_path): array
    {
        $attributes = [];
        $style = html_entity_decode(trim($element->getAttribute('style')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($style !== '') {
            $referencedResources = [];
            $attributes['style'] = $this->rewriteCssResourceReferences($style, $base_path, $referencedResources);
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr || !str_starts_with(strtolower($attribute->name), 'data-')) {
                continue;
            }
            $attributes[strtolower($attribute->name)] = html_entity_decode(trim($attribute->value), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function xhtmlSemanticResourceAttributes(string $elementName): array
    {
        return match ($elementName) {
            'audio' => ['src' => 'src'],
            'video' => ['src' => 'src', 'poster' => 'poster'],
            'source' => ['src' => 'src'],
            'track' => ['src' => 'src'],
            'object' => ['data' => 'data'],
            'embed' => ['src' => 'src'],
            'iframe' => ['src' => 'src'],
            'script' => ['src' => 'src'],
            'form' => ['action' => 'action'],
            'input' => ['src' => 'src'],
            'button' => ['formAction' => 'formaction'],
            'area' => ['href' => 'href'],
            'table', 'tr', 'th', 'td' => ['background' => 'background'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function xhtmlSemanticSrcsetAttributes(string $elementName): array
    {
        return match ($elementName) {
            'img', 'source' => ['srcset' => 'srcset'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function xhtmlSemanticPlainAttributes(string $elementName): array
    {
        return match ($elementName) {
            'audio' => ['preload' => 'preload', 'crossorigin' => 'crossorigin'],
            'video' => ['preload' => 'preload', 'crossorigin' => 'crossorigin', 'width' => 'width', 'height' => 'height'],
            'source' => ['type' => 'type', 'media' => 'media', 'sizes' => 'sizes'],
            'track' => ['kind' => 'kind', 'srclang' => 'srclang', 'label' => 'label'],
            'object' => ['type' => 'type', 'name' => 'name', 'width' => 'width', 'height' => 'height'],
            'param' => ['name' => 'name', 'value' => 'value'],
            'embed' => ['type' => 'type', 'width' => 'width', 'height' => 'height'],
            'applet' => ['code' => 'code', 'archive' => 'archive', 'codebase' => 'codebase', 'name' => 'name', 'width' => 'width', 'height' => 'height', 'align' => 'align'],
            'img' => ['usemap' => 'usemap'],
            'svg' => ['viewBox' => 'viewBox', 'width' => 'width', 'height' => 'height', 'preserveAspectRatio' => 'preserveAspectRatio'],
            'style' => ['type' => 'type', 'media' => 'media', 'blocking' => 'blocking'],
            'script' => ['type' => 'type', 'charset' => 'charset', 'crossorigin' => 'crossorigin', 'integrity' => 'integrity', 'referrerpolicy' => 'referrerpolicy', 'nonce' => 'nonce'],
            'blockquote' => ['cite' => 'cite'],
            'ol' => ['start' => 'start', 'type' => 'type'],
            'ul' => ['type' => 'type'],
            'li' => ['value' => 'value'],
            'map' => ['name' => 'name'],
            'area' => ['alt' => 'alt', 'coords' => 'coords', 'shape' => 'shape', 'target' => 'target', 'download' => 'download', 'rel' => 'rel', 'hreflang' => 'hreflang', 'type' => 'type', 'media' => 'media'],
            'q' => ['cite' => 'cite'],
            'data' => ['value' => 'value'],
            'ins', 'del' => ['cite' => 'cite', 'datetime' => 'datetime'],
            'meter' => ['value' => 'value', 'min' => 'min', 'max' => 'max', 'low' => 'low', 'high' => 'high', 'optimum' => 'optimum'],
            'output' => ['for' => 'for', 'name' => 'name'],
            'progress' => ['value' => 'value', 'max' => 'max'],
            'time' => ['datetime' => 'datetime'],
            'math' => ['display' => 'display', 'alttext' => 'alttext'],
            'form' => ['method' => 'method', 'enctype' => 'enctype', 'target' => 'target', 'name' => 'name', 'acceptCharset' => 'accept-charset', 'autocomplete' => 'autocomplete'],
            'label' => ['for' => 'for', 'form' => 'form'],
            'input' => ['type' => 'type', 'name' => 'name', 'value' => 'value', 'alt' => 'alt', 'placeholder' => 'placeholder', 'form' => 'form', 'min' => 'min', 'max' => 'max', 'step' => 'step', 'pattern' => 'pattern', 'accept' => 'accept', 'autocomplete' => 'autocomplete', 'inputmode' => 'inputmode'],
            'button' => ['type' => 'type', 'name' => 'name', 'value' => 'value', 'form' => 'form', 'formMethod' => 'formmethod', 'formEnctype' => 'formenctype', 'formTarget' => 'formtarget'],
            'select' => ['name' => 'name', 'form' => 'form', 'size' => 'size', 'autocomplete' => 'autocomplete'],
            'option' => ['value' => 'value', 'label' => 'label'],
            'optgroup' => ['label' => 'label'],
            'textarea' => ['name' => 'name', 'placeholder' => 'placeholder', 'rows' => 'rows', 'cols' => 'cols', 'wrap' => 'wrap', 'form' => 'form', 'maxlength' => 'maxlength', 'minlength' => 'minlength', 'dirname' => 'dirname'],
            'fieldset' => ['name' => 'name', 'form' => 'form'],
            'canvas' => ['width' => 'width', 'height' => 'height'],
            'trigger' => ['observer' => 'observer', 'event' => 'event', 'action' => 'action', 'ref' => 'ref'],
            'table' => ['summary' => 'summary', 'align' => 'align', 'border' => 'border', 'cellpadding' => 'cellpadding', 'cellspacing' => 'cellspacing', 'width' => 'width'],
            'caption' => ['align' => 'align'],
            'colgroup', 'col' => ['align' => 'align', 'valign' => 'valign', 'width' => 'width'],
            'thead', 'tbody', 'tfoot', 'tr' => ['align' => 'align', 'valign' => 'valign'],
            'th', 'td' => ['headers' => 'headers', 'scope' => 'scope', 'abbr' => 'abbr', 'axis' => 'axis', 'align' => 'align', 'valign' => 'valign', 'width' => 'width', 'height' => 'height'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function xhtmlSemanticPositiveIntegerAttributes(string $elementName): array
    {
        return match ($elementName) {
            'colgroup', 'col' => ['span' => 'span'],
            'th', 'td' => ['colspan' => 'colspan', 'rowspan' => 'rowspan'],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function xhtmlSemanticBooleanAttributes(string $elementName): array
    {
        return match ($elementName) {
            'audio', 'video' => ['controls' => 'controls', 'autoplay' => 'autoplay', 'loop' => 'loop', 'muted' => 'muted'],
            'style' => ['scoped' => 'scoped'],
            'script' => ['async' => 'async', 'defer' => 'defer', 'nomodule' => 'nomodule'],
            'ol' => ['reversed' => 'reversed'],
            'track' => ['default' => 'default'],
            'form' => ['novalidate' => 'novalidate'],
            'input' => ['checked' => 'checked', 'disabled' => 'disabled', 'readonly' => 'readonly', 'required' => 'required', 'multiple' => 'multiple', 'autofocus' => 'autofocus'],
            'button' => ['disabled' => 'disabled', 'autofocus' => 'autofocus', 'formNoValidate' => 'formnovalidate'],
            'select' => ['multiple' => 'multiple', 'disabled' => 'disabled', 'required' => 'required', 'autofocus' => 'autofocus'],
            'option' => ['selected' => 'selected', 'disabled' => 'disabled'],
            'optgroup' => ['disabled' => 'disabled'],
            'textarea' => ['disabled' => 'disabled', 'readonly' => 'readonly', 'required' => 'required', 'autofocus' => 'autofocus'],
            'fieldset' => ['disabled' => 'disabled'],
            'dialog' => ['open' => 'open'],
            default => [],
        };
    }

    private function xhtmlSemanticPlainAttributeValue(\DOMElement $element, string $elementName, string $attribute): string
    {
        if ($elementName === 'trigger') {
            return $this->attributeByLocalName($element, $attribute);
        }

        return $element->getAttribute($attribute);
    }

    private function xhtmlTextOnlySemanticElement(string $elementName, string $text): bool
    {
        return $text !== '' && in_array($elementName, ['strong', 'b', 'em', 'i', 'code', 'tt', 'cite', 'q', 'small', 'sup', 'sub', 'samp', 'var', 'u', 'ins', 's', 'strike', 'del', 'applet', 'svg', 'style', 'script', 'noscript'], true);
    }

    private function xhtmlEmptySemanticElement(string $elementName): bool
    {
        return in_array($elementName, ['br', 'hr'], true);
    }

    private function xhtmlElementHasSemanticAttributes(\DOMElement $element): bool
    {
        if (
            trim($this->epubTypeAttribute($element)) !== ''
            || trim($this->attributeByLocalName($element, 'role')) !== ''
            || $this->xhtmlElementHasAriaAttributes($element)
            || $this->xhtmlElementHasStyleOrDataAttributes($element)
            || $element->hasAttribute('hidden')
        ) {
            return true;
        }

        $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($element->getAttribute('lang'));
        }
        if ($language !== '') {
            return true;
        }

        return trim($element->getAttribute('dir')) !== '';
    }

    private function xhtmlElementHasStyleOrDataAttributes(\DOMElement $element): bool
    {
        if (trim($element->getAttribute('style')) !== '') {
            return true;
        }

        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && str_starts_with(strtolower($attribute->name), 'data-')) {
                return true;
            }
        }

        return false;
    }

    private function epubTypeAttribute(\DOMElement $element): string
    {
        foreach ($element->attributes ?? [] as $attribute) {
            if (
                $attribute instanceof \DOMAttr
                && $attribute->localName === 'type'
                && (
                    $attribute->namespaceURI === 'http://www.idpf.org/2007/ops'
                    || $attribute->prefix === 'epub'
                    || strtolower($attribute->name) === 'epub:type'
                )
            ) {
                return $attribute->value;
            }
        }

        return $element->getAttribute('epub:type') ?: $element->getAttribute('type');
    }

    private function xhtmlElementHasAriaAttributes(\DOMElement $element): bool
    {
        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && str_starts_with(strtolower($attribute->name), 'aria-') && trim($attribute->value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function xhtmlSemanticElementText(\DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($element->textContent)) ?? trim($element->textContent);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($text, 'UTF-8') > 240 ? rtrim(mb_substr($text, 0, 237, 'UTF-8')) . '...' : $text;
        }

        return strlen($text) > 240 ? rtrim(substr($text, 0, 237)) . '...' : $text;
    }

    /**
     * Returns the direct XHTML head title, separate from document metadata titles.
     */
    private function xhtmlHeadTitleFromDom(\DOMDocument $dom): string
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'title') {
                continue;
            }
            $parent = $element->parentNode;
            if (!$parent instanceof \DOMElement || $parent->localName !== 'head') {
                continue;
            }

            return preg_replace('/\s+/u', ' ', trim($element->textContent)) ?? trim($element->textContent);
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xhtmlHeadBasesFromDom(\DOMDocument $dom, string $base_path): array
    {
        $bases = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'base') {
                continue;
            }
            $parent = $element->parentNode;
            if (!$parent instanceof \DOMElement || $parent->localName !== 'head') {
                continue;
            }

            $base = [];
            $id = html_entity_decode(trim($element->getAttribute('id')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($id !== '') {
                $base['id'] = $id;
            }
            $target = html_entity_decode(trim($element->getAttribute('target')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($target !== '') {
                $base['target'] = $target;
            }
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href !== '') {
                $base['href'] = $this->rewriteRelativeBaseHref($href, $base_path);
            }
            if ($base !== []) {
                $bases[] = $base;
            }
        }

        return $bases;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xhtmlHeadMetasFromDom(\DOMDocument $dom): array
    {
        $metas = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'meta') {
                continue;
            }
            $parent = $element->parentNode;
            if (!$parent instanceof \DOMElement || $parent->localName !== 'head') {
                continue;
            }
            if ($element->hasAttribute('charset')) {
                continue;
            }

            $meta = [];
            foreach ([
                'id' => 'id',
                'name' => 'name',
                'property' => 'property',
                'content' => 'content',
                'refines' => 'refines',
                'scheme' => 'scheme',
            ] as $key => $attribute) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $meta[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            $httpEquiv = trim($element->getAttribute('http-equiv'));
            if ($httpEquiv !== '') {
                $meta['httpEquiv'] = html_entity_decode($httpEquiv, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language === '') {
                $language = trim($element->getAttribute('lang'));
            }
            if ($language !== '') {
                $meta['lang'] = html_entity_decode($language, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $direction = strtolower(trim($element->getAttribute('dir')));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $meta['dir'] = $direction;
            }
            if (strtolower((string) ($meta['name'] ?? '')) === 'viewport') {
                continue;
            }
            if (!$this->hasSemanticHeadMetaKey($meta)) {
                continue;
            }

            $metas[] = $meta;
        }

        return $metas;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function hasSemanticHeadMetaKey(array $meta): bool
    {
        foreach (['name', 'property', 'httpEquiv'] as $key) {
            if (isset($meta[$key]) && is_string($meta[$key]) && trim($meta[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xhtmlHeadStylesFromDom(\DOMDocument $dom, string $base_path): array
    {
        $styles = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'style') {
                continue;
            }
            $parent = $element->parentNode;
            if (!$parent instanceof \DOMElement || $parent->localName !== 'head') {
                continue;
            }
            $css = trim($element->textContent);
            if ($css === '') {
                continue;
            }

            $referencedResources = [];
            $style = ['css' => $this->rewriteCssResourceReferences($css, $base_path, $referencedResources)];
            foreach ([
                'id' => 'id',
                'type' => 'type',
                'media' => 'media',
                'title' => 'title',
            ] as $key => $attribute) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $style[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language === '') {
                $language = trim($element->getAttribute('lang'));
            }
            if ($language !== '') {
                $style['lang'] = html_entity_decode($language, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $direction = strtolower(trim($element->getAttribute('dir')));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $style['dir'] = $direction;
            }

            $styles[] = $style;
        }

        return $styles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xhtmlHeadLinksFromDom(\DOMDocument $dom, string $base_path): array
    {
        $links = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'link') {
                continue;
            }
            $parent = $element->parentNode;
            if (!$parent instanceof \DOMElement || $parent->localName !== 'head') {
                continue;
            }
            $href = html_entity_decode(trim($element->getAttribute('href')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href === '') {
                continue;
            }

            $link = [
                'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
            ];
            foreach ([
                'id' => 'id',
                'rel' => 'rel',
                'type' => 'type',
                'media' => 'media',
                'title' => 'title',
                'hreflang' => 'hreflang',
                'as' => 'as',
                'sizes' => 'sizes',
                'crossorigin' => 'crossorigin',
                'integrity' => 'integrity',
                'referrerpolicy' => 'referrerpolicy',
                'fetchpriority' => 'fetchpriority',
                'blocking' => 'blocking',
                'color' => 'color',
                'imagesizes' => 'imagesizes',
            ] as $key => $attribute) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $link[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
            $imagesrcset = trim($element->getAttribute('imagesrcset'));
            if ($imagesrcset !== '') {
                $referencedResources = [];
                $link['imagesrcset'] = $this->rewriteSrcsetValue(
                    html_entity_decode($imagesrcset, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                    $base_path,
                    $referencedResources
                );
            }
            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language === '') {
                $language = trim($element->getAttribute('lang'));
            }
            if ($language !== '') {
                $link['lang'] = html_entity_decode($language, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            $direction = strtolower(trim($element->getAttribute('dir')));
            if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
                $link['dir'] = $direction;
            }
            $properties = array_values(array_filter(
                preg_split('/\s+/', trim($element->getAttribute('properties'))) ?: [],
                static fn (string $property): bool => $property !== ''
            ));
            if ($properties !== []) {
                $link['properties'] = $properties;
            }
            if ($element->hasAttribute('disabled')) {
                $link['disabled'] = true;
            }

            $links[] = $link;
        }

        return $links;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xhtmlHeadScriptsFromDom(\DOMDocument $dom, string $base_path): array
    {
        $scripts = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'script') {
                continue;
            }
            $parent = $element->parentNode;
            if (!$parent instanceof \DOMElement || $parent->localName !== 'head') {
                continue;
            }

            $script = [];
            foreach ([
                'id' => 'id',
                'type' => 'type',
                'charset' => 'charset',
                'crossorigin' => 'crossorigin',
                'integrity' => 'integrity',
                'referrerpolicy' => 'referrerpolicy',
                'nonce' => 'nonce',
            ] as $key => $attribute) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $script[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }

            $src = html_entity_decode(trim($element->getAttribute('src')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($src !== '') {
                $script['src'] = $this->rewriteRelativeResourceUrl($src, $base_path);
            }
            foreach (['async', 'defer', 'nomodule'] as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    $script[$attribute] = true;
                }
            }

            $body = trim($element->textContent);
            if ($body !== '') {
                $script['script'] = html_entity_decode($body, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
            if (isset($script['src']) || isset($script['script'])) {
                $scripts[] = $script;
            }
        }

        return $scripts;
    }

    /**
     * @return array{width: int, height: int, content: string, properties?: array<string, string>}|null
     */
    private function xhtmlViewportFromDom(\DOMDocument $dom): ?array
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'meta') {
                continue;
            }
            if (strtolower(trim($element->getAttribute('name'))) !== 'viewport') {
                continue;
            }
            $viewport = $this->parseViewportContent($element->getAttribute('content'));
            if ($viewport !== null) {
                return $viewport;
            }
        }

        return null;
    }

    private function validLanguageTag(string $language): bool
    {
        return preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/', $language) === 1;
    }

    /**
     * @return array{width: int, height: int, content: string, properties?: array<string, string>}|null
     */
    private function parseViewportContent(string $content): ?array
    {
        $content = html_entity_decode(trim($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $width = null;
        $height = null;
        $extraOrder = [];
        $extraParts = [];
        $properties = [];
        foreach (preg_split('/[,;]/', $content) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^\s*(width|height)\s*=\s*([0-9]+(?:\.[0-9]+)?)\s*$/i', $part, $match) === 1) {
                $number = $this->positiveViewportInteger($match[2]);
                if ($number === null) {
                    continue;
                }
                if (strtolower($match[1]) === 'width') {
                    $width = $number;
                } else {
                    $height = $number;
                }
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\s*=\s*([^,;]+)$/', $part, $match) !== 1) {
                continue;
            }
            $key = strtolower($match[1]);
            if ($key === 'width' || $key === 'height') {
                continue;
            }
            $value = $this->viewportPropertyValue($match[2]);
            if ($value === null) {
                continue;
            }
            if (!isset($properties[$key])) {
                $extraOrder[] = $key;
            }
            $properties[$key] = $value;
            $extraParts[$key] = $key . '=' . $value;
        }
        if ($width === null || $height === null) {
            return null;
        }

        $viewport = [
            'width' => $width,
            'height' => $height,
            'content' => 'width=' . $width . ', height=' . $height,
        ];
        $orderedExtraParts = [];
        foreach ($extraOrder as $key) {
            if (isset($extraParts[$key])) {
                $orderedExtraParts[] = $extraParts[$key];
            }
        }
        if ($orderedExtraParts !== []) {
            $viewport['content'] .= ', ' . implode(', ', $orderedExtraParts);
            $viewport['properties'] = $properties;
        }

        return $viewport;
    }

    private function viewportPropertyValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '' || strlen($text) > 80 || strpbrk($text, "<>\"'") !== false) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $text) === 1) {
            return null;
        }

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    private function positiveViewportInteger(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $text) !== 1) {
            return null;
        }

        $number = (int) round((float) $text);

        return $number > 0 ? $number : null;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array{
     *     resources: list<string>,
     *     toc: list<array{text: string, level: int, href?: string, type?: string}>,
     *     tocReadingOrderGroups: list<list<array{text: string, level: int, href?: string, type?: string}>>,
     *     landmarkTargetGroups: list<list<array{text: string, level: int, href?: string, type?: string}>>,
     *     pageListTargetGroups: list<list<array{text: string, level: int, href?: string, type?: string, value?: string}>>,
     *     landmarkNavCount: int,
     *     landmarks: list<array{text: string, level: int, href?: string, type?: string}>,
     *     pageList: list<array{text: string, level: int, href?: string, type?: string, value?: string}>,
     *     ncxNavLists: list<array<string, mixed>>,
     *     auxiliaryNavSections: list<array{type: string, title?: string, attributes?: array<string, mixed>, entries: list<array<string, mixed>>}>,
     *     rootAttributes: array<string, mixed>,
     *     bodyAttributes: array<string, mixed>,
     *     tocNavAttributes: array<string, mixed>,
     *     landmarkNavAttributes: array<string, mixed>,
     *     pageListNavAttributes: array<string, mixed>,
     *     tocNavTitle: string,
     *     landmarkNavTitle: string,
     *     pageListNavTitle: string
     * }
     */
    private function navigation(\ZipArchive $zip, string $base_path, array $manifest, string $spine_toc_id): array
    {
        $resources = [];
        $toc_entries = [];
        $toc_entry_groups = [];
        $landmark_target_groups = [];
        $page_list_target_groups = [];
        $landmark_entries = [];
        $page_list_entries = [];
        $ncx_nav_lists = [];
        $auxiliary_nav_sections = [];
        $ncx_metadata = [];
        $toc_nav_attributes = [];
        $landmark_nav_attributes = [];
        $page_list_nav_attributes = [];
        $landmark_nav_count = 0;
        $toc_nav_title = '';
        $landmark_nav_title = '';
        $page_list_nav_title = '';
        $root_attributes = [];
        $body_attributes = [];
        foreach ($manifest as $id => $item) {
            $href = $this->packageResourceZipPath($base_path, $item['href']);
            if ($href === '') {
                continue;
            }
            $media_type = strtolower($item['media-type']);
            $properties = array_map('strtolower', $item['properties']);
            $is_nav = in_array('nav', $properties, true);
            $is_ncx = str_contains($media_type, 'x-dtbncx')
                || str_ends_with(strtolower($href), '.ncx')
                || ($id === $spine_toc_id && !$is_nav);
            if (!$is_nav && !$is_ncx) {
                continue;
            }

            $xml = $zip->getFromName($href);
            if (!is_string($xml)) {
                continue;
            }
            $resources[] = $href;
            try {
                if ($is_ncx) {
                    $parsed_ncx_metadata = $this->ncxMetadata($xml, $href);
                    if ($parsed_ncx_metadata !== []) {
                        $ncx_metadata[] = $parsed_ncx_metadata;
                    }
                    $ncx_toc_entries = $this->ncxTocEntries($xml, $this->dirname($href));
                    array_push($toc_entries, ...$ncx_toc_entries);
                    if ($ncx_toc_entries !== []) {
                        $toc_entry_groups[] = $ncx_toc_entries;
                    }
                    array_push($page_list_entries, ...$this->ncxPageListEntries($xml, $this->dirname($href)));
                    array_push($ncx_nav_lists, ...$this->ncxNavLists($xml, $this->dirname($href)));
                } else {
                    try {
                        $parsed = $this->xhtmlNavigationEntries($xml, $this->dirname($href));
                    } catch (\Throwable) {
                        continue;
                    }
                    array_push($toc_entries, ...$parsed['toc']);
                    if ($parsed['toc'] !== []) {
                        $toc_entry_groups[] = $parsed['toc'];
                    }
                    array_push($landmark_entries, ...$parsed['landmarks']);
                    array_push($page_list_entries, ...$parsed['pageList']);
                    $landmark_nav_count += (int) ($parsed['landmarkNavCount'] ?? 0);
                    if ($parsed['landmarks'] !== []) {
                        $landmark_target_groups[] = $parsed['landmarks'];
                    }
                    if ($parsed['pageList'] !== []) {
                        $page_list_target_groups[] = $parsed['pageList'];
                    }
                    array_push($auxiliary_nav_sections, ...$parsed['auxiliaryNavSections']);
                    if ($root_attributes === [] && $parsed['rootAttributes'] !== []) {
                        $root_attributes = $parsed['rootAttributes'];
                    }
                    if ($body_attributes === [] && $parsed['bodyAttributes'] !== []) {
                        $body_attributes = $parsed['bodyAttributes'];
                    }
                    if ($toc_nav_attributes === [] && $parsed['tocNavAttributes'] !== []) {
                        $toc_nav_attributes = $parsed['tocNavAttributes'];
                    }
                    if ($landmark_nav_attributes === [] && $parsed['landmarkNavAttributes'] !== []) {
                        $landmark_nav_attributes = $parsed['landmarkNavAttributes'];
                    }
                    if ($page_list_nav_attributes === [] && $parsed['pageListNavAttributes'] !== []) {
                        $page_list_nav_attributes = $parsed['pageListNavAttributes'];
                    }
                    if ($toc_nav_title === '' && $parsed['tocNavTitle'] !== '') {
                        $toc_nav_title = $parsed['tocNavTitle'];
                    }
                    if ($landmark_nav_title === '' && $parsed['landmarkNavTitle'] !== '') {
                        $landmark_nav_title = $parsed['landmarkNavTitle'];
                    }
                    if ($page_list_nav_title === '' && $parsed['pageListNavTitle'] !== '') {
                        $page_list_nav_title = $parsed['pageListNavTitle'];
                    }
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        $toc_reading_order_groups = [];
        foreach ($toc_entry_groups as $toc_entry_group) {
            $unique_group = $this->uniqueNavigationEntries($toc_entry_group);
            if ($unique_group !== []) {
                $toc_reading_order_groups[] = $unique_group;
            }
        }
        $landmark_target_groups = $this->uniqueNavigationEntryGroups($landmark_target_groups);
        $page_list_target_groups = $this->uniqueNavigationEntryGroups($page_list_target_groups);

        return [
            'resources' => array_values(array_unique($resources)),
            'toc' => $this->uniqueNavigationEntries($toc_entries),
            'tocReadingOrderGroups' => $toc_reading_order_groups,
            'landmarkTargetGroups' => $landmark_target_groups,
            'pageListTargetGroups' => $page_list_target_groups,
            'landmarkNavCount' => $landmark_nav_count,
            'landmarks' => $this->uniqueNavigationEntries($landmark_entries),
            'pageList' => $this->uniquePageListEntries($page_list_entries),
            'ncxNavLists' => $ncx_nav_lists,
            'auxiliaryNavSections' => $this->uniqueAuxiliaryNavSections($auxiliary_nav_sections),
            'ncxMetadata' => $ncx_metadata,
            'rootAttributes' => $root_attributes,
            'bodyAttributes' => $body_attributes,
            'tocNavAttributes' => $toc_nav_attributes,
            'landmarkNavAttributes' => $landmark_nav_attributes,
            'pageListNavAttributes' => $page_list_nav_attributes,
            'tocNavTitle' => $this->defaultedNavTitle($toc_nav_title, 'Table of Contents'),
            'landmarkNavTitle' => $this->defaultedNavTitle($landmark_nav_title, 'Landmarks'),
            'pageListNavTitle' => $this->defaultedNavTitle($page_list_nav_title, 'Page List'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $tocEntries
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function navTocReadingOrderDiagnostics(array $tocEntries, array $spineItems, array $manifest, string $base_path): array
    {
        if ($tocEntries === [] || $spineItems === []) {
            return [];
        }

        $targetMaps = $this->navigationReadingOrderTargetMaps($spineItems, $manifest, $base_path);
        $linearPaths = $targetMaps['linearPaths'];
        $nonLinearPaths = $targetMaps['nonLinearPaths'];
        $manifestPaths = $targetMaps['manifestPaths'];

        $diagnostics = [];
        $previousOrder = null;
        $previousContext = null;
        foreach ($tocEntries as $entry) {
            $href = (string) ($entry['href'] ?? '');
            if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
                continue;
            }
            if ($this->navLinkHrefPathDiagnosticReason($href) !== '') {
                continue;
            }

            [$targetPath, $suffix] = $this->navigationTargetPath($href);
            if ($targetPath === '') {
                continue;
            }

            $context = $this->navTocEntryDiagnosticContext($entry, $href, $targetPath, $suffix);
            if (!isset($linearPaths[$targetPath])) {
                if (isset($nonLinearPaths[$targetPath])) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'nav-toc-target-non-linear-spine',
                        'EPUB navigation TOC entry targets a non-linear spine resource.',
                        $context + ['idref' => $nonLinearPaths[$targetPath]]
                    );
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-nav-toc-spine-target',
                    'EPUB navigation TOC entry does not target a linear spine resource.',
                    $context + (isset($manifestPaths[$targetPath]) ? ['manifestId' => $manifestPaths[$targetPath]] : [])
                );
                continue;
            }

            $current = $linearPaths[$targetPath];
            $currentOrder = (int) $current['order'];
            if ($previousOrder !== null && $currentOrder < $previousOrder) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'out-of-order-nav-toc-entry',
                    'EPUB navigation TOC entry appears before a previous TOC target in the linear spine reading order.',
                    $context + [
                        'idref' => $current['idref'],
                        'spineOrder' => $currentOrder,
                        'previousHref' => $previousContext['href'] ?? null,
                        'previousTargetPath' => $previousContext['targetPath'] ?? null,
                        'previousIdref' => $previousContext['idref'] ?? null,
                        'previousSpineOrder' => $previousOrder,
                    ]
                );
                continue;
            }

            $previousOrder = $currentOrder;
            $previousContext = [
                'href' => $href,
                'targetPath' => $targetPath,
                'idref' => $current['idref'],
            ];
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $pageListEntries
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function navPageListReadingOrderDiagnostics(\ZipArchive $zip, array $pageListEntries, array $spineItems, array $manifest, string $base_path): array
    {
        if ($pageListEntries === [] || $spineItems === []) {
            return [];
        }

        $targetMaps = $this->navigationReadingOrderTargetMaps($spineItems, $manifest, $base_path);
        $linearPaths = $targetMaps['linearPaths'];
        $diagnostics = [];
        $targetDocuments = [];
        $previousOrder = null;
        $previousElementOrder = null;
        $previousContext = null;

        foreach ($pageListEntries as $entry) {
            $href = (string) ($entry['href'] ?? '');
            if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
                continue;
            }
            if ($this->navLinkHrefPathDiagnosticReason($href) !== '') {
                continue;
            }

            [$targetPath, $suffix] = $this->navigationTargetPath($href);
            if ($targetPath === '' || !isset($linearPaths[$targetPath])) {
                continue;
            }

            $current = $linearPaths[$targetPath];
            $currentOrder = (int) $current['order'];
            $fragment = $this->urlFragmentIdentifier($href);
            $elementOrder = null;
            if ($fragment !== '') {
                if (!array_key_exists($targetPath, $targetDocuments)) {
                    $targetXml = $zip->getFromName($targetPath);
                    $targetDocuments[$targetPath] = null;
                    if (is_string($targetXml)) {
                        try {
                            $targetDocuments[$targetPath] = $this->loadXml($targetXml, 'EPUB page-list target');
                        } catch (\Throwable) {
                            $targetDocuments[$targetPath] = null;
                        }
                    }
                }
                $targetDocument = $targetDocuments[$targetPath];
                if ($targetDocument instanceof \DOMDocument) {
                    $elementOrder = $this->xmlDocumentElementOrder($targetDocument, $fragment);
                }
            }

            $context = $this->navTocEntryDiagnosticContext($entry, $href, $targetPath, $suffix) + [
                'idref' => $current['idref'],
                'spineOrder' => $currentOrder,
            ];
            if ($fragment !== '') {
                $context['fragment'] = $fragment;
            }
            if ($elementOrder !== null) {
                $context['targetElementOrder'] = $elementOrder;
            }

            $sameSpineTarget = is_array($previousContext) && ($previousContext['targetPath'] ?? null) === $targetPath;
            $outOfOrder = $previousOrder !== null && (
                $currentOrder < $previousOrder
                || ($currentOrder === $previousOrder && $sameSpineTarget && $elementOrder !== null && $previousElementOrder !== null && $elementOrder < $previousElementOrder)
            );
            if ($outOfOrder) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'out-of-order-nav-page-list-entry',
                    'EPUB page-list navigation entry appears before a previous page-list target in the linear spine reading order.',
                    $context + [
                        'previousHref' => $previousContext['href'] ?? null,
                        'previousTargetPath' => $previousContext['targetPath'] ?? null,
                        'previousFragment' => $previousContext['fragment'] ?? null,
                        'previousIdref' => $previousContext['idref'] ?? null,
                        'previousSpineOrder' => $previousOrder,
                        'previousTargetElementOrder' => $previousElementOrder,
                    ]
                );
                continue;
            }

            $previousOrder = $currentOrder;
            $previousElementOrder = $elementOrder;
            $previousContext = [
                'href' => $href,
                'targetPath' => $targetPath,
                'fragment' => $fragment !== '' ? $fragment : null,
                'idref' => $current['idref'],
            ];
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return list<array<string, mixed>>
     */
    private function xhtmlNavigationTargetDiagnostics(array $entries, string $navType, array $spineItems, array $manifest, string $base_path): array
    {
        if ($entries === [] || $spineItems === []) {
            return [];
        }

        $targetMaps = $this->navigationReadingOrderTargetMaps($spineItems, $manifest, $base_path);
        $linearPaths = $targetMaps['linearPaths'];
        $nonLinearPaths = $targetMaps['nonLinearPaths'];
        $manifestPaths = $targetMaps['manifestPaths'];
        $diagnostics = [];
        foreach ($entries as $entry) {
            $href = (string) ($entry['href'] ?? '');
            if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
                continue;
            }
            if ($this->navLinkHrefPathDiagnosticReason($href) !== '') {
                continue;
            }

            [$targetPath, $suffix] = $this->navigationTargetPath($href);
            if ($targetPath === '' || isset($linearPaths[$targetPath])) {
                continue;
            }

            $context = $this->navTocEntryDiagnosticContext($entry, $href, $targetPath, $suffix) + ['navType' => $navType];
            if (isset($nonLinearPaths[$targetPath])) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    $navType === 'page-list' ? 'nav-page-list-target-non-linear-spine' : 'nav-landmark-target-non-linear-spine',
                    'EPUB navigation entry targets a non-linear spine resource.',
                    $context + ['idref' => $nonLinearPaths[$targetPath]]
                );
                continue;
            }

            if (!isset($manifestPaths[$targetPath])) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                $navType === 'page-list' ? 'missing-nav-page-list-spine-target' : 'missing-nav-landmark-spine-target',
                'EPUB navigation entry does not target a linear spine resource.',
                $context + ['manifestId' => $manifestPaths[$targetPath]]
            );
        }

        return $diagnostics;
    }

    /**
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array{
     *     linearPaths: array<string, array{order: int, idref: string, path: string, fallbackIdref?: string}>,
     *     nonLinearPaths: array<string, string>,
     *     manifestPaths: array<string, string>
     * }
     */
    private function navigationReadingOrderTargetMaps(array $spineItems, array $manifest, string $base_path): array
    {
        $linearPaths = [];
        $nonLinearPaths = [];
        $manifestPaths = [];
        $order = 0;
        foreach ($spineItems as $spineItem) {
            $idref = $spineItem['idref'];
            if (!isset($manifest[$idref])) {
                continue;
            }

            $path = $this->packageResourceZipPath($base_path, $manifest[$idref]['href']);
            if ($path === '') {
                continue;
            }

            $manifestPaths[$path] = $idref;
            if (!$spineItem['linear']) {
                $nonLinearPaths[$path] = $idref;
                $readable = $this->readableSpineManifestItem($manifest, $idref, $base_path);
                if ($readable !== null && $readable['path'] !== $path) {
                    $nonLinearPaths[$readable['path']] = $idref;
                }
                continue;
            }

            $linearPaths[$path] = [
                'order' => $order,
                'idref' => $idref,
                'path' => $path,
            ];

            $readable = $this->readableSpineManifestItem($manifest, $idref, $base_path);
            if ($readable !== null && $readable['path'] !== $path) {
                $linearPaths[$readable['path']] = [
                    'order' => $order,
                    'idref' => $idref,
                    'path' => $readable['path'],
                    'fallbackIdref' => $readable['idref'],
                ];
            }

            $order++;
        }

        foreach ($manifest as $id => $item) {
            $path = $this->packageResourceZipPath($base_path, $item['href']);
            if ($path !== '') {
                $manifestPaths[$path] = $id;
            }
        }

        return [
            'linearPaths' => $linearPaths,
            'nonLinearPaths' => $nonLinearPaths,
            'manifestPaths' => $manifestPaths,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function navTocEntryDiagnosticContext(array $entry, string $href, string $targetPath, string $suffix): array
    {
        $context = [
            'href' => $href,
            'targetPath' => $targetPath,
        ];
        if ($suffix !== '') {
            $context['targetSuffix'] = $suffix;
        }
        foreach (['text', 'level', 'type', 'value'] as $key) {
            if (isset($entry[$key])) {
                $context[$key] = $entry[$key];
            }
        }

        return $context;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function navigationTargetPath(string $href): array
    {
        [$targetPath, $suffix] = $this->splitUrlPathSuffix($href);

        return [
            $this->normalizeZipPath($this->decodeUrlPathPercentEscapes($targetPath)),
            $suffix,
        ];
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @return list<array<string, mixed>>
     */
    private function ncxNavigationDiagnostics(\ZipArchive $zip, string $base_path, array $manifest, string $spine_toc_id, array $spineItems): array
    {
        $diagnostics = [];
        $targetMaps = $this->navigationReadingOrderTargetMaps($spineItems, $manifest, $base_path);
        foreach ($manifest as $id => $item) {
            $href = $this->normalizeZipPath($base_path . '/' . $item['href']);
            $media_type = strtolower($item['media-type']);
            $properties = array_map('strtolower', $item['properties']);
            $is_nav = in_array('nav', $properties, true);
            $is_ncx = str_contains($media_type, 'x-dtbncx')
                || str_ends_with(strtolower($href), '.ncx')
                || ($id === $spine_toc_id && !$is_nav);
            if (!$is_ncx) {
                continue;
            }

            $xml = $zip->getFromName($href);
            if (!is_string($xml)) {
                continue;
            }

            try {
                $dom = $this->loadXml($xml, 'EPUB NCX table of contents');
            } catch (\InvalidArgumentException) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'malformed-ncx-document',
                    'EPUB NCX table of contents is not well-formed XML.',
                    ['id' => $id, 'href' => $item['href'], 'path' => $href]
                );
                continue;
            }

            array_push(
                $diagnostics,
                ...$this->ncxDocumentStructureDiagnostics($dom, $href),
                ...$this->ncxDocumentNavigationDiagnostics($dom, $href, $targetMaps)
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxDocumentStructureDiagnostics(\DOMDocument $dom, string $path): array
    {
        $diagnostics = [];
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'ncx') {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-root',
                    'EPUB NCX table of contents must use an ncx root element.',
                    [
                        'path' => $path,
                        'element' => $root instanceof \DOMElement ? $root->localName : '',
                    ]
                ),
            ];
        }

        if (($root->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-ncx-root-namespace',
                'EPUB NCX root element must use the DAISY NCX namespace.',
                [
                    'path' => $path,
                    'element' => $root->localName,
                    'namespace' => $root->namespaceURI ?? '',
                    'expectedNamespace' => self::NCX_NAMESPACE,
                ]
            );
        }

        array_push(
            $diagnostics,
            ...$this->ncxRootAttributeDiagnostics($root, $path),
            ...$this->ncxRootChildNamespaceDiagnostics($root, $path),
            ...$this->ncxIdDiagnostics($root, $path),
            ...$this->ncxUnexpectedChildElementDiagnostics($root, $path),
            ...$this->ncxNavigationChildOrderDiagnostics($root, $path),
            ...$this->ncxDocumentMetadataNamespaceDiagnostics($root, $path),
            ...$this->ncxDocumentLabelLanguageDiagnostics($root, $path),
            ...$this->ncxDuplicateRootChildDiagnostics($root, $path),
            ...$this->ncxChildOrderDiagnostics($root, $path)
        );

        $head = $this->firstChildElement($root, 'head');
        if (!$head instanceof \DOMElement) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-ncx-head',
                'EPUB NCX table of contents is missing the required head element.',
                ['path' => $path]
            );
        } elseif ($this->ncxHeadMetaContent($head, 'dtb:uid') === '') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-ncx-uid',
                'EPUB NCX head is missing the required dtb:uid metadata.',
                ['path' => $path]
            );
        }
        if ($head instanceof \DOMElement) {
            array_push($diagnostics, ...$this->ncxHeadNumericMetadataDiagnostics($head, $path));
        }

        $docTitle = $this->firstChildElement($root, 'docTitle');
        if (!$docTitle instanceof \DOMElement) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-ncx-doc-title',
                'EPUB NCX table of contents is missing the required docTitle element.',
                ['path' => $path]
            );
        } elseif ($this->ncxLabelText($docTitle) === '') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'empty-ncx-doc-title',
                'EPUB NCX docTitle must contain text.',
                ['path' => $path]
            );
        }

        $navMap = $this->firstChildElement($root, 'navMap');
        if (!$navMap instanceof \DOMElement) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-ncx-nav-map',
                'EPUB NCX table of contents is missing the required navMap element.',
                ['path' => $path]
            );
        } elseif (!$this->firstChildElement($navMap, 'navPoint') instanceof \DOMElement) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'empty-ncx-nav-map',
                'EPUB NCX navMap must contain at least one navPoint element.',
                ['path' => $path, 'element' => 'navMap']
            );
        }

        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'pageList' && !$this->firstChildElement($child, 'pageTarget') instanceof \DOMElement) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'empty-ncx-page-list',
                    'EPUB NCX pageList must contain at least one pageTarget element.',
                    ['path' => $path, 'element' => 'pageList']
                );
            }
            if ($child->localName === 'navList' && !$this->firstChildElement($child, 'navTarget') instanceof \DOMElement) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'empty-ncx-nav-list',
                    'EPUB NCX navList must contain at least one navTarget element.',
                    $this->ncxNavigationContainerContext($child, $path)
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxRootAttributeDiagnostics(\DOMElement $root, string $path): array
    {
        if (($root->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $diagnostics = [];
        $version = trim($root->getAttribute('version'));
        if ($version === '') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'missing-ncx-version',
                'EPUB NCX root element must declare version 2005-1.',
                [
                    'path' => $path,
                    'element' => $root->localName,
                    'expectedVersion' => '2005-1',
                ]
            );
        } elseif ($version !== '2005-1') {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-ncx-version',
                'EPUB NCX root version must be 2005-1.',
                [
                    'path' => $path,
                    'element' => $root->localName,
                    'version' => $version,
                    'expectedVersion' => '2005-1',
                ]
            );
        }

        foreach ($this->ncxInvalidLanguageAttributes($root) as $attribute => $language) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-ncx-root-language',
                'EPUB NCX root language attributes must be valid language tags.',
                [
                    'path' => $path,
                    'element' => $root->localName,
                    'attribute' => $attribute,
                    'language' => $language,
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxRootChildNamespaceDiagnostics(\DOMElement $root, string $path): array
    {
        $diagnostics = [];
        $rootChildren = array_flip(['head', 'docTitle', 'docAuthor', 'navMap', 'pageList', 'navList']);
        $position = 0;

        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $position++;
            if (!isset($rootChildren[$child->localName]) || ($child->namespaceURI ?? '') === self::NCX_NAMESPACE) {
                continue;
            }

            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-ncx-child-namespace',
                'EPUB NCX root child elements must use the DAISY NCX namespace.',
                $this->ncxRootChildContext($child, $path, $position) + [
                    'namespace' => $child->namespaceURI ?? '',
                    'expectedNamespace' => self::NCX_NAMESPACE,
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxIdDiagnostics(\DOMElement $root, string $path): array
    {
        if (($root->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $diagnostics = [];
        $seen = [];
        foreach (array_merge([$root], iterator_to_array($root->getElementsByTagName('*'))) as $element) {
            if (!$element instanceof \DOMElement || ($element->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                continue;
            }

            $id = trim($element->getAttribute('id'));
            if ($id === '') {
                continue;
            }

            $context = $this->ncxElementIdContext($element, $path, $id);
            if (!$this->validXmlId($id)) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-id',
                    'EPUB NCX id attributes must be XML NCNames.',
                    $context
                );
                continue;
            }

            if (isset($seen[$id])) {
                $previousContext = $seen[$id];
                $duplicateContext = $context + [
                    'previousElement' => $previousContext['element'],
                ];
                foreach (['parent', 'type', 'text'] as $key) {
                    if (isset($previousContext[$key])) {
                        $duplicateContext['previous' . ucfirst($key)] = $previousContext[$key];
                    }
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-ncx-id',
                    'EPUB NCX id attributes must be unique within the NCX document.',
                    $duplicateContext
                );
                continue;
            }

            $seen[$id] = $context;
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxUnexpectedChildElementDiagnostics(\DOMElement $root, string $path): array
    {
        if (($root->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $allowedChildren = [
            'ncx' => ['head', 'docTitle', 'docAuthor', 'navMap', 'pageList', 'navList'],
            'head' => ['meta'],
            'docTitle' => ['text', 'audio', 'img'],
            'docAuthor' => ['text', 'audio', 'img'],
            'navMap' => ['navInfo', 'navLabel', 'navPoint'],
            'pageList' => ['navInfo', 'navLabel', 'pageTarget'],
            'navList' => ['navInfo', 'navLabel', 'navTarget'],
            'navPoint' => ['navLabel', 'content', 'navPoint'],
            'pageTarget' => ['navLabel', 'content'],
            'navTarget' => ['navLabel', 'content'],
            'navInfo' => ['text', 'audio', 'img'],
            'navLabel' => ['text', 'audio', 'img'],
            'text' => [],
            'audio' => [],
            'img' => [],
            'content' => [],
            'meta' => [],
        ];

        $diagnostics = [];
        foreach (array_merge([$root], iterator_to_array($root->getElementsByTagName('*'))) as $parent) {
            if (!$parent instanceof \DOMElement || ($parent->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                continue;
            }
            if (!array_key_exists($parent->localName, $allowedChildren)) {
                continue;
            }

            $allowed = array_flip($allowedChildren[$parent->localName]);
            foreach ($parent->childNodes as $child) {
                if (!$child instanceof \DOMElement || ($child->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                    continue;
                }
                if (isset($allowed[$child->localName])) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-child-element',
                    'EPUB NCX elements must only contain children allowed by the NCX content model.',
                    $this->ncxUnexpectedChildContext($parent, $child, $path)
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxNavigationChildOrderDiagnostics(\DOMElement $root, string $path): array
    {
        if (($root->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $models = [
            'navMap' => ['navInfo' => 0, 'navLabel' => 1, 'navPoint' => 2],
            'pageList' => ['navInfo' => 0, 'navLabel' => 1, 'pageTarget' => 2],
            'navList' => ['navInfo' => 0, 'navLabel' => 1, 'navTarget' => 2],
            'navPoint' => ['navLabel' => 0, 'content' => 1, 'navPoint' => 2],
            'pageTarget' => ['navLabel' => 0, 'content' => 1],
            'navTarget' => ['navLabel' => 0, 'content' => 1],
        ];
        $navigationEntries = array_flip(['navPoint', 'pageTarget', 'navTarget']);

        $diagnostics = [];
        foreach (array_merge([$root], iterator_to_array($root->getElementsByTagName('*'))) as $parent) {
            if (!$parent instanceof \DOMElement || ($parent->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                continue;
            }
            if (!isset($models[$parent->localName])) {
                continue;
            }

            $order = $models[$parent->localName];
            $expectedOrder = array_keys($order);
            $maxRank = -1;
            $maxContext = null;
            $firstContentContext = null;
            $position = 0;
            foreach ($parent->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                $position++;
                if (($child->namespaceURI ?? '') !== self::NCX_NAMESPACE || !array_key_exists($child->localName, $order)) {
                    continue;
                }

                $context = $this->ncxNavigationChildContext($parent, $child, $path, $position) + [
                    'expectedOrder' => $expectedOrder,
                ];
                if ($child->localName === 'content' && isset($navigationEntries[$parent->localName])) {
                    if (is_array($firstContentContext)) {
                        $duplicateContext = $context + [
                            'previousPosition' => $firstContentContext['position'],
                        ];
                        if (isset($firstContentContext['contentSrc'])) {
                            $duplicateContext['previousContentSrc'] = $firstContentContext['contentSrc'];
                        }
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'duplicate-ncx-content',
                            'NCX navigation elements must contain only one direct content child.',
                            $duplicateContext
                        );
                    } else {
                        $firstContentContext = $context;
                    }
                }

                $rank = $order[$child->localName];
                if ($rank < $maxRank) {
                    if (is_array($maxContext)) {
                        $context['previousChildElement'] = $maxContext['childElement'];
                        $context['previousPosition'] = $maxContext['position'];
                        if (isset($maxContext['childId'])) {
                            $context['previousChildId'] = $maxContext['childId'];
                        }
                        if (isset($maxContext['contentSrc'])) {
                            $context['previousContentSrc'] = $maxContext['contentSrc'];
                        }
                    }

                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-ncx-navigation-child-order',
                        'NCX navigation container and entry children must appear in content-model order.',
                        $context
                    );
                    continue;
                }

                if ($rank > $maxRank) {
                    $maxRank = $rank;
                    $maxContext = $context;
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxDocumentMetadataNamespaceDiagnostics(\DOMElement $root, string $path): array
    {
        if (($root->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $diagnostics = [];
        $head = $this->firstChildElement($root, 'head');
        if ($head instanceof \DOMElement && ($head->namespaceURI ?? '') === self::NCX_NAMESPACE) {
            foreach ($head->childNodes as $child) {
                if (!$child instanceof \DOMElement || $child->localName !== 'meta' || ($child->namespaceURI ?? '') === self::NCX_NAMESPACE) {
                    continue;
                }

                $context = [
                    'path' => $path,
                    'element' => 'head',
                    'childElement' => 'meta',
                    'namespace' => $child->namespaceURI ?? '',
                    'expectedNamespace' => self::NCX_NAMESPACE,
                ];
                $name = trim($child->getAttribute('name'));
                if ($name !== '') {
                    $context['name'] = $name;
                }
                $content = trim($child->getAttribute('content'));
                if ($content !== '') {
                    $context['content'] = $content;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-head-meta-namespace',
                    'EPUB NCX head metadata elements must use the DAISY NCX namespace.',
                    $context
                );
            }
        }

        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement || ($child->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                continue;
            }

            if ($child->localName === 'docTitle' || $child->localName === 'docAuthor') {
                $text = $this->firstDescendantElement($child, 'text');
                if ($text instanceof \DOMElement && ($text->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                    $context = $this->ncxDocumentLabelContext($child, $path) + [
                        'childElement' => 'text',
                        'namespace' => $text->namespaceURI ?? '',
                        'expectedNamespace' => self::NCX_NAMESPACE,
                    ];
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        $child->localName === 'docTitle' ? 'invalid-ncx-doc-title-text-namespace' : 'invalid-ncx-doc-author-text-namespace',
                        'EPUB NCX document label text elements must use the DAISY NCX namespace.',
                        $context
                    );
                }
                continue;
            }

            if ($child->localName !== 'pageList' && $child->localName !== 'navList') {
                continue;
            }

            $label = $this->firstChildElement($child, 'navLabel');
            if (!$label instanceof \DOMElement) {
                continue;
            }

            $context = $this->ncxNavigationContainerContext($child, $path);
            if (($label->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-container-nav-label-namespace',
                    'NCX navigation container navLabel elements must use the DAISY NCX namespace.',
                    $context + [
                        'childElement' => 'navLabel',
                        'namespace' => $label->namespaceURI ?? '',
                        'expectedNamespace' => self::NCX_NAMESPACE,
                    ]
                );
            }

            $text = $this->firstDescendantElement($label, 'text');
            if ($text instanceof \DOMElement && ($text->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-container-nav-label-text-namespace',
                    'NCX navigation container label text elements must use the DAISY NCX namespace.',
                    $context + [
                        'childElement' => 'text',
                        'namespace' => $text->namespaceURI ?? '',
                        'expectedNamespace' => self::NCX_NAMESPACE,
                    ]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxDocumentLabelLanguageDiagnostics(\DOMElement $root, string $path): array
    {
        if (($root->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $diagnostics = [];
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement || ($child->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                continue;
            }

            if ($child->localName === 'docTitle' || $child->localName === 'docAuthor') {
                array_push(
                    $diagnostics,
                    ...$this->ncxLabelLanguageDiagnostics(
                        $child,
                        $child->localName === 'docTitle' ? 'invalid-ncx-doc-title-language' : 'invalid-ncx-doc-author-language',
                        'EPUB NCX document label language attributes must be valid language tags.',
                        $this->ncxDocumentLabelContext($child, $path)
                    )
                );
                continue;
            }

            if ($child->localName !== 'pageList' && $child->localName !== 'navList') {
                continue;
            }

            $label = $this->firstChildElement($child, 'navLabel');
            if (!$label instanceof \DOMElement || ($label->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                continue;
            }

            array_push(
                $diagnostics,
                ...$this->ncxLabelLanguageDiagnostics(
                    $label,
                    'invalid-ncx-container-nav-label-language',
                    'NCX navigation container label language attributes must be valid language tags.',
                    $this->ncxNavigationContainerContext($child, $path)
                )
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxDuplicateRootChildDiagnostics(\DOMElement $root, string $path): array
    {
        $diagnostics = [];
        foreach (['head', 'docTitle', 'navMap', 'pageList'] as $localName) {
            $seen = 0;
            $firstContext = null;
            $position = 0;
            foreach ($root->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                $position++;
                if ($child->localName !== $localName) {
                    continue;
                }

                $seen++;
                $context = $this->ncxRootChildContext($child, $path, $position);
                if ($seen === 1) {
                    $firstContext = $context;
                    continue;
                }
                if (is_array($firstContext)) {
                    $context['previousPosition'] = $firstContext['position'];
                    if (isset($firstContext['id'])) {
                        $context['previousId'] = $firstContext['id'];
                    }
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'duplicate-ncx-' . $this->kebabCaseLocalName($localName),
                    'EPUB NCX table of contents must not contain duplicate ' . $localName . ' elements.',
                    $context
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxChildOrderDiagnostics(\DOMElement $root, string $path): array
    {
        $order = [
            'head' => 0,
            'docTitle' => 1,
            'docAuthor' => 2,
            'navMap' => 3,
            'pageList' => 4,
            'navList' => 5,
        ];
        $expectedOrder = array_keys($order);
        $singletonElements = [
            'head' => true,
            'docTitle' => true,
            'navMap' => true,
            'pageList' => true,
        ];
        $seenSingletons = [];
        $diagnostics = [];
        $maxRank = -1;
        $maxContext = null;
        $position = 0;

        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $position++;
            if (!array_key_exists($child->localName, $order)) {
                continue;
            }
            if (isset($singletonElements[$child->localName])) {
                if (isset($seenSingletons[$child->localName])) {
                    continue;
                }
                $seenSingletons[$child->localName] = true;
            }

            $rank = $order[$child->localName];
            $context = $this->ncxRootChildContext($child, $path, $position) + [
                'expectedOrder' => $expectedOrder,
            ];
            if ($rank < $maxRank) {
                if (is_array($maxContext)) {
                    $context['previousElement'] = $maxContext['element'];
                    $context['previousPosition'] = $maxContext['position'];
                    if (isset($maxContext['id'])) {
                        $context['previousId'] = $maxContext['id'];
                    }
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-child-order',
                    'EPUB NCX root children must appear in content-model order: head, docTitle, docAuthor, navMap, pageList, then navList.',
                    $context
                );
                continue;
            }

            if ($rank > $maxRank) {
                $maxRank = $rank;
                $maxContext = $this->ncxRootChildContext($child, $path, $position);
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxRootChildContext(\DOMElement $element, string $path, int $position): array
    {
        $context = [
            'path' => $path,
            'element' => $element->localName,
            'position' => $position,
        ];
        $id = trim($element->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxDocumentLabelContext(\DOMElement $element, string $path): array
    {
        $context = [
            'path' => $path,
            'element' => $element->localName,
        ];
        $id = trim($element->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }
        $text = $this->ncxLabelText($element);
        if ($text !== '') {
            $context['text'] = $text;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxUnexpectedChildContext(\DOMElement $parent, \DOMElement $child, string $path): array
    {
        $context = [
            'path' => $path,
            'element' => $parent->localName,
            'childElement' => $child->localName,
        ];
        $id = trim($parent->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }
        $type = trim($parent->getAttribute($parent->localName === 'pageTarget' ? 'type' : 'class'));
        if ($type !== '') {
            $context['type'] = $type;
        }
        $childId = trim($child->getAttribute('id'));
        if ($childId !== '') {
            $context['childId'] = $childId;
        }
        $text = '';
        if ($parent->localName === 'docTitle' || $parent->localName === 'docAuthor' || $parent->localName === 'navLabel' || $parent->localName === 'navInfo') {
            $text = $this->ncxLabelText($parent);
        } else {
            $label = $this->firstChildElement($parent, 'navLabel') ?? $this->firstDescendantElement($parent, 'navLabel');
            $text = $label instanceof \DOMElement ? $this->ncxNavLabelText($label) : '';
        }
        if ($text !== '') {
            $context['text'] = $text;
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxNavigationChildContext(\DOMElement $parent, \DOMElement $child, string $path, int $position): array
    {
        $context = $this->ncxUnexpectedChildContext($parent, $child, $path) + [
            'position' => $position,
        ];
        $contentSrc = $child->localName === 'content' ? trim($child->getAttribute('src')) : '';
        if ($contentSrc !== '') {
            $context['contentSrc'] = html_entity_decode($contentSrc, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxElementIdContext(\DOMElement $element, string $path, string $id): array
    {
        $context = [
            'path' => $path,
            'element' => $element->localName,
            'id' => $id,
        ];
        $parent = $element->parentNode;
        if ($parent instanceof \DOMElement) {
            $context['parent'] = $parent->localName;
        }
        $type = trim($element->getAttribute($element->localName === 'pageTarget' ? 'type' : 'class'));
        if ($type !== '') {
            $context['type'] = $type;
        }

        if ($element->localName === 'docTitle' || $element->localName === 'docAuthor') {
            $text = $this->ncxLabelText($element);
        } else {
            $label = $this->firstChildElement($element, 'navLabel') ?? $this->firstDescendantElement($element, 'navLabel');
            $text = $label instanceof \DOMElement ? $this->ncxNavLabelText($label) : '';
        }
        if ($text !== '') {
            $context['text'] = $text;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function ncxLabelLanguageDiagnostics(\DOMElement $label, string $code, string $message, array $context): array
    {
        if (($label->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $diagnostics = [];
        foreach ($this->ncxLabelLanguageElements($label) as $element) {
            foreach ($this->ncxInvalidLanguageAttributes($element) as $attribute => $value) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    $code,
                    $message,
                    $context + [
                        'childElement' => $element->localName,
                        'attribute' => $attribute,
                        'language' => $value,
                    ]
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<\DOMElement>
     */
    private function ncxLabelLanguageElements(\DOMElement $label): array
    {
        $elements = [$label];
        $text = $this->firstDescendantElement($label, 'text');
        if ($text instanceof \DOMElement && ($text->namespaceURI ?? '') === self::NCX_NAMESPACE) {
            $elements[] = $text;
        }

        return $elements;
    }

    /**
     * @return array<string, string>
     */
    private function ncxInvalidLanguageAttributes(\DOMElement $element): array
    {
        $invalid = [];
        $xmlLanguage = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($xmlLanguage !== '' && !$this->validXmlLanguageTag($xmlLanguage)) {
            $invalid['xml:lang'] = $xmlLanguage;
        }
        $language = trim($element->getAttribute('lang'));
        if ($language !== '' && !$this->validXmlLanguageTag($language)) {
            $invalid['lang'] = $language;
        }

        return $invalid;
    }

    private function kebabCaseLocalName(string $localName): string
    {
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $localName);

        return strtolower($kebab ?? $localName);
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxNavigationContainerContext(\DOMElement $element, string $path): array
    {
        $context = [
            'path' => $path,
            'element' => $element->localName,
        ];
        $id = trim($element->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }
        $class = trim($element->getAttribute('class'));
        if ($class !== '') {
            $context['type'] = $class;
        }
        $label = $this->firstChildElement($element, 'navLabel');
        if ($label instanceof \DOMElement) {
            $text = $this->ncxNavLabelText($label);
            if ($text !== '') {
                $context['text'] = $text;
            }
        }

        return $context;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxHeadNumericMetadataDiagnostics(\DOMElement $head, string $path): array
    {
        $diagnostics = [];
        foreach ([
            'dtb:depth' => [
                'code' => 'invalid-ncx-depth',
                'message' => 'EPUB NCX dtb:depth metadata must be a positive integer.',
                'validator' => fn (string $value): ?int => $this->positiveXmlInteger($value),
            ],
            'dtb:totalPageCount' => [
                'code' => 'invalid-ncx-total-page-count',
                'message' => 'EPUB NCX dtb:totalPageCount metadata must be a non-negative integer.',
                'validator' => fn (string $value): ?int => $this->nonNegativeXmlInteger($value),
            ],
            'dtb:maxPageNumber' => [
                'code' => 'invalid-ncx-max-page-number',
                'message' => 'EPUB NCX dtb:maxPageNumber metadata must be a non-negative integer.',
                'validator' => fn (string $value): ?int => $this->nonNegativeXmlInteger($value),
            ],
        ] as $name => $metadata) {
            $element = $this->ncxHeadMetaElement($head, $name);
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $value = trim($element->getAttribute('content'));
            if (($metadata['validator'])($value) !== null) {
                continue;
            }
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                $metadata['code'],
                $metadata['message'],
                [
                    'path' => $path,
                    'name' => $name,
                    'value' => $value,
                ]
            );
        }

        return $diagnostics;
    }

    private function ncxHeadMetaContent(\DOMElement $head, string $name): string
    {
        $element = $this->ncxHeadMetaElement($head, $name);

        return $element instanceof \DOMElement ? trim($element->getAttribute('content')) : '';
    }

    private function ncxHeadMetaElement(\DOMElement $head, string $name): ?\DOMElement
    {
        foreach ($head->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'meta') {
                continue;
            }
            if (strtolower(trim($child->getAttribute('name'))) !== strtolower($name)) {
                continue;
            }

            return $child;
        }

        return null;
    }

    private function ncxLabelText(\DOMElement $element): string
    {
        $text = $this->firstDescendantText($element, 'text');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param array{
     *     linearPaths: array<string, array{order: int, idref: string, path: string, fallbackIdref?: string}>,
     *     nonLinearPaths: array<string, string>,
     *     manifestPaths: array<string, string>
     * } $targetMaps
     * @return list<array<string, mixed>>
     */
    private function ncxDocumentNavigationDiagnostics(\DOMDocument $dom, string $path, array $targetMaps): array
    {
        $diagnostics = [];
        $seenPlayOrders = [];
        $previousPlayOrder = null;
        $previousContext = null;
        foreach ($this->ncxNavigationElements($dom) as $element) {
            $navType = $this->ncxNavigationElementType($element);
            $context = $this->ncxNavigationElementContext($element, $path, $navType);
            array_push($diagnostics, ...$this->ncxNavigationElementNamespaceDiagnostics($element, $context));
            array_push($diagnostics, ...$this->ncxNavigationChildNamespaceDiagnostics($element, $context));
            array_push($diagnostics, ...$this->ncxNavigationLabelLanguageDiagnostics($element, $context));
            array_push($diagnostics, ...$this->ncxNavigationLabelDiagnostics($element, $context));
            array_push($diagnostics, ...$this->ncxPageTargetTypeDiagnostics($element, $context));
            array_push($diagnostics, ...$this->ncxPageTargetValueDiagnostics($element, $context));
            $rawPlayOrder = trim($element->getAttribute('playOrder'));
            if ($rawPlayOrder === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-ncx-play-order',
                    'NCX navigation element is missing the required playOrder attribute.',
                    $context
                );
            } else {
                $playOrder = $this->positiveXmlInteger($rawPlayOrder);
                if ($playOrder === null) {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-ncx-play-order',
                        'NCX playOrder must be a positive integer.',
                        $context + ['value' => $rawPlayOrder]
                    );
                } else {
                    if (isset($seenPlayOrders[$playOrder])) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'duplicate-ncx-play-order',
                            'NCX playOrder values must be unique within a navigation document.',
                            $this->ncxDuplicatePlayOrderContext($context, $seenPlayOrders[$playOrder], $playOrder)
                        );
                    }
                    if ($previousPlayOrder !== null && $playOrder <= $previousPlayOrder) {
                        $diagnostics[] = $this->epubDiagnostic(
                            'error',
                            'out-of-order-ncx-play-order',
                            'NCX playOrder values must increase in document reading order.',
                            $this->ncxOutOfOrderPlayOrderContext($context, $previousContext ?? [], $playOrder, $previousPlayOrder)
                        );
                    }
                    $seenPlayOrders[$playOrder] ??= $context + ['playOrder' => $playOrder];
                    $previousPlayOrder = $playOrder;
                    $previousContext = $context + ['playOrder' => $playOrder];
                }
            }

            array_push(
                $diagnostics,
                ...$this->ncxNavigationTargetDiagnostics(
                    $element,
                    $path,
                    $context,
                    $targetMaps,
                    $element->localName !== 'navPoint'
                )
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<\DOMElement>
     */
    private function ncxNavigationElements(\DOMDocument $dom): array
    {
        $elements = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            if (in_array($element->localName, ['navPoint', 'pageTarget', 'navTarget'], true)) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private function ncxNavigationElementType(\DOMElement $element): string
    {
        return match ($element->localName) {
            'navPoint' => 'toc',
            'pageTarget' => 'page-list',
            'navTarget' => 'nav-list',
            default => '',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxNavigationElementContext(\DOMElement $element, string $path, string $navType): array
    {
        $context = [
            'path' => $path,
            'element' => $element->localName,
        ];
        if ($navType !== '') {
            $context['navType'] = $navType;
        }
        $id = trim($element->getAttribute('id'));
        if ($id !== '') {
            $context['id'] = $id;
        }
        $type = trim($element->getAttribute($element->localName === 'pageTarget' ? 'type' : 'class'));
        if ($type !== '') {
            $context['type'] = $type;
        }
        $value = trim($element->getAttribute('value'));
        if ($value !== '') {
            $context['value'] = $value;
        }

        $label = $this->firstChildElement($element, 'navLabel');
        if ($label instanceof \DOMElement) {
            $text = $this->ncxNavLabelText($label);
            if ($text !== '') {
                $context['text'] = $text;
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function ncxNavigationElementNamespaceDiagnostics(\DOMElement $element, array $context): array
    {
        if (($element->namespaceURI ?? '') === self::NCX_NAMESPACE) {
            return [];
        }

        return [
            $this->epubDiagnostic(
                'error',
                'invalid-ncx-navigation-namespace',
                'NCX navigation elements must use the DAISY NCX namespace.',
                $context + [
                    'namespace' => $element->namespaceURI ?? '',
                    'expectedNamespace' => self::NCX_NAMESPACE,
                ]
            ),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function ncxNavigationChildNamespaceDiagnostics(\DOMElement $element, array $context): array
    {
        if (($element->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $diagnostics = [];
        $label = $this->firstChildElement($element, 'navLabel');
        if ($label instanceof \DOMElement) {
            if (($label->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-nav-label-namespace',
                    'NCX navigation navLabel elements must use the DAISY NCX namespace.',
                    $context + [
                        'childElement' => 'navLabel',
                        'namespace' => $label->namespaceURI ?? '',
                        'expectedNamespace' => self::NCX_NAMESPACE,
                    ]
                );
            }

            $text = $this->firstDescendantElement($label, 'text');
            if ($text instanceof \DOMElement && ($text->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-nav-label-text-namespace',
                    'NCX navigation label text elements must use the DAISY NCX namespace.',
                    $context + [
                        'childElement' => 'text',
                        'namespace' => $text->namespaceURI ?? '',
                        'expectedNamespace' => self::NCX_NAMESPACE,
                    ]
                );
            }
        }

        $content = $this->firstChildElement($element, 'content');
        if ($content instanceof \DOMElement && ($content->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-ncx-content-namespace',
                'NCX navigation content elements must use the DAISY NCX namespace.',
                $context + [
                    'childElement' => 'content',
                    'namespace' => $content->namespaceURI ?? '',
                    'expectedNamespace' => self::NCX_NAMESPACE,
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function ncxNavigationLabelLanguageDiagnostics(\DOMElement $element, array $context): array
    {
        if (($element->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        $label = $this->firstChildElement($element, 'navLabel');
        if (!$label instanceof \DOMElement || ($label->namespaceURI ?? '') !== self::NCX_NAMESPACE) {
            return [];
        }

        return $this->ncxLabelLanguageDiagnostics(
            $label,
            'invalid-ncx-nav-label-language',
            'NCX navigation label language attributes must be valid language tags.',
            $context
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function ncxNavigationLabelDiagnostics(\DOMElement $element, array $context): array
    {
        $label = $this->firstChildElement($element, 'navLabel');
        if (!$label instanceof \DOMElement) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'missing-ncx-nav-label',
                    'NCX navigation elements must contain a navLabel child.',
                    $context
                ),
            ];
        }

        if ($this->ncxNavLabelText($label) !== '') {
            return [];
        }

        return [
            $this->epubDiagnostic(
                'error',
                'empty-ncx-nav-label',
                'NCX navigation labels must contain text.',
                $context
            ),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function ncxPageTargetTypeDiagnostics(\DOMElement $element, array $context): array
    {
        if ($element->localName !== 'pageTarget') {
            return [];
        }

        $type = trim($element->getAttribute('type'));
        if ($type === '') {
            return [
                $this->epubDiagnostic(
                    'error',
                    'missing-ncx-page-target-type',
                    'NCX pageTarget elements must declare a type attribute.',
                    $context
                ),
            ];
        }

        if (!in_array($type, ['front', 'normal', 'special'], true)) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-page-target-type',
                    'NCX pageTarget type must be front, normal, or special.',
                    $context + ['allowed' => ['front', 'normal', 'special']]
                ),
            ];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function ncxPageTargetValueDiagnostics(\DOMElement $element, array $context): array
    {
        if ($element->localName !== 'pageTarget') {
            return [];
        }

        $value = trim($element->getAttribute('value'));
        if ($value !== '') {
            return [];
        }

        return [
            $this->epubDiagnostic(
                'error',
                'missing-ncx-page-target-value',
                'NCX pageTarget elements must declare a value attribute.',
                $context
            ),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $firstContext
     * @return array<string, mixed>
     */
    private function ncxDuplicatePlayOrderContext(array $context, array $firstContext, int $playOrder): array
    {
        $duplicate = $context + ['playOrder' => $playOrder];
        foreach ([
            'id' => 'firstId',
            'element' => 'firstElement',
            'navType' => 'firstNavType',
            'text' => 'firstText',
        ] as $sourceKey => $targetKey) {
            if (isset($firstContext[$sourceKey])) {
                $duplicate[$targetKey] = $firstContext[$sourceKey];
            }
        }

        return $duplicate;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $previousContext
     * @return array<string, mixed>
     */
    private function ncxOutOfOrderPlayOrderContext(array $context, array $previousContext, int $playOrder, int $previousPlayOrder): array
    {
        $outOfOrder = $context + [
            'playOrder' => $playOrder,
            'previousPlayOrder' => $previousPlayOrder,
        ];
        foreach ([
            'id' => 'previousId',
            'element' => 'previousElement',
            'navType' => 'previousNavType',
            'text' => 'previousText',
        ] as $sourceKey => $targetKey) {
            if (isset($previousContext[$sourceKey])) {
                $outOfOrder[$targetKey] = $previousContext[$sourceKey];
            }
        }

        return $outOfOrder;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     linearPaths: array<string, array{order: int, idref: string, path: string, fallbackIdref?: string}>,
     *     nonLinearPaths: array<string, string>,
     *     manifestPaths: array<string, string>
     * } $targetMaps
     * @return list<array<string, mixed>>
     */
    private function ncxNavigationTargetDiagnostics(\DOMElement $element, string $path, array $context, array $targetMaps, bool $checkSpineTarget): array
    {
        $content = $this->firstChildElement($element, 'content');
        $src = $content instanceof \DOMElement ? html_entity_decode(trim($content->getAttribute('src')), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
        if ($src === '') {
            return [
                $this->epubDiagnostic(
                    'error',
                    'missing-ncx-content-src',
                    'NCX navigation element is missing a content src target.',
                    $context
                ),
            ];
        }

        $srcPathReason = $this->ncxContentSrcPathDiagnosticReason($src);
        if ($srcPathReason !== '') {
            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-content-src-path',
                    'NCX navigation content src must be an absolute URL or a path-relative scheme-less URL.',
                    $context + [
                        'contentSrc' => $src,
                        'reason' => $srcPathReason,
                    ]
                ),
            ];
        }

        $href = $this->rewriteRelativeResourceUrl($src, $this->dirname($path));
        $targetPath = '';
        if ($this->isPackageRelativeResourceUrl($href)) {
            [$targetPath] = $this->splitUrlPathSuffix($href);
            $targetPath = $this->normalizeZipPath($targetPath);
        }

        $srcFragmentReason = $this->ncxContentSrcFragmentDiagnosticReason($src);
        if ($srcFragmentReason !== '') {
            $fragmentContext = $context + [
                'contentSrc' => $src,
                'reason' => $srcFragmentReason,
            ];
            if ($src !== $href) {
                $fragmentContext['href'] = $href;
            }
            if ($targetPath !== '') {
                $fragmentContext['targetPath'] = $targetPath;
            }
            $fragment = $this->urlFragmentIdentifier($src);
            if ($fragment !== '') {
                $fragmentContext['fragment'] = $fragment;
            }

            return [
                $this->epubDiagnostic(
                    'error',
                    'invalid-ncx-content-src-fragment',
                    'NCX navigation content src fragments must be non-empty fragment identifiers without whitespace.',
                    $fragmentContext
                ),
            ];
        }

        if (!$checkSpineTarget || !$this->isPackageRelativeResourceUrl($href)) {
            return [];
        }

        [$targetPath, $suffix] = $this->splitUrlPathSuffix($href);
        $targetPath = $this->normalizeZipPath($targetPath);
        if ($targetPath === '') {
            return [];
        }

        $targetContext = $context + [
            'href' => $href,
            'targetPath' => $targetPath,
        ];
        if ($src !== $href) {
            $targetContext['contentSrc'] = $src;
        }
        if ($suffix !== '') {
            $targetContext['targetSuffix'] = $suffix;
        }

        if (isset($targetMaps['linearPaths'][$targetPath])) {
            return [];
        }

        if (isset($targetMaps['nonLinearPaths'][$targetPath])) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'ncx-target-non-linear-spine',
                    'NCX navigation target points at a non-linear spine resource.',
                    $targetContext + ['idref' => $targetMaps['nonLinearPaths'][$targetPath]]
                ),
            ];
        }

        return [
            $this->epubDiagnostic(
                'error',
                'missing-ncx-spine-target',
                'NCX navigation target does not point at a linear spine resource.',
                $targetContext + (isset($targetMaps['manifestPaths'][$targetPath]) ? ['manifestId' => $targetMaps['manifestPaths'][$targetPath]] : [])
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function uniqueNavigationEntries(array $entries): array
    {
        $unique = [];
        $seen = [];
        foreach ($entries as $entry) {
            $key = implode("\0", [
                (string) ($entry['text'] ?? ''),
                (string) ($entry['href'] ?? ''),
                (string) ($entry['level'] ?? ''),
                (string) ($entry['type'] ?? ''),
                (string) ($entry['value'] ?? ''),
                (string) ($entry['id'] ?? ''),
                (string) ($entry['title'] ?? ''),
                (string) ($entry['role'] ?? ''),
                (string) ($entry['ariaLabel'] ?? ''),
                (string) ($entry['lang'] ?? ''),
                (string) ($entry['dir'] ?? ''),
                (string) ($entry['rel'] ?? ''),
                (string) ($entry['hreflang'] ?? ''),
                (string) ($entry['media'] ?? ''),
                (string) ($entry['target'] ?? ''),
                json_encode($entry['classes'] ?? [], JSON_UNESCAPED_SLASHES) ?: '',
                ($entry['hidden'] ?? false) === true ? 'hidden' : '',
            ]);
            if (isset($seen[$key])) {
                $index = $seen[$key];
                foreach (['playOrder'] as $metadataKey) {
                    if (isset($entry[$metadataKey]) && !isset($unique[$index][$metadataKey])) {
                        $unique[$index][$metadataKey] = $entry[$metadataKey];
                    }
                }
                continue;
            }
            $seen[$key] = count($unique);
            $unique[] = $entry;
        }

        return $unique;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function uniquePageListEntries(array $entries): array
    {
        $unique = [];
        $seen = [];
        foreach ($entries as $entry) {
            $key = implode("\0", [
                (string) ($entry['text'] ?? ''),
                (string) ($entry['href'] ?? ''),
                (string) ($entry['level'] ?? ''),
                (string) ($entry['value'] ?? ''),
                (string) ($entry['title'] ?? ''),
                (string) ($entry['role'] ?? ''),
                (string) ($entry['ariaLabel'] ?? ''),
                (string) ($entry['lang'] ?? ''),
                (string) ($entry['dir'] ?? ''),
                (string) ($entry['rel'] ?? ''),
                (string) ($entry['hreflang'] ?? ''),
                (string) ($entry['media'] ?? ''),
                (string) ($entry['target'] ?? ''),
                json_encode($entry['classes'] ?? [], JSON_UNESCAPED_SLASHES) ?: '',
                ($entry['hidden'] ?? false) === true ? 'hidden' : '',
            ]);
            if (isset($seen[$key])) {
                $index = $seen[$key];
                if (($entry['type'] ?? null) === 'pagebreak') {
                    $unique[$index]['type'] = 'pagebreak';
                    if (!isset($entry['id'])) {
                        unset($unique[$index]['id']);
                    }
                }
                foreach (['playOrder'] as $metadataKey) {
                    if (isset($entry[$metadataKey]) && !isset($unique[$index][$metadataKey])) {
                        $unique[$index][$metadataKey] = $entry[$metadataKey];
                    }
                }
                continue;
            }
            $seen[$key] = count($unique);
            $unique[] = $entry;
        }

        return $unique;
    }

    /**
     * @param list<list<array<string, mixed>>> $groups
     * @return list<list<array<string, mixed>>>
     */
    private function uniqueNavigationEntryGroups(array $groups): array
    {
        $uniqueGroups = [];
        foreach ($groups as $group) {
            $uniqueGroup = $this->uniqueNavigationEntries($group);
            if ($uniqueGroup !== []) {
                $uniqueGroups[] = $uniqueGroup;
            }
        }

        return $uniqueGroups;
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    private function uniqueAuxiliaryNavSections(array $sections): array
    {
        $unique = [];
        $seen = [];
        foreach ($sections as $section) {
            $key = json_encode($section, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $section;
        }

        return $unique;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param array<string, array<string, mixed>> $overlayMetadata
     * @return list<array<string, mixed>>
     */
    private function mediaOverlays(\ZipArchive $zip, string $base_path, array $manifest, array $overlayMetadata = []): array
    {
        $overlay_items = [];
        foreach ($manifest as $id => $item) {
            $media_type = strtolower($item['media-type']);
            $href = strtolower($item['href']);
            if (str_contains($media_type, 'smil') || str_ends_with($href, '.smil')) {
                $overlay_items[$id] = $item;
            }
        }

        $overlays = [];
        foreach ($manifest as $id => $item) {
            $overlay_id = $item['media-overlay'];
            if ($overlay_id === '') {
                continue;
            }

            $entry = [
                'contentId' => $id,
                'contentHref' => $item['href'],
                'contentPath' => $this->normalizeZipPath($base_path . '/' . $item['href']),
                'contentMediaType' => $item['media-type'],
                'overlayId' => $overlay_id,
                'parCount' => 0,
                'textTargets' => [],
                'audioTargets' => [],
            ];
            $this->applyMediaOverlayMetadata($entry, $overlayMetadata[$overlay_id] ?? []);
            $this->applyMediaOverlayMetadata($entry, $overlayMetadata[$id] ?? [], 'content');

            $overlay_item = $overlay_items[$overlay_id] ?? null;
            if (!is_array($overlay_item)) {
                $entry['missing'] = true;
                $overlays[] = $entry;
                continue;
            }

            $overlay_path = $this->normalizeZipPath($base_path . '/' . $overlay_item['href']);
            $entry['overlayHref'] = $overlay_item['href'];
            $entry['overlayPath'] = $overlay_path;
            $entry['overlayMediaType'] = $overlay_item['media-type'];

            $xml = $zip->getFromName($overlay_path);
            if (!is_string($xml)) {
                $entry['missing'] = true;
                $overlays[] = $entry;
                continue;
            }

            try {
                $pairs = $this->smilOverlayPairs($xml, $this->dirname($overlay_path));
                $sequences = $this->smilOverlaySequences($xml, $this->dirname($overlay_path));
                $documentAttributes = $this->smilOverlayDocumentAttributes($xml);
            } catch (\InvalidArgumentException) {
                $entry['malformed'] = true;
                $overlays[] = $entry;
                continue;
            }

            $entry['parCount'] = count($pairs);
            if (($documentAttributes['rootAttributes'] ?? []) !== []) {
                $entry['rootAttributes'] = $documentAttributes['rootAttributes'];
            }
            if (($documentAttributes['bodyAttributes'] ?? []) !== []) {
                $entry['bodyAttributes'] = $documentAttributes['bodyAttributes'];
            }
            if ($sequences !== []) {
                $entry['sequences'] = $sequences;
            }
            if ($pairs !== []) {
                $entry['pairs'] = $pairs;
                $entry['textTargets'] = array_values(array_unique(array_filter(
                    array_map(static fn (array $pair): string => (string) ($pair['text'] ?? ''), $pairs),
                    static fn (string $target): bool => $target !== ''
                )));
                $entry['audioTargets'] = array_values(array_unique(array_filter(
                    array_map(static fn (array $pair): string => (string) ($pair['audio'] ?? ''), $pairs),
                    static fn (string $target): bool => $target !== ''
                )));
            }

            $overlays[] = $entry;
        }

        return $overlays;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $metadata
     */
    private function applyMediaOverlayMetadata(array &$entry, array $metadata, string $prefix = ''): void
    {
        if ($metadata === []) {
            return;
        }

        foreach (['duration', 'narrator', 'activeClass', 'playbackActiveClass'] as $key) {
            if (!isset($metadata[$key]) || !is_scalar($metadata[$key]) || trim((string) $metadata[$key]) === '') {
                continue;
            }
            $entry[$prefix === '' ? $key : $prefix . ucfirst($key)] = trim((string) $metadata[$key]);
        }
        if (isset($metadata['metadataProperties']) && is_array($metadata['metadataProperties'])) {
            $entry[$prefix === '' ? 'metadataProperties' : $prefix . 'MetadataProperties'] = $metadata['metadataProperties'];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function smilOverlayPairs(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB SMIL media overlay');
        $pairs = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'par') {
                continue;
            }

            $text = $this->firstDescendantElement($element, 'text');
            $audio = $this->firstDescendantElement($element, 'audio');
            $text_src = $text instanceof \DOMElement ? html_entity_decode(trim($text->getAttribute('src')), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            $audio_src = $audio instanceof \DOMElement ? html_entity_decode(trim($audio->getAttribute('src')), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            if ($text_src === '' && $audio_src === '') {
                continue;
            }

            $pair = [
                'index' => count($pairs) + 1,
            ];
            $id = trim($element->getAttribute('id'));
            if ($id !== '') {
                $pair['id'] = $id;
            }
            if ($text_src !== '') {
                $pair['text'] = $this->mediaOverlayReferencePathDiagnosticReason($text_src) === ''
                    ? $this->rewriteRelativeResourceUrl($text_src, $base_path)
                    : $text_src;
            }
            if ($text instanceof \DOMElement) {
                $text_attributes = $this->smilOverlayElementAttributes($text, false);
                if (($text_attributes['id'] ?? '') !== '') {
                    $pair['textId'] = $text_attributes['id'];
                }
                if ($text_attributes !== []) {
                    $pair['textAttributes'] = $text_attributes;
                }
            }
            if ($audio_src !== '') {
                $pair['audio'] = $this->mediaOverlayReferencePathDiagnosticReason($audio_src) === ''
                    ? $this->rewriteRelativeResourceUrl($audio_src, $base_path)
                    : $audio_src;
            }
            if ($audio instanceof \DOMElement) {
                $audio_attributes = $this->smilOverlayElementAttributes($audio, false);
                if (($audio_attributes['id'] ?? '') !== '') {
                    $pair['audioId'] = $audio_attributes['id'];
                }
                if ($audio_attributes !== []) {
                    $pair['audioAttributes'] = $audio_attributes;
                }
                $clip_begin = trim($audio->getAttribute('clipBegin'));
                $clip_end = trim($audio->getAttribute('clipEnd'));
                if ($clip_begin !== '') {
                    $pair['clipBegin'] = $clip_begin;
                }
                if ($clip_end !== '') {
                    $pair['clipEnd'] = $clip_end;
                }
            }

            $pairs[] = $pair;
        }

        return $pairs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function smilOverlaySequences(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB SMIL media overlay');
        $sequences = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'seq') {
                continue;
            }

            $sequence = [
                'index' => count($sequences) + 1,
            ];
            $id = trim($element->getAttribute('id'));
            if ($id !== '') {
                $sequence['id'] = $id;
            }
            $textref = html_entity_decode(trim($this->attributeByLocalName($element, 'textref')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($textref !== '') {
                $sequence['textref'] = $this->mediaOverlayReferencePathDiagnosticReason($textref) === ''
                    ? $this->rewriteRelativeResourceUrl($textref, $base_path)
                    : $textref;
            }
            $type = trim($this->attributeByLocalName($element, 'type'));
            if ($type !== '') {
                $sequence['type'] = $type;
            }
            $language = trim($element->getAttribute('xml:lang'));
            if ($language === '') {
                $language = trim($this->attributeByLocalName($element, 'lang'));
            }
            if ($language !== '' && $this->validLanguageTag($language)) {
                $sequence['lang'] = $language;
            }

            $parIds = [];
            foreach ($element->getElementsByTagName('*') as $descendant) {
                if (!$descendant instanceof \DOMElement || $descendant->localName !== 'par') {
                    continue;
                }
                $parId = trim($descendant->getAttribute('id'));
                if ($parId !== '') {
                    $parIds[] = $parId;
                }
            }

            $hasMetadata = array_diff(array_keys($sequence), ['index']) !== [];
            if (!$hasMetadata) {
                continue;
            }
            if ($parIds !== []) {
                $sequence['parIds'] = array_values(array_unique($parIds));
            }
            $sequences[] = $sequence;
        }

        return $sequences;
    }

    /**
     * @return array{rootAttributes: array<string, mixed>, bodyAttributes: array<string, mixed>}
     */
    private function smilOverlayDocumentAttributes(string $xml): array
    {
        $dom = $this->loadXml($xml, 'EPUB SMIL media overlay');
        $root = $dom->documentElement;
        $body = null;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'body') {
                $body = $element;
                break;
            }
        }

        return [
            'rootAttributes' => $root instanceof \DOMElement ? $this->smilOverlayElementAttributes($root, true) : [],
            'bodyAttributes' => $body instanceof \DOMElement ? $this->smilOverlayElementAttributes($body, false) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function smilOverlayElementAttributes(\DOMElement $element, bool $root): array
    {
        $attributes = [];
        foreach ([
            'id' => 'id',
            'role' => 'role',
            'title' => 'title',
            'ariaLabel' => 'aria-label',
        ] as $key => $attribute) {
            $value = trim($this->attributeByLocalName($element, $attribute));
            if ($value !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        if ($root) {
            foreach (['prefix' => 'prefix', 'version' => 'version'] as $key => $attribute) {
                $value = trim($this->attributeByLocalName($element, $attribute));
                if ($value !== '') {
                    $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }

        $classes = $this->attributeTokenList($element, 'class');
        if ($classes !== []) {
            $attributes['classes'] = $classes;
        }

        $epubType = trim($this->epubTypeAttribute($element));
        if ($epubType !== '') {
            $attributes['epubType'] = html_entity_decode($epubType, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($element->getAttribute('lang'));
        }
        if ($this->validLanguageTag($language)) {
            $attributes['lang'] = $language;
        }

        $direction = strtolower(trim($element->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if ($element->hasAttribute('hidden')) {
            $attributes['hidden'] = true;
        }

        return $attributes;
    }

    /**
     * @return array{
     *     toc: list<array{text: string, level: int, href?: string, type?: string}>,
     *     landmarks: list<array{text: string, level: int, href?: string, type?: string}>,
     *     pageList: list<array{text: string, level: int, href?: string, type?: string, value?: string}>,
     *     landmarkNavCount: int,
     *     auxiliaryNavSections: list<array{type: string, title?: string, attributes?: array<string, mixed>, entries: list<array<string, mixed>>}>,
     *     rootAttributes: array<string, mixed>,
     *     bodyAttributes: array<string, mixed>,
     *     tocNavAttributes: array<string, mixed>,
     *     landmarkNavAttributes: array<string, mixed>,
     *     pageListNavAttributes: array<string, mixed>,
     *     tocNavTitle: string,
     *     landmarkNavTitle: string,
     *     pageListNavTitle: string
     * }
     */
    private function xhtmlNavigationEntries(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB nav document');
        $navs = [];
        $toc_navs = [];
        $landmark_navs = [];
        $page_list_navs = [];
        $auxiliary_navs = [];
        $auxiliary_types = ['loi', 'lot', 'loa', 'lov'];
        $known_typed_navs = 0;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }
            $type = strtolower($this->attributeByLocalName($element, 'type'));
            $navs[] = $element;
            $matched_auxiliary_type = '';
            if ($this->tokenListContains($type, 'toc')) {
                $toc_navs[] = $element;
                $known_typed_navs++;
            }
            if ($this->tokenListContains($type, 'landmarks')) {
                $landmark_navs[] = $element;
                $known_typed_navs++;
            }
            if ($this->tokenListContains($type, 'page-list') || $this->tokenListContains($type, 'pagebreaks')) {
                $page_list_navs[] = $element;
                $known_typed_navs++;
            }
            foreach ($auxiliary_types as $auxiliary_type) {
                if (!$this->tokenListContains($type, $auxiliary_type)) {
                    continue;
                }
                $matched_auxiliary_type = $auxiliary_type;
                $known_typed_navs++;
                break;
            }
            if ($matched_auxiliary_type !== '') {
                $auxiliary_navs[] = [$matched_auxiliary_type, $element];
            }
        }
        if ($toc_navs === [] && $known_typed_navs === 0) {
            $toc_navs = $navs;
        }

        $toc_entries = [];
        foreach ($toc_navs as $nav) {
            array_push($toc_entries, ...$this->xhtmlNavListEntries($nav, $base_path, 1, 'toc'));
        }
        $landmark_entries = [];
        foreach ($landmark_navs as $nav) {
            array_push($landmark_entries, ...$this->xhtmlNavListEntries($nav, $base_path, 1, 'landmarks'));
        }
        $page_list_entries = [];
        foreach ($page_list_navs as $nav) {
            array_push($page_list_entries, ...$this->xhtmlNavListEntries($nav, $base_path, 1, 'page-list'));
        }
        $auxiliary_nav_sections = [];
        foreach ($auxiliary_navs as [$nav_type, $nav]) {
            $entries = $this->xhtmlNavListEntries($nav, $base_path, 1, $nav_type);
            $section = [
                'type' => $nav_type,
            ];
            $title = $this->xhtmlNavSectionTitle($nav);
            if ($title !== '') {
                $section['title'] = $title;
            }
            $attributes = $this->xhtmlNavSectionAttributes($nav, $nav_type, $nav_type, 2);
            if ($attributes !== []) {
                $section['attributes'] = $attributes;
            }
            $section['entries'] = $entries;
            $auxiliary_nav_sections[] = $section;
        }

        return [
            'toc' => $toc_entries,
            'landmarks' => $landmark_entries,
            'pageList' => $page_list_entries,
            'landmarkNavCount' => count($landmark_navs),
            'auxiliaryNavSections' => $auxiliary_nav_sections,
            'rootAttributes' => $this->xhtmlRootAttributesFromDom($dom),
            'bodyAttributes' => $this->xhtmlBodyAttributesFromDom($dom),
            'tocNavAttributes' => $this->xhtmlNavSectionAttributes($toc_navs[0] ?? null, 'toc', 'toc', 1),
            'landmarkNavAttributes' => $this->xhtmlNavSectionAttributes($landmark_navs[0] ?? null, 'landmarks', 'landmarks', 2),
            'pageListNavAttributes' => $this->xhtmlNavSectionAttributes($page_list_navs[0] ?? null, 'page-list', 'page-list', 2),
            'tocNavTitle' => $this->xhtmlNavSectionTitle($toc_navs[0] ?? null),
            'landmarkNavTitle' => $this->xhtmlNavSectionTitle($landmark_navs[0] ?? null),
            'pageListNavTitle' => $this->xhtmlNavSectionTitle($page_list_navs[0] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function xhtmlNavSectionAttributes(?\DOMElement $nav, string $requiredType, string $defaultId, int $defaultHeadingLevel = 0): array
    {
        if (!$nav instanceof \DOMElement) {
            return [];
        }

        $attributes = [];
        $type = trim($this->attributeByLocalName($nav, 'type'));
        $typeTokens = array_values(array_filter(preg_split('/\s+/', strtolower($type)) ?: [], static fn (string $token): bool => $token !== ''));
        if ($type !== '' && $typeTokens !== [$requiredType]) {
            $attributes['type'] = html_entity_decode($type, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $id = $this->validNavSectionId(trim($nav->getAttribute('id')));
        if ($id !== '' && $id !== $defaultId) {
            $attributes['id'] = $id;
        }

        foreach ([
            'role' => 'role',
            'title' => 'title',
            'ariaLabel' => 'aria-label',
        ] as $key => $attribute) {
            $value = trim($this->attributeByLocalName($nav, $attribute));
            if ($value !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        $classes = $this->attributeTokenList($nav, 'class');
        if ($classes !== []) {
            $attributes['classes'] = $classes;
        }

        $language = trim($nav->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($nav->getAttribute('lang'));
        }
        if ($this->validLanguageTag($language)) {
            $attributes['lang'] = $language;
        }

        $direction = strtolower(trim($nav->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if ($nav->hasAttribute('hidden')) {
            $attributes['hidden'] = true;
        }
        $heading = $this->xhtmlNavSectionHeading($nav);
        if ($defaultHeadingLevel > 0 && $heading['level'] > 0 && $heading['level'] !== $defaultHeadingLevel) {
            $attributes['headingLevel'] = $heading['level'];
        }
        if ($heading['attributes'] !== []) {
            $attributes['headingAttributes'] = $heading['attributes'];
        }

        return $attributes;
    }

    private function xhtmlNavSectionTitle(?\DOMElement $nav): string
    {
        return $this->xhtmlNavSectionHeading($nav)['text'];
    }

    /**
     * @return array{text: string, level: int, attributes: array<string, mixed>}
     */
    private function xhtmlNavSectionHeading(?\DOMElement $nav): array
    {
        if (!$nav instanceof \DOMElement) {
            return ['text' => '', 'level' => 0, 'attributes' => []];
        }

        foreach ($nav->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if (preg_match('/^h([1-6])$/i', $child->localName, $matches) !== 1) {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $child->textContent) ?? $child->textContent);
            if ($text !== '') {
                return [
                    'text' => $text,
                    'level' => (int) $matches[1],
                    'attributes' => $this->xhtmlNavHeadingAttributes($child),
                ];
            }
        }

        return ['text' => '', 'level' => 0, 'attributes' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function xhtmlNavHeadingAttributes(\DOMElement $heading): array
    {
        $attributes = [];
        $type = strtolower(trim($this->attributeByLocalName($heading, 'type')));
        if ($type !== '') {
            $attributes['type'] = html_entity_decode($type, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $id = $this->validNavSectionId(trim($this->attributeByLocalName($heading, 'id')));
        if ($id !== '') {
            $attributes['id'] = $id;
        }

        foreach ([
            'role' => 'role',
            'title' => 'title',
            'ariaLabel' => 'aria-label',
        ] as $key => $attribute) {
            $value = trim($this->attributeByLocalName($heading, $attribute));
            if ($value !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        $classes = $this->attributeTokenList($heading, 'class');
        if ($classes !== []) {
            $attributes['classes'] = $classes;
        }

        $language = trim($heading->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($heading->getAttribute('lang'));
        }
        if ($this->validLanguageTag($language)) {
            $attributes['lang'] = $language;
        }

        $direction = strtolower(trim($heading->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if ($heading->hasAttribute('hidden')) {
            $attributes['hidden'] = true;
        }

        return $attributes;
    }

    private function validNavSectionId(string $id): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $id) === 1 ? $id : '';
    }

    private function defaultedNavTitle(string $title, string $default): string
    {
        return $title !== '' && $title !== $default ? $title : '';
    }

    /**
     * @return list<array{text: string, level: int, href?: string, type?: string, value?: string}>
     */
    private function xhtmlNavListEntries(\DOMNode $parent, string $base_path, int $level, string $navType = ''): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'li') {
                if (!$this->navListItemHasOrderedListParent($child)) {
                    continue;
                }
                $entry = $this->xhtmlNavListItemEntry($child, $base_path, $level, $navType);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
                foreach ($child->childNodes as $nested) {
                    if ($nested instanceof \DOMElement && in_array($nested->localName, ['ol', 'ul'], true)) {
                        array_push($entries, ...$this->xhtmlNavListEntries($nested, $base_path, $level + 1, $navType));
                    }
                }
                continue;
            }
            if (in_array($child->localName, ['ol', 'ul'], true)) {
                array_push($entries, ...$this->xhtmlNavListEntries($child, $base_path, $level, $navType));
                continue;
            }
            array_push($entries, ...$this->xhtmlNavListEntries($child, $base_path, $level, $navType));
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function xhtmlNavListItemEntry(\DOMElement $item, string $base_path, int $level, string $navType = ''): ?array
    {
        $element = $this->firstNavListItemLabelElement($item, 'a');
        $isAnchor = $element instanceof \DOMElement && $element->localName === 'a';
        if (!$isAnchor && $this->specializedNavEntryRequiresLink($navType)) {
            return null;
        }
        if (!$isAnchor) {
            $element = $this->firstNavListItemLabelElement($item, 'span');
        }
        if (!$element instanceof \DOMElement) {
            return null;
        }

        $text = $this->xhtmlNavLabelText($element);
        if ($text === '') {
            return null;
        }

        $entry = [
            'text' => $text,
        ];
        if ($isAnchor) {
            $href = html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($href === '') {
                return null;
            }
            $entry['href'] = $this->rewriteRelativeResourceUrl($href, $base_path);
        }
        $entry['level'] = $level;
        $type = strtolower(trim($this->attributeByLocalName($element, 'type')));
        if ($type === '') {
            $type = strtolower(trim($this->attributeByLocalName($item, 'type')));
        }
        if ($type !== '') {
            $entry['type'] = $type;
        }
        if ($navType === 'page-list') {
            $value = trim($this->attributeByLocalName($element, 'value'));
            if ($value === '') {
                $value = trim($this->attributeByLocalName($item, 'value'));
            }
            $entry['value'] = $value !== '' ? $value : $text;
        }
        $this->addXhtmlNavLabelAttributes($entry, $element, $item);
        $itemAttributes = $this->xhtmlNavListItemAttributes($item);
        if ($itemAttributes !== []) {
            $entry['itemAttributes'] = $itemAttributes;
        }

        return $entry;
    }

    private function specializedNavEntryRequiresLink(string $navType): bool
    {
        return in_array($navType, ['landmarks', 'page-list'], true);
    }

    private function xhtmlNavLabelText(\DOMElement $element): string
    {
        $text = $this->normalizedXhtmlText($element->textContent);
        if ($text !== '') {
            return $text;
        }

        foreach (['aria-label', 'title'] as $attribute) {
            $value = $this->normalizedXhtmlText($this->attributeByLocalName($element, $attribute));
            if ($value !== '') {
                return $value;
            }
        }

        return $this->xhtmlNavDescendantTextAlternative($element);
    }

    private function xhtmlNavDescendantTextAlternative(\DOMElement $element): string
    {
        $parts = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = $this->normalizedXhtmlText($child->textContent);
                if ($text !== '') {
                    $parts[] = $text;
                }
                continue;
            }
            if (!$child instanceof \DOMElement || in_array($child->localName, ['ol', 'ul'], true)) {
                continue;
            }

            $text = '';
            foreach ($this->xhtmlNavTextAlternativeAttributes($child) as $attribute) {
                $text = $this->normalizedXhtmlText($this->attributeByLocalName($child, $attribute));
                if ($text !== '') {
                    break;
                }
            }
            if ($text === '') {
                $text = $this->xhtmlNavDescendantTextAlternative($child);
            }
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $this->normalizedXhtmlText(implode(' ', $parts));
    }

    /**
     * @return list<string>
     */
    private function xhtmlNavTextAlternativeAttributes(\DOMElement $element): array
    {
        return match ($element->localName) {
            'img', 'area', 'input' => ['alt', 'aria-label', 'title'],
            default => ['aria-label', 'title'],
        };
    }

    private function normalizedXhtmlText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8')) ?? $text);
    }

    /**
     * @return array<string, mixed>
     */
    private function xhtmlNavListItemAttributes(\DOMElement $item): array
    {
        $attributes = [];
        $type = strtolower(trim($this->attributeByLocalName($item, 'type')));
        if ($type !== '') {
            $attributes['type'] = html_entity_decode($type, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $id = $this->validNavSectionId(trim($this->attributeByLocalName($item, 'id')));
        if ($id !== '') {
            $attributes['id'] = $id;
        }

        foreach ([
            'role' => 'role',
            'title' => 'title',
            'ariaLabel' => 'aria-label',
        ] as $key => $attribute) {
            $value = trim($this->attributeByLocalName($item, $attribute));
            if ($value !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        $classes = $this->attributeTokenList($item, 'class');
        if ($classes !== []) {
            $attributes['classes'] = $classes;
        }

        $language = trim($item->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($item->getAttribute('lang'));
        }
        if ($this->validLanguageTag($language)) {
            $attributes['lang'] = $language;
        }

        $direction = strtolower(trim($item->getAttribute('dir')));
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $attributes['dir'] = $direction;
        }
        if ($item->hasAttribute('hidden')) {
            $attributes['hidden'] = true;
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function addXhtmlNavLabelAttributes(array &$entry, \DOMElement $element, \DOMElement $item): void
    {
        foreach ([
            'id' => 'id',
            'title' => 'title',
            'role' => 'role',
            'ariaLabel' => 'aria-label',
        ] as $key => $attribute) {
            $value = trim($this->attributeByLocalName($element, $attribute));
            if ($value === '' && in_array($key, ['id', 'title'], true)) {
                $value = trim($this->attributeByLocalName($item, $attribute));
            }
            if ($value !== '') {
                $entry[$key] = $value;
            }
        }

        $classes = $this->attributeTokenList($element, 'class');
        if ($classes !== []) {
            $entry['classes'] = $classes;
        }
        $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($language === '') {
            $language = trim($element->getAttribute('lang'));
        }
        if ($language === '') {
            $language = trim($item->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        }
        if ($language === '') {
            $language = trim($item->getAttribute('lang'));
        }
        if ($this->validLanguageTag($language)) {
            $entry['lang'] = $language;
        }

        $direction = strtolower(trim($element->getAttribute('dir')));
        if ($direction === '') {
            $direction = strtolower(trim($item->getAttribute('dir')));
        }
        if (in_array($direction, ['ltr', 'rtl', 'auto'], true)) {
            $entry['dir'] = $direction;
        }
        if ($element->hasAttribute('hidden') || $item->hasAttribute('hidden')) {
            $entry['hidden'] = true;
        }

        if ($element->localName === 'a') {
            foreach ([
                'rel' => 'rel',
                'hreflang' => 'hreflang',
                'media' => 'media',
                'target' => 'target',
            ] as $key => $attribute) {
                $value = trim($this->attributeByLocalName($element, $attribute));
                if ($value !== '') {
                    $entry[$key] = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }
    }

    private function firstNavListItemLabelElement(\DOMElement $item, string $localName): ?\DOMElement
    {
        foreach ($item->childNodes as $child) {
            if (!$child instanceof \DOMElement || in_array($child->localName, ['ol', 'ul'], true)) {
                continue;
            }

            $match = $this->firstNavLabelElement($child, $localName);
            if ($match instanceof \DOMElement) {
                return $match;
            }
        }

        return null;
    }

    private function firstNavLabelElement(\DOMElement $element, string $localName): ?\DOMElement
    {
        if ($element->localName === $localName) {
            return $element;
        }
        if (in_array($element->localName, ['ol', 'ul'], true)) {
            return null;
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $match = $this->firstNavLabelElement($child, $localName);
            if ($match instanceof \DOMElement) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function ncxMetadata(string $xml, string $path): array
    {
        $dom = $this->loadXml($xml, 'EPUB NCX table of contents');
        $metadata = [
            'path' => $path,
        ];
        $head_records = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'meta') {
                continue;
            }

            $name = trim($element->getAttribute('name'));
            $content = trim($element->getAttribute('content'));
            if ($name === '' && $content === '') {
                continue;
            }

            $record = [];
            if ($name !== '') {
                $record['name'] = $name;
            }
            if ($content !== '') {
                $record['content'] = $content;
            }
            foreach (['id', 'scheme'] as $attribute) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $record[$attribute] = $value;
                }
            }
            $head_records[] = $record;

            switch (strtolower($name)) {
                case 'dtb:uid':
                    if ($content !== '') {
                        $metadata['uid'] = $content;
                    }
                    break;
                case 'dtb:depth':
                    $depth = $this->positiveXmlInteger($content);
                    if ($depth !== null) {
                        $metadata['depth'] = $depth;
                    }
                    break;
                case 'dtb:totalpagecount':
                    $total = $this->nonNegativeXmlInteger($content);
                    if ($total !== null) {
                        $metadata['totalPageCount'] = $total;
                    }
                    break;
                case 'dtb:maxpagenumber':
                    $max = $this->nonNegativeXmlInteger($content);
                    if ($max !== null) {
                        $metadata['maxPageNumber'] = $max;
                    }
                    break;
            }
        }
        if ($head_records !== []) {
            $metadata['head'] = $head_records;
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'docTitle') {
                continue;
            }
            $title = trim(preg_replace('/\s+/u', ' ', $this->firstDescendantText($element, 'text')) ?? $this->firstDescendantText($element, 'text'));
            if ($title !== '') {
                $metadata['docTitle'] = $title;
            }
            $language = $this->ncxLabelLanguage($element);
            if ($language !== '') {
                $metadata['docTitleLang'] = $language;
            }
            break;
        }

        $authors = [];
        $author_records = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'docAuthor') {
                continue;
            }
            $author = trim(preg_replace('/\s+/u', ' ', $this->firstDescendantText($element, 'text')) ?? $this->firstDescendantText($element, 'text'));
            if ($author !== '') {
                $authors[] = $author;
                $record = ['text' => $author];
                $language = $this->ncxLabelLanguage($element);
                if ($language !== '') {
                    $record['lang'] = $language;
                }
                $author_records[] = $record;
            }
        }
        if ($authors !== []) {
            $metadata['docAuthors'] = array_values(array_unique($authors));
        }
        if ($author_records !== [] && array_filter($author_records, static fn (array $record): bool => isset($record['lang'])) !== []) {
            $metadata['docAuthorRecords'] = $author_records;
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'pageList') {
                continue;
            }
            $label = $this->firstChildElement($element, 'navLabel');
            if ($label instanceof \DOMElement) {
                $label_text = $this->ncxNavLabelText($label);
                if ($label_text !== '') {
                    $metadata['pageListLabel'] = $label_text;
                }
                $language = $this->ncxLabelLanguage($label);
                if ($language !== '') {
                    $metadata['pageListLabelLang'] = $language;
                }
            }
            break;
        }

        return count($metadata) > 1 ? $metadata : [];
    }

    private function ncxContentSrcPathDiagnosticReason(string $src): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }
        if (str_starts_with($src, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($src)) {
            return '';
        }
        if (str_starts_with($src, '/')) {
            return 'absolute-path';
        }
        if (str_contains($src, '\\')) {
            return 'backslash';
        }

        [$srcPath, $suffix] = $this->splitUrlPathSuffix($src);
        if (trim($srcPath) === '' && $suffix !== '') {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($srcPath);
    }

    private function ncxContentSrcFragmentDiagnosticReason(string $src): string
    {
        $src = trim($src);
        if ($src === '' || !str_contains($src, '#') || $this->isAbsoluteUrl($src) || str_starts_with($src, '#')) {
            return '';
        }

        $fragment = $this->urlFragmentIdentifier($src);
        if ($fragment === '') {
            return 'empty-fragment';
        }
        if (preg_match('/\s/u', $fragment) === 1) {
            return 'invalid-fragment';
        }

        return '';
    }

    private function ncxNavigationEntryHref(string $href, string $base_path): string
    {
        if ($this->ncxContentSrcPathDiagnosticReason($href) !== '') {
            return $href;
        }

        return $this->rewriteRelativeResourceUrl($href, $base_path);
    }

    /**
     * @return list<array{text: string, href: string, level: int, type?: string}>
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
     * @return list<array{text: string, href: string, level: int, type?: string}>
     */
    private function ncxNavPointEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'navPoint') {
                continue;
            }

            $label = $this->firstChildElement($child, 'navLabel');
            $text = $label instanceof \DOMElement ? $this->ncxNavLabelText($label) : '';
            $content = $this->firstChildElement($child, 'content');
            $href = $content instanceof \DOMElement ? html_entity_decode($content->getAttribute('src'), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            if ($text !== '' && $href !== '') {
                $entry = [
                    'text' => $text,
                    'href' => $this->ncxNavigationEntryHref($href, $base_path),
                    'level' => $level,
                ];
                $id = trim($child->getAttribute('id'));
                if ($id !== '') {
                    $entry['id'] = $id;
                }
                $type = trim($child->getAttribute('class'));
                if ($type !== '') {
                    $entry['type'] = $type;
                }
                $language = $this->ncxNavLabelLanguage($child);
                if ($language !== '') {
                    $entry['lang'] = $language;
                }
                $play_order = $this->positiveXmlInteger($child->getAttribute('playOrder'));
                if ($play_order !== null) {
                    $entry['playOrder'] = $play_order;
                }
                $entries[] = $entry;
            }
            array_push($entries, ...$this->ncxNavPointEntries($child, $base_path, $level + 1));
        }

        return $entries;
    }

    /**
     * @return list<array{text: string, href: string, level: int, type?: string, value?: string}>
     */
    private function ncxPageListEntries(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB NCX table of contents');
        $page_list = null;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'pageList') {
                $page_list = $element;
                break;
            }
        }

        return $page_list instanceof \DOMElement ? $this->ncxPageTargetEntries($page_list, $base_path, 1) : [];
    }

    /**
     * @return list<array{text: string, href: string, level: int, type?: string, value?: string}>
     */
    private function ncxPageTargetEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'pageTarget') {
                continue;
            }

            $label = $this->firstChildElement($child, 'navLabel');
            $text = $label instanceof \DOMElement ? $this->ncxNavLabelText($label) : '';
            $content = $this->firstChildElement($child, 'content');
            $href = $content instanceof \DOMElement ? html_entity_decode($content->getAttribute('src'), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            if ($text !== '' && $href !== '') {
                $entry = [
                    'text' => $text,
                    'href' => $this->ncxNavigationEntryHref($href, $base_path),
                    'level' => $level,
                ];
                $id = trim($child->getAttribute('id'));
                if ($id !== '') {
                    $entry['id'] = $id;
                }
                $type = trim($child->getAttribute('type'));
                if ($type !== '') {
                    $entry['type'] = $type;
                }
                $value = trim($child->getAttribute('value'));
                if ($value !== '') {
                    $entry['value'] = $value;
                }
                $language = $this->ncxNavLabelLanguage($child);
                if ($language !== '') {
                    $entry['lang'] = $language;
                }
                $play_order = $this->positiveXmlInteger($child->getAttribute('playOrder'));
                if ($play_order !== null) {
                    $entry['playOrder'] = $play_order;
                }
                $entries[] = $entry;
            }
            array_push($entries, ...$this->ncxPageTargetEntries($child, $base_path, $level + 1));
        }

        return $entries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ncxNavLists(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB NCX navigation lists');
        $lists = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'navList') {
                continue;
            }

            $label = $this->firstChildElement($element, 'navLabel');
            $label_text = $label instanceof \DOMElement ? $this->ncxNavLabelText($label) : '';
            $entries = $this->ncxNavTargetEntries($element, $base_path, 1);
            if ($label_text === '' && $entries === []) {
                continue;
            }

            $list = [
                'label' => $label_text,
                'entries' => $entries,
            ];
            $id = trim($element->getAttribute('id'));
            if ($id !== '') {
                $list['id'] = $id;
            }
            $type = trim($element->getAttribute('class'));
            if ($type !== '') {
                $list['type'] = $type;
            }
            if ($label instanceof \DOMElement) {
                $language = $this->ncxLabelLanguage($label);
                if ($language !== '') {
                    $list['lang'] = $language;
                }
            }
            $lists[] = $list;
        }

        return $lists;
    }

    /**
     * @return list<array{text: string, href: string, level: int, type?: string, value?: string}>
     */
    private function ncxNavTargetEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'navTarget') {
                continue;
            }

            $label = $this->firstChildElement($child, 'navLabel');
            $text = $label instanceof \DOMElement ? $this->ncxNavLabelText($label) : '';
            $content = $this->firstChildElement($child, 'content');
            $href = $content instanceof \DOMElement ? html_entity_decode($content->getAttribute('src'), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            if ($text !== '' && $href !== '') {
                $entry = [
                    'text' => $text,
                    'href' => $this->ncxNavigationEntryHref($href, $base_path),
                    'level' => $level,
                ];
                $id = trim($child->getAttribute('id'));
                if ($id !== '') {
                    $entry['id'] = $id;
                }
                $type = trim($child->getAttribute('class'));
                if ($type !== '') {
                    $entry['type'] = $type;
                }
                $value = trim($child->getAttribute('value'));
                if ($value !== '') {
                    $entry['value'] = $value;
                }
                $language = $this->ncxNavLabelLanguage($child);
                if ($language !== '') {
                    $entry['lang'] = $language;
                }
                $play_order = $this->positiveXmlInteger($child->getAttribute('playOrder'));
                if ($play_order !== null) {
                    $entry['playOrder'] = $play_order;
                }
                $entries[] = $entry;
            }
            array_push($entries, ...$this->ncxNavTargetEntries($child, $base_path, $level + 1));
        }

        return $entries;
    }

    private function ncxNavLabelLanguage(\DOMElement $entry): string
    {
        $label = $this->firstChildElement($entry, 'navLabel');
        if (!$label instanceof \DOMElement) {
            return '';
        }

        return $this->ncxLabelLanguage($label);
    }

    private function ncxNavLabelText(\DOMElement $label): string
    {
        $text = $this->firstDescendantText($label, 'text');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function ncxLabelLanguage(\DOMElement $label): string
    {
        foreach ([$label, $this->firstDescendantElement($label, 'text')] as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $language = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
            if ($language === '') {
                $language = trim($element->getAttribute('lang'));
            }
            if ($this->validLanguageTag($language)) {
                return $language;
            }
        }

        return '';
    }

    private function firstChildElement(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function positiveXmlInteger(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[0-9]+$/', $value) !== 1) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    private function nonNegativeXmlInteger(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[0-9]+$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array{present: bool, first: bool, firstEntry?: string, readable?: bool, bytes?: int, compressedBytes?: int, crc32?: int, compressionMethod?: int, encryptionMethod?: int, mediaType?: string, localHeaderName?: string, localHeaderExtraBytes?: int}
     */
    private function ocfMimetype(\ZipArchive $zip, string $path = ''): array
    {
        $firstEntry = $zip->numFiles > 0 ? $zip->getNameIndex(0) : false;
        $entry = [
            'present' => false,
            'first' => $firstEntry === 'mimetype',
        ];
        if (is_string($firstEntry) && $firstEntry !== '') {
            $entry['firstEntry'] = $firstEntry;
        }
        if ($path !== '') {
            $localHeader = $this->zipFirstLocalFileHeader($path);
            if ($localHeader !== null) {
                $entry['localHeaderName'] = $localHeader['name'];
                $entry['localHeaderExtraBytes'] = $localHeader['extraBytes'];
            }
        }

        $stat = $zip->statName('mimetype');
        if (!is_array($stat)) {
            return $entry;
        }

        $entry['present'] = true;
        foreach ([
            'bytes' => 'size',
            'compressedBytes' => 'comp_size',
            'crc32' => 'crc',
            'compressionMethod' => 'comp_method',
            'encryptionMethod' => 'encryption_method',
        ] as $key => $statKey) {
            if (isset($stat[$statKey]) && is_int($stat[$statKey])) {
                $entry[$key] = $stat[$statKey];
            }
        }

        $bytes = $zip->getFromName('mimetype');
        if (!is_string($bytes)) {
            $entry['readable'] = false;

            return $entry;
        }

        $entry['readable'] = true;
        $entry['mediaType'] = $bytes;

        return $entry;
    }

    /**
     * @param array<string, mixed> $mimetype
     * @return list<array<string, mixed>>
     */
    private function ocfMimetypeDiagnostics(array $mimetype): array
    {
        $diagnostics = [];
        if (($mimetype['present'] ?? false) !== true) {
            $context = [];
            if (isset($mimetype['firstEntry']) && is_string($mimetype['firstEntry']) && $mimetype['firstEntry'] !== '') {
                $context['firstEntry'] = $mimetype['firstEntry'];
            }

            return [
                $this->epubDiagnostic(
                    'error',
                    'missing-ocf-mimetype',
                    'EPUB OCF ZIP container must include a top-level mimetype file.',
                    $context
                ),
            ];
        }

        if (($mimetype['first'] ?? false) !== true) {
            $context = [];
            if (isset($mimetype['firstEntry']) && is_string($mimetype['firstEntry']) && $mimetype['firstEntry'] !== '') {
                $context['firstEntry'] = $mimetype['firstEntry'];
            }
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'ocf-mimetype-not-first',
                'EPUB OCF ZIP container must store the mimetype file as the first archive entry.',
                $context
            );
        }

        if (($mimetype['readable'] ?? true) === false) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'unreadable-ocf-mimetype',
                'EPUB OCF mimetype file could not be read from the archive.'
            );
        } elseif (($mimetype['mediaType'] ?? '') !== self::OCF_MIMETYPE) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'invalid-ocf-mimetype-content',
                'EPUB OCF mimetype file must contain exactly application/epub+zip.',
                [
                    'bytes' => is_string($mimetype['mediaType'] ?? null) ? strlen($mimetype['mediaType']) : null,
                    'expected' => self::OCF_MIMETYPE,
                    'value' => is_string($mimetype['mediaType'] ?? null) ? $mimetype['mediaType'] : '',
                ]
            );
        }

        if (isset($mimetype['compressionMethod']) && (int) $mimetype['compressionMethod'] !== 0) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'compressed-ocf-mimetype',
                'EPUB OCF mimetype file must be stored uncompressed.',
                ['compressionMethod' => (int) $mimetype['compressionMethod']]
            );
        }

        if (isset($mimetype['encryptionMethod']) && (int) $mimetype['encryptionMethod'] !== 0) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'encrypted-ocf-mimetype',
                'EPUB OCF mimetype file must not be encrypted.',
                ['encryptionMethod' => (int) $mimetype['encryptionMethod']]
            );
        }

        if (
            isset($mimetype['localHeaderExtraBytes'])
            && (int) $mimetype['localHeaderExtraBytes'] > 0
            && (($mimetype['localHeaderName'] ?? '') === 'mimetype' || ($mimetype['first'] ?? false) === true)
        ) {
            $diagnostics[] = $this->epubDiagnostic(
                'error',
                'ocf-mimetype-extra-field',
                'EPUB OCF mimetype file local ZIP header must not contain an extra field.',
                [
                    'extraBytes' => (int) $mimetype['localHeaderExtraBytes'],
                    'localHeaderName' => (string) ($mimetype['localHeaderName'] ?? ''),
                ]
            );
        }

        return $diagnostics;
    }

    /**
     * @return array{name: string, extraBytes: int}|null
     */
    private function zipFirstLocalFileHeader(string $path): ?array
    {
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) {
            return null;
        }

        try {
            $header = fread($handle, 30);
            if (!is_string($header) || strlen($header) < 30) {
                return null;
            }

            $fields = unpack(
                'Vsignature/vversionNeeded/vflags/vcompressionMethod/vmodTime/vmodDate/Vcrc32/VcompressedSize/VuncompressedSize/vfileNameLength/vextraFieldLength',
                $header
            );
            if (!is_array($fields) || ($fields['signature'] ?? null) !== 0x04034b50) {
                return null;
            }

            $nameLength = (int) ($fields['fileNameLength'] ?? 0);
            $extraLength = (int) ($fields['extraFieldLength'] ?? 0);
            if ($nameLength <= 0 || $nameLength > 65535 || $extraLength < 0 || $extraLength > 65535) {
                return null;
            }

            $name = fread($handle, $nameLength);
            if (!is_string($name) || strlen($name) !== $nameLength) {
                return null;
            }

            return [
                'name' => $name,
                'extraBytes' => $extraLength,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<array<string, mixed>> $sidecars
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spine_items
     * @return list<array<string, mixed>>
     */
    private function ocfSidecarDiagnostics(\ZipArchive $zip, array $sidecars, array $manifest, string $base_path, array $spine_items): array
    {
        $diagnostics = [];
        $manifestByPath = $this->manifestResourcesByZipPath($manifest, $base_path);
        $linearSpinePaths = $this->linearSpineResourcePaths($manifest, $base_path, $spine_items);

        foreach ($sidecars as $sidecar) {
            $path = (string) ($sidecar['path'] ?? '');
            $kind = (string) ($sidecar['kind'] ?? '');
            if ($path === '' || $kind === '') {
                continue;
            }

            $context = ['kind' => $kind, 'path' => $path];
            if (($sidecar['xmlReadable'] ?? true) === false) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'unreadable-ocf-sidecar',
                    'EPUB OCF sidecar XML could not be read from the archive.',
                    $context
                );
                continue;
            }

            if (($sidecar['xmlWellFormed'] ?? true) === false) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'malformed-ocf-sidecar',
                    'EPUB OCF sidecar XML is not well-formed.',
                    $context
                );
                continue;
            }

            if (($sidecar['rootValid'] ?? true) === false) {
                $rootContext = $context;
                if (isset($sidecar['rootName']) && is_string($sidecar['rootName']) && $sidecar['rootName'] !== '') {
                    $rootContext['rootName'] = $sidecar['rootName'];
                }
                if (isset($sidecar['rootNamespace']) && is_string($sidecar['rootNamespace']) && $sidecar['rootNamespace'] !== '') {
                    $rootContext['rootNamespace'] = $sidecar['rootNamespace'];
                }
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ocf-sidecar-root',
                    'EPUB OCF sidecar XML uses an unexpected document root.',
                    $rootContext
                );
            }

            if ($kind !== 'encryption' && $kind !== 'signatures') {
                continue;
            }

            $xml = $zip->getFromName($path);
            if (!is_string($xml)) {
                continue;
            }

            try {
                $dom = $this->loadXml($xml, 'EPUB OCF sidecar ' . $path);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($kind === 'encryption') {
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->ocfEncryptionDiagnostics($zip, $dom, $context, $manifestByPath, $linearSpinePaths)
                );
                continue;
            }

            $diagnostics = array_merge(
                $diagnostics,
                $this->ocfSignatureDiagnostics($zip, $dom, $context)
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @return array<string, array{id: string, href: string, mediaType: string}>
     */
    private function manifestResourcesByZipPath(array $manifest, string $base_path): array
    {
        $resources = [];
        foreach ($manifest as $id => $item) {
            $path = $this->packageResourceZipPath($base_path, $item['href']);
            if ($path === '') {
                continue;
            }

            $resources[$path] = [
                'id' => $id,
                'href' => $item['href'],
                'mediaType' => $item['media-type'],
            ];
        }

        return $resources;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spine_items
     * @return array<string, array{idref: string, mediaType: string, fallbackIdref?: string}>
     */
    private function linearSpineResourcePaths(array $manifest, string $base_path, array $spine_items): array
    {
        $paths = [];
        foreach ($spine_items as $spine_item) {
            $idref = $spine_item['idref'];
            if (!$spine_item['linear'] || !isset($manifest[$idref])) {
                continue;
            }

            $path = $this->packageResourceZipPath($base_path, $manifest[$idref]['href']);
            if ($path === '') {
                continue;
            }

            $paths[$path] = [
                'idref' => $idref,
                'mediaType' => $manifest[$idref]['media-type'],
            ];

            $readable = $this->readableSpineManifestItem($manifest, $idref, $base_path);
            if ($readable === null || $readable['path'] === $path) {
                continue;
            }

            $paths[$readable['path']] = [
                'idref' => $idref,
                'mediaType' => $readable['item']['media-type'],
                'fallbackIdref' => $readable['idref'],
            ];
        }

        return $paths;
    }

    /**
     * @param array<string, mixed> $baseContext
     * @param array<string, array{id: string, href: string, mediaType: string}> $manifestByPath
     * @param array<string, array{idref: string, mediaType: string, fallbackIdref?: string}> $linearSpinePaths
     * @return list<array<string, mixed>>
     */
    private function ocfEncryptionDiagnostics(
        \ZipArchive $zip,
        \DOMDocument $dom,
        array $baseContext,
        array $manifestByPath,
        array $linearSpinePaths
    ): array {
        $diagnostics = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'EncryptedData') {
                continue;
            }

            $context = $this->ocfEncryptionElementContext($baseContext, $element);
            $cipherReference = $this->firstDescendantElement($element, 'CipherReference');
            $uri = $cipherReference instanceof \DOMElement
                ? html_entity_decode(trim($cipherReference->getAttribute('URI')), ENT_QUOTES | ENT_XML1, 'UTF-8')
                : '';
            if ($uri === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-ocf-encryption-cipher-reference',
                    'EPUB OCF encryption data is missing a CipherReference URI.',
                    $context
                );
                continue;
            }

            $uriPathReason = $this->ocfSidecarReferenceUriPathDiagnosticReason($uri);
            if ($uriPathReason !== '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'invalid-ocf-encryption-cipher-reference-uri-path',
                    'EPUB OCF encryption CipherReference URI must be an absolute URL or a path-relative scheme-less URL.',
                    $context + ['uri' => $uri, 'reason' => $uriPathReason]
                );
                continue;
            }

            $path = $this->isPackageRelativeResourceUrl($uri) ? $this->normalizeZipPath($uri) : '';
            $resourceContext = $context + ['uri' => $uri];
            if ($path !== '') {
                $resourceContext['resourcePath'] = $path;
            }

            if ($path !== '' && $zip->locateName($path) === false) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-ocf-encrypted-resource',
                    'EPUB OCF encryption CipherReference URI does not resolve to an archive resource.',
                    $resourceContext
                );
            }

            if ($path !== '' && isset($linearSpinePaths[$path]) && $this->isEpubContentDocumentMediaType($linearSpinePaths[$path]['mediaType'])) {
                $diagnostics[] = $this->epubDiagnostic(
                    'warning',
                    'encrypted-linear-spine-resource',
                    'EPUB linear spine content is listed as encrypted; native PHP EPUB reading preserves metadata but does not decrypt it.',
                    $resourceContext + [
                        'idref' => $linearSpinePaths[$path]['idref'],
                        'mediaType' => $linearSpinePaths[$path]['mediaType'],
                    ]
                );
            }

            if ($path !== '' && !isset($manifestByPath[$path]) && $zip->locateName($path) !== false) {
                $diagnostics[] = $this->epubDiagnostic(
                    'warning',
                    'unmanifested-ocf-encrypted-resource',
                    'EPUB OCF encryption CipherReference URI targets an archive resource outside the OPF manifest.',
                    $resourceContext
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $baseContext
     * @return array<string, mixed>
     */
    private function ocfEncryptionElementContext(array $baseContext, \DOMElement $element): array
    {
        $context = $baseContext + ['element' => $this->qualifiedName($element)];
        $id = trim($element->getAttribute('Id'));
        if ($id !== '') {
            $context['id'] = $id;
        }
        $type = trim($element->getAttribute('Type'));
        if ($type !== '') {
            $context['type'] = $type;
        }
        $method = $this->firstDescendantElement($element, 'EncryptionMethod');
        if ($method instanceof \DOMElement) {
            $algorithm = trim($method->getAttribute('Algorithm'));
            if ($algorithm !== '') {
                $context['algorithm'] = $algorithm;
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $baseContext
     * @return list<array<string, mixed>>
     */
    private function ocfSignatureDiagnostics(\ZipArchive $zip, \DOMDocument $dom, array $baseContext): array
    {
        $diagnostics = [];
        $signatures = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'Signature') {
                $signatures[] = $element;
            }
        }

        if ($signatures === []) {
            return [
                $this->epubDiagnostic(
                    'error',
                    'missing-ocf-signature',
                    'EPUB OCF signatures sidecar has no XML Signature elements.',
                    $baseContext
                ),
            ];
        }

        foreach ($signatures as $signature) {
            $signatureContext = $this->ocfSignatureElementContext($baseContext, $signature);
            $signedInfo = $this->firstChildElement($signature, 'SignedInfo');
            if (!$signedInfo instanceof \DOMElement) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-ocf-signature-signed-info',
                    'EPUB OCF XML Signature is missing SignedInfo.',
                    $signatureContext
                );
            }

            $signatureValue = $this->firstChildElement($signature, 'SignatureValue');
            if (!$signatureValue instanceof \DOMElement || trim($signatureValue->textContent) === '') {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-ocf-signature-value',
                    'EPUB OCF XML Signature is missing SignatureValue.',
                    $signatureContext
                );
            }

            if (!$signedInfo instanceof \DOMElement) {
                continue;
            }

            $references = $this->signatureReferenceElements($signedInfo);
            if ($references === []) {
                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-ocf-signature-reference',
                    'EPUB OCF XML Signature SignedInfo has no Reference elements.',
                    $signatureContext
                );
                continue;
            }

            foreach ($references as $reference) {
                $referenceContext = $this->ocfSignatureReferenceContext($signatureContext, $reference);
                $uri = html_entity_decode(trim($reference->getAttribute('URI')), ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($uri === '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'missing-ocf-signature-reference-uri',
                        'EPUB OCF XML Signature Reference is missing a URI.',
                        $referenceContext
                    );
                    continue;
                }

                $uriPathReason = $this->ocfSidecarReferenceUriPathDiagnosticReason($uri);
                if ($uriPathReason !== '') {
                    $diagnostics[] = $this->epubDiagnostic(
                        'error',
                        'invalid-ocf-signature-reference-uri-path',
                        'EPUB OCF XML Signature Reference URI must be an absolute URL or a path-relative scheme-less URL.',
                        $referenceContext + ['reason' => $uriPathReason]
                    );
                    continue;
                }

                if (!$this->isPackageRelativeResourceUrl($uri)) {
                    continue;
                }

                [$path] = $this->splitUrlPathSuffix($uri);
                $resourcePath = $this->normalizeZipPath($path);
                if ($resourcePath === '') {
                    continue;
                }

                if ($zip->locateName($resourcePath) !== false) {
                    continue;
                }

                $diagnostics[] = $this->epubDiagnostic(
                    'error',
                    'missing-ocf-signature-reference-resource',
                    'EPUB OCF XML Signature Reference URI does not resolve to an archive resource.',
                    $referenceContext + ['resourcePath' => $resourcePath]
                );
            }
        }

        return $diagnostics;
    }

    private function ocfSidecarReferenceUriPathDiagnosticReason(string $uri): string
    {
        $uri = trim($uri);
        if ($uri === '') {
            return '';
        }
        if (str_starts_with($uri, '//')) {
            return 'protocol-relative-url';
        }
        if ($this->isAbsoluteUrl($uri)) {
            return '';
        }
        if (str_starts_with($uri, '#')) {
            return '';
        }
        if (str_starts_with($uri, '/')) {
            return 'absolute-path';
        }
        if (str_contains($uri, '\\')) {
            return 'backslash';
        }

        [$uriPath, $suffix] = $this->splitUrlPathSuffix($uri);
        if (trim($uriPath) === '' && $suffix !== '') {
            return 'empty-path';
        }

        return $this->encodedDotSegmentPathDiagnosticReason($uriPath);
    }

    /**
     * @return list<\DOMElement>
     */
    private function signatureReferenceElements(\DOMElement $signedInfo): array
    {
        $references = [];
        foreach ($signedInfo->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'Reference') {
                $references[] = $element;
            }
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $baseContext
     * @return array<string, mixed>
     */
    private function ocfSignatureElementContext(array $baseContext, \DOMElement $element): array
    {
        $context = $baseContext + ['element' => $this->qualifiedName($element)];
        $id = trim($element->getAttribute('Id'));
        if ($id !== '') {
            $context['id'] = $id;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $signatureContext
     * @return array<string, mixed>
     */
    private function ocfSignatureReferenceContext(array $signatureContext, \DOMElement $reference): array
    {
        $context = $signatureContext + ['referenceElement' => $this->qualifiedName($reference)];
        $id = trim($reference->getAttribute('Id'));
        if ($id !== '') {
            $context['referenceId'] = $id;
        }
        $uri = html_entity_decode(trim($reference->getAttribute('URI')), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($uri !== '') {
            $context['uri'] = $uri;
        }
        $type = trim($reference->getAttribute('Type'));
        if ($type !== '') {
            $context['referenceType'] = $type;
        }

        return $context;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ocfSidecars(\ZipArchive $zip): array
    {
        $sidecars = [];
        foreach ($this->ocfSidecarConfig() as $path => $config) {
            $stat = $zip->statName($path);
            if (!is_array($stat)) {
                continue;
            }

            $entry = [
                'kind' => $config['kind'],
                'path' => $path,
            ];
            foreach ([
                'bytes' => 'size',
                'compressedBytes' => 'comp_size',
                'crc32' => 'crc',
                'compressionMethod' => 'comp_method',
            ] as $key => $statKey) {
                if (isset($stat[$statKey]) && is_int($stat[$statKey])) {
                    $entry[$key] = $stat[$statKey];
                }
            }

            $xml = $zip->getFromName($path);
            if (!is_string($xml)) {
                $entry['xmlReadable'] = false;
                $sidecars[] = $entry;
                continue;
            }

            $entry['xmlReadable'] = true;
            try {
                $dom = $this->loadXml($xml, 'EPUB OCF sidecar ' . $path);
            } catch (\InvalidArgumentException) {
                $entry['xmlWellFormed'] = false;
                $sidecars[] = $entry;
                continue;
            }

            $root = $dom->documentElement;
            $entry['xmlWellFormed'] = true;
            if ($root instanceof \DOMElement) {
                $entry['rootName'] = $root->localName;
                $entry['rootNamespace'] = $root->namespaceURI ?? '';
                $entry['rootValid'] = $root->localName === $config['rootName']
                    && ($config['rootNamespace'] === '' || $root->namespaceURI === $config['rootNamespace']);
            }
            if ($config['kind'] === 'encryption') {
                $items = $this->ocfEncryptionItems($dom);
                if ($items !== []) {
                    $entry['items'] = $items;
                    $entry['encryptedResources'] = array_values(array_unique(array_filter(
                        array_map(static fn (array $item): string => (string) ($item['path'] ?? ''), $items),
                        static fn (string $path): bool => $path !== ''
                    )));
                    $entry['encryptionAlgorithms'] = array_values(array_unique(array_filter(
                        array_map(static fn (array $item): string => (string) ($item['algorithm'] ?? ''), $items),
                        static fn (string $algorithm): bool => $algorithm !== ''
                    )));
                }
            }

            $sidecars[] = $entry;
        }

        return $sidecars;
    }

    /**
     * @return array<string, array{kind: string, rootName: string, rootNamespace: string}>
     */
    private function ocfSidecarConfig(): array
    {
        return [
            'META-INF/encryption.xml' => [
                'kind' => 'encryption',
                'rootName' => 'encryption',
                'rootNamespace' => self::OCF_CONTAINER_NAMESPACE,
            ],
            'META-INF/metadata.xml' => [
                'kind' => 'metadata',
                'rootName' => 'metadata',
                'rootNamespace' => self::OCF_CONTAINER_NAMESPACE,
            ],
            'META-INF/rights.xml' => [
                'kind' => 'rights',
                'rootName' => 'rights',
                'rootNamespace' => self::OCF_CONTAINER_NAMESPACE,
            ],
            'META-INF/signatures.xml' => [
                'kind' => 'signatures',
                'rootName' => 'signatures',
                'rootNamespace' => self::OCF_CONTAINER_NAMESPACE,
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function ocfEncryptionItems(\DOMDocument $dom): array
    {
        $items = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'EncryptedData') {
                continue;
            }

            $cipherReference = $this->firstDescendantElement($element, 'CipherReference');
            if (!$cipherReference instanceof \DOMElement) {
                continue;
            }
            $uri = html_entity_decode(trim($cipherReference->getAttribute('URI')), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($uri === '') {
                continue;
            }

            $item = ['uri' => $uri];
            if ($this->ocfSidecarReferenceUriPathDiagnosticReason($uri) === '') {
                $item['path'] = $this->isPackageRelativeResourceUrl($uri) ? $this->normalizeZipPath($uri) : $uri;
            }
            $id = trim($element->getAttribute('Id'));
            if ($id !== '') {
                $item['id'] = $id;
            }
            $type = trim($element->getAttribute('Type'));
            if ($type !== '') {
                $item['type'] = $type;
            }
            $method = $this->firstDescendantElement($element, 'EncryptionMethod');
            if ($method instanceof \DOMElement) {
                $algorithm = trim($method->getAttribute('Algorithm'));
                if ($algorithm !== '') {
                    $item['algorithm'] = $algorithm;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $sidecars
     * @return array<string, array{kind: string, encoding: string, bytes: int, data: string}>
     */
    private function extractedOcfSidecarPayloads(\ZipArchive $zip, array $sidecars): array
    {
        $payloads = [];
        $max_bytes = $this->resourceMaxBytes();
        foreach ($sidecars as $sidecar) {
            $path = (string) ($sidecar['path'] ?? '');
            $kind = (string) ($sidecar['kind'] ?? '');
            if ($path === '' || $kind === '') {
                continue;
            }
            $bytes = $zip->getFromName($path);
            if (!is_string($bytes) || strlen($bytes) > $max_bytes) {
                continue;
            }
            $payloads[$path] = [
                'kind' => $kind,
                'encoding' => 'base64',
                'bytes' => strlen($bytes),
                'data' => base64_encode($bytes),
            ];
        }

        return $payloads;
    }

    /**
     * @param list<array<string, mixed>> $rootfiles
     * @return array<string, array<string, mixed>>
     */
    private function alternateContainerRootfilePackages(\ZipArchive $zip, array $rootfiles, string $selectedRootfile): array
    {
        $packages = [];
        $selectedRootfile = $this->normalizeZipPath($selectedRootfile);
        foreach ($rootfiles as $rootfile) {
            if (!is_array($rootfile)) {
                continue;
            }
            $path = $this->normalizeZipPath((string) ($rootfile['path'] ?? $rootfile['fullPath'] ?? ''));
            if ($path === '' || $path === $selectedRootfile || isset($packages[$path])) {
                continue;
            }

            $media_type = trim((string) ($rootfile['mediaType'] ?? ''));
            if (!$this->mediaTypeMatches($media_type, self::OPF_MEDIA_TYPE) && !str_ends_with(strtolower($path), '.opf')) {
                continue;
            }

            $xml = $zip->getFromName($path);
            if (!is_string($xml)) {
                continue;
            }

            $entry = [
                'path' => $path,
                'mediaType' => $media_type !== '' ? $media_type : self::OPF_MEDIA_TYPE,
                'bytes' => strlen($xml),
            ];
            foreach (['id', 'fullPath'] as $key) {
                $value = trim((string) ($rootfile[$key] ?? ''));
                if ($value !== '') {
                    $entry[$key] = $value;
                }
            }
            $properties = $rootfile['properties'] ?? [];
            if (is_array($properties)) {
                $properties = array_values(array_filter(
                    array_map(static fn (mixed $property): string => trim((string) $property), $properties),
                    static fn (string $property): bool => $property !== ''
                ));
                if ($properties !== []) {
                    $entry['properties'] = $properties;
                }
            }

            try {
                $dom = $this->loadXml($xml, 'EPUB alternate OPF package ' . $path);
            } catch (\InvalidArgumentException) {
                $entry['xmlWellFormed'] = false;
                $packages[$path] = $entry;
                continue;
            }

            $package = $dom->documentElement;
            $entry['xmlWellFormed'] = true;
            if (!$package instanceof \DOMElement) {
                $entry['rootValid'] = false;
                $packages[$path] = $entry;
                continue;
            }

            $entry['rootName'] = $package->localName;
            $entry['rootNamespace'] = $package->namespaceURI ?? '';
            $entry['rootValid'] = $this->isOpfPackageElement($package, 'package');
            if ($entry['rootValid']) {
                $entry += $this->alternatePackageSummary($zip, $package, $this->dirname($path), $path);
            }

            $packages[$path] = $entry;
        }

        return $packages;
    }

    /**
     * @return array<string, mixed>
     */
    private function alternatePackageSummary(\ZipArchive $zip, \DOMElement $package, string $basePath, string $rootfile): array
    {
        $summary = [];
        foreach ($this->packageAttributes($package) as $key => $value) {
            $summary[$key] = $value;
        }

        $packageMetadata = $this->metadata($package, $basePath);
        unset($packageMetadata['titleInlines']);
        foreach ([
            'identifier',
            'title',
            'lang',
            'author',
            'contributor',
            'subject',
            'description',
            'publisher',
            'date',
            'type',
            'format',
            'source',
            'relation',
            'coverage',
            'rights',
            'epubDublinCoreMetadata',
            'epubMetadataProperties',
            'epubMetadataLinks',
            'epubProperties',
            'epubCoverItemId',
            'epubRenditionLayout',
            'epubRenditionOrientation',
            'epubRenditionSpread',
            'epubRenditionFlow',
            'epubRenditionViewport',
            'epubViewport',
            'epubMediaDuration',
            'epubMediaNarrator',
            'epubMediaActiveClass',
            'epubMediaPlaybackActiveClass',
        ] as $key) {
            if (array_key_exists($key, $packageMetadata)) {
                $summary[$key] = $packageMetadata[$key];
            }
        }

        $manifest = $this->manifest($package);
        $manifestResources = $this->manifestResources($basePath, $manifest);
        $manifestResourcesByPath = $this->manifestResourcesByPath($manifestResources);
        $packageLinkResources = $this->packageLinkResourceEntries($zip, $package, $basePath, $rootfile, $manifestResourcesByPath);
        $assetResources = $this->assetResourceEntries($manifestResources);
        $imageResources = $this->imageResources($basePath, $manifest);
        $bindings = $this->bindings($package);
        $collections = $this->collections($package, $basePath);
        $spineItems = $this->spineItems($package);
        $spineMetadata = $this->spineMetadata($package);
        $guideReferences = $this->guideReferences($package, $basePath);
        $coverImage = $this->coverImageResource($basePath, $manifest, $packageMetadata, $guideReferences);
        $mediaOverlays = $this->mediaOverlays($zip, $basePath, $manifest, $this->mediaOverlayMetadata($summary['epubMetadataProperties'] ?? []));
        $linearSpineItems = array_values(array_filter($spineItems, static fn (array $item): bool => (bool) ($item['linear'] ?? false)));

        $summary['epubManifestResources'] = $manifestResources;
        $summary['epubManifestResourceCount'] = count($manifestResources);
        $summary['epubAssetResources'] = array_map(static fn (array $resource): string => $resource['path'], $assetResources);
        if ($imageResources !== []) {
            $summary['epubImageResources'] = $imageResources;
        }
        if ($coverImage !== '') {
            $summary['epubCoverImage'] = $coverImage;
        }
        if ($guideReferences !== []) {
            $summary['epubGuideReferences'] = $guideReferences;
        }
        if ($packageLinkResources !== []) {
            $summary['epubPackageLinkResources'] = $packageLinkResources;
        }
        if ($mediaOverlays !== []) {
            $summary['epubMediaOverlayCount'] = count($mediaOverlays);
            $summary['epubMediaOverlays'] = $mediaOverlays;
            $summary['epubMediaOverlayResources'] = array_values(array_unique(array_filter(
                array_map(static fn (array $overlay): string => (string) ($overlay['overlayPath'] ?? ''), $mediaOverlays),
                static fn (string $path): bool => $path !== ''
            )));
        }
        $resourceReferencedResources = [];
        if ($this->extractResources()) {
            $extractedResources = $this->extractedResourcePayloads(
                $zip,
                $this->resourceEntriesForPayloadExtraction($assetResources, $packageLinkResources),
                $resourceReferencedResources
            );
            $summary['epubResourcePayloads'] = $extractedResources['payloads'];
            $summary['epubExtractedResourceCount'] = count($extractedResources['payloads']);
            $summary['epubExtractedResourceBytes'] = $extractedResources['bytes'];
            if ($extractedResources['skipped'] !== []) {
                $summary['epubSkippedResourcePayloads'] = $extractedResources['skipped'];
            }
        }
        if ($bindings !== []) {
            $summary['epubBindings'] = $bindings;
        }
        if ($collections !== []) {
            $summary['epubCollections'] = $collections;
        }
        $summary['epubSpineItems'] = count($spineItems);
        $summary['epubLinearSpineItems'] = count($linearSpineItems);
        $summary['epubNonLinearSpineItems'] = count($spineItems) - count($linearSpineItems);
        $nonLinearResources = [];
        $fallbackSpineResources = [];
        foreach ($spineItems as $spineItem) {
            $idref = (string) ($spineItem['idref'] ?? '');
            if (($spineItem['linear'] ?? false) || $idref === '' || !isset($manifest[$idref])) {
                continue;
            }
            $path = $this->packageResourcePath($basePath, $manifest[$idref]['href']);
            $nonLinearResources[] = $path;
            $readableItem = $this->readableSpineManifestItem($manifest, $idref, $basePath);
            if ($readableItem !== null && $readableItem['idref'] !== $idref) {
                $fallbackSpineResources[] = [
                    'idref' => $idref,
                    'path' => $path,
                    'mediaType' => $this->manifestResourceMediaType($path, $manifest[$idref]['media-type']),
                    'fallbackIdref' => $readableItem['idref'],
                    'fallbackPath' => $readableItem['path'],
                    'fallbackMediaType' => $this->manifestResourceMediaType($readableItem['path'], $readableItem['item']['media-type']),
                ];
            }
        }
        if ($nonLinearResources !== []) {
            $summary['epubNonLinearResources'] = array_values(array_unique($nonLinearResources));
        }
        if ($fallbackSpineResources !== []) {
            $summary['epubFallbackSpineResources'] = $this->uniqueFallbackSpineResources($fallbackSpineResources);
            $summary['epubFallbackSpineResourceCount'] = count($summary['epubFallbackSpineResources']);
        }
        $spineXhtmlMetadata = [];
        $xhtmlDiagnostics = [];
        $bodySummary = $this->alternatePackageReadableBodySummary(
            $zip,
            $manifest,
            $spineItems,
            $basePath,
            $manifestResourcesByPath,
            $spineXhtmlMetadata,
            $xhtmlDiagnostics
        );
        $spineItemRefs = $this->spineItemMetadata(
            $spineItems,
            $manifest,
            $basePath,
            $spineXhtmlMetadata,
            $this->spineRenditionMetadata($summary['epubMetadataProperties'] ?? [])
        );
        if ($spineItemRefs !== []) {
            $summary['epubSpineItemRefs'] = $spineItemRefs;
            $viewports = array_values(array_filter(
                array_map(static fn (array $item): mixed => $item['viewport'] ?? null, $spineItemRefs),
                static fn (mixed $viewport): bool => is_array($viewport)
            ));
            if ($viewports !== []) {
                $summary['epubViewports'] = $viewports;
                $summary['epubViewport'] = $viewports[0];
            }
        }
        if ($spineMetadata['id'] !== '') {
            $summary['epubSpineId'] = $spineMetadata['id'];
        }
        if ($spineMetadata['pageProgressionDirection'] !== '') {
            $summary['epubPageProgressionDirection'] = $spineMetadata['pageProgressionDirection'];
        }
        if ($spineMetadata['toc'] !== '') {
            $summary['epubSpineTocId'] = $spineMetadata['toc'];
        }
        $navigation = $this->navigation($zip, $basePath, $manifest, $spineMetadata['toc']);
        $navigation = $this->navigationWithGuideDerivedLandmarks($navigation, $zip, $package, $spineItems, $manifest, $basePath);
        $summary = $this->alternateNavigationSummary($summary, $navigation);
        $diagnostics = $this->alternatePackageDiagnostics(
            $zip,
            $package,
            $manifest,
            $spineItems,
            $basePath,
            $rootfile,
            $navigation
        );
        $diagnostics = array_merge($diagnostics, $xhtmlDiagnostics);
        if ($diagnostics !== []) {
            $summary['epubDiagnostics'] = $diagnostics;
            $summary['epubDiagnosticCount'] = count($diagnostics);
            $summary['epubDiagnosticErrorCount'] = count(array_filter(
                $diagnostics,
                static fn (array $diagnostic): bool => ($diagnostic['severity'] ?? '') === 'error'
            ));
            $summary['epubDiagnosticWarningCount'] = count(array_filter(
                $diagnostics,
                static fn (array $diagnostic): bool => ($diagnostic['severity'] ?? '') === 'warning'
            ));
        }
        if ($bodySummary !== []) {
            if (
                isset($summary['epubFallbackSpineResources'], $bodySummary['epubFallbackSpineResources'])
                && is_array($summary['epubFallbackSpineResources'])
                && is_array($bodySummary['epubFallbackSpineResources'])
            ) {
                $summary['epubFallbackSpineResources'] = $this->uniqueFallbackSpineResources(array_merge(
                    $summary['epubFallbackSpineResources'],
                    $bodySummary['epubFallbackSpineResources']
                ));
                $summary['epubFallbackSpineResourceCount'] = count($summary['epubFallbackSpineResources']);
                unset($bodySummary['epubFallbackSpineResources'], $bodySummary['epubFallbackSpineResourceCount']);
            }
            $summary += $bodySummary;
        }
        if ($resourceReferencedResources !== []) {
            $summary['epubReferencedResources'] = array_values(array_unique(array_merge(
                $summary['epubReferencedResources'] ?? [],
                $resourceReferencedResources
            )));
        }

        return $summary;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, mixed> $navigation
     * @return list<array<string, mixed>>
     */
    private function alternatePackageDiagnostics(\ZipArchive $zip, \DOMElement $package, array $manifest, array $spineItems, string $basePath, string $rootfile, array $navigation): array
    {
        $diagnostics = $this->packageDiagnostics($zip, $package, $manifest, $basePath, $rootfile);
        foreach ($navigation['tocReadingOrderGroups'] as $tocEntryGroup) {
            $diagnostics = array_merge(
                $diagnostics,
                $this->navTocReadingOrderDiagnostics($tocEntryGroup, $spineItems, $manifest, $basePath)
            );
        }
        foreach ($navigation['landmarkTargetGroups'] as $landmarkEntryGroup) {
            $diagnostics = array_merge(
                $diagnostics,
                $this->xhtmlNavigationTargetDiagnostics($landmarkEntryGroup, 'landmarks', $spineItems, $manifest, $basePath)
            );
        }
        foreach ($navigation['pageListTargetGroups'] as $pageListEntryGroup) {
            $diagnostics = array_merge(
                $diagnostics,
                $this->xhtmlNavigationTargetDiagnostics($pageListEntryGroup, 'page-list', $spineItems, $manifest, $basePath)
            );
            $diagnostics = array_merge(
                $diagnostics,
                $this->navPageListReadingOrderDiagnostics($zip, $pageListEntryGroup, $spineItems, $manifest, $basePath)
            );
        }

        return array_merge(
            $diagnostics,
            $this->ncxNavigationDiagnostics($zip, $basePath, $manifest, $this->spineTocId($package), $spineItems)
        );
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $navigation
     * @return array<string, mixed>
     */
    private function alternateNavigationSummary(array $summary, array $navigation): array
    {
        $summary['epubTocResources'] = $navigation['resources'];
        if ($navigation['ncxMetadata'] !== []) {
            $summary['epubNcxMetadata'] = $navigation['ncxMetadata'];
            $primaryNcxMetadata = $navigation['ncxMetadata'][0];
            foreach ([
                'epubNcxUid' => 'uid',
                'epubNcxDepth' => 'depth',
                'epubNcxTotalPageCount' => 'totalPageCount',
                'epubNcxMaxPageNumber' => 'maxPageNumber',
                'epubNcxDocTitle' => 'docTitle',
                'epubNcxDocTitleLang' => 'docTitleLang',
                'epubNcxDocAuthors' => 'docAuthors',
                'epubNcxDocAuthorRecords' => 'docAuthorRecords',
                'epubNcxPageListLabel' => 'pageListLabel',
                'epubNcxPageListLabelLang' => 'pageListLabelLang',
            ] as $metadataKey => $sourceKey) {
                if (isset($primaryNcxMetadata[$sourceKey])) {
                    $summary[$metadataKey] = $primaryNcxMetadata[$sourceKey];
                }
            }
        }
        $summary['epubTocEntryCount'] = count($navigation['toc']);
        $summary['epubLandmarkEntryCount'] = count($navigation['landmarks']);
        $summary['epubPageListEntryCount'] = count($navigation['pageList']);
        $summary['epubAuxiliaryNavSectionCount'] = count($navigation['auxiliaryNavSections']);
        $summary['epubAuxiliaryNavEntryCount'] = array_sum(array_map(
            static fn (array $section): int => count($section['entries'] ?? []),
            $navigation['auxiliaryNavSections']
        ));
        if ($navigation['toc'] !== []) {
            $summary['epubTocEntries'] = $navigation['toc'];
        }
        if ($navigation['landmarks'] !== []) {
            $summary['epubLandmarkEntries'] = $navigation['landmarks'];
        }
        if ($navigation['pageList'] !== []) {
            $summary['epubPageListEntries'] = $navigation['pageList'];
        }
        if (($navigation['ncxNavLists'] ?? []) !== []) {
            $summary['epubNcxNavLists'] = $navigation['ncxNavLists'];
        }
        if ($navigation['auxiliaryNavSections'] !== []) {
            $summary['epubAuxiliaryNavSections'] = $navigation['auxiliaryNavSections'];
        }
        foreach ([
            'epubNavRootAttributes' => 'rootAttributes',
            'epubNavBodyAttributes' => 'bodyAttributes',
        ] as $metadataKey => $navigationKey) {
            if (($navigation[$navigationKey] ?? []) !== []) {
                $summary[$metadataKey] = $navigation[$navigationKey];
            }
        }
        foreach ([
            'epubTocNavAttributes' => 'tocNavAttributes',
            'epubLandmarkNavAttributes' => 'landmarkNavAttributes',
            'epubPageListNavAttributes' => 'pageListNavAttributes',
        ] as $metadataKey => $navigationKey) {
            if (($navigation[$navigationKey] ?? []) !== []) {
                $summary[$metadataKey] = $navigation[$navigationKey];
            }
        }
        foreach ([
            'epubTocNavTitle' => 'tocNavTitle',
            'epubLandmarkNavTitle' => 'landmarkNavTitle',
            'epubPageListNavTitle' => 'pageListNavTitle',
        ] as $metadataKey => $navigationKey) {
            if (($navigation[$navigationKey] ?? '') !== '') {
                $summary[$metadataKey] = $navigation[$navigationKey];
            }
        }

        return $summary;
    }

    /**
     * @param array<string, array<string, mixed>> $packages
     * @return list<AstNode>
     */
    private function alternateRootfileBodyBlocks(array $packages): array
    {
        $blocks = [];
        foreach ($packages as $path => $package) {
            if (!is_array($package)) {
                continue;
            }

            $bodyBlocks = $package['epubBodyBlocks'] ?? [];
            if (!is_array($bodyBlocks) || $bodyBlocks === []) {
                continue;
            }

            $rootfilePath = is_string($path) ? $path : (string) ($package['path'] ?? '');
            $bodyBlockSourcesByIndex = $this->alternateRootfileBodyBlockSourcesByIndex($package);
            $children = [];
            foreach ($bodyBlocks as $bodyBlockIndex => $bodyBlock) {
                $child = $this->astNodeFromData($bodyBlock);
                if ($child !== null) {
                    $children[] = $this->alternateRootfileBodyBlockWithSourceAttributes(
                        $child,
                        $rootfilePath,
                        is_int($bodyBlockIndex) ? ($bodyBlockSourcesByIndex[$bodyBlockIndex] ?? []) : []
                    );
                }
            }
            if ($children === []) {
                continue;
            }

            $attributes = [
                'data-epub-rootfile' => $rootfilePath,
            ];
            foreach ([
                'id' => 'data-epub-rootfile-id',
                'fullPath' => 'data-epub-rootfile-full-path',
                'mediaType' => 'data-epub-media-type',
                'title' => 'data-epub-title',
                'identifier' => 'data-epub-identifier',
                'lang' => 'data-epub-language',
            ] as $sourceKey => $attributeName) {
                $value = $package[$sourceKey] ?? null;
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $attributes[$attributeName] = (string) $value;
                }
            }

            $properties = $package['properties'] ?? [];
            if (is_array($properties)) {
                $propertyList = array_values(array_filter(
                    array_map(static fn (mixed $property): string => trim((string) $property), $properties),
                    static fn (string $property): bool => $property !== ''
                ));
                if ($propertyList !== []) {
                    $attributes['data-epub-rootfile-properties'] = implode(' ', $propertyList);
                }
            }

            $blocks[] = new AstNode('div', [
                'id' => $this->alternateRootfileBodyBlockId($rootfilePath),
                'classes' => ['epub-alternate-rootfile'],
                'attributes' => $attributes,
            ], $children);
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $package
     * @return array<int, array<string, mixed>>
     */
    private function alternateRootfileBodyBlockSourcesByIndex(array $package): array
    {
        $sources = $package['epubBodyBlockSources'] ?? [];
        if (!is_array($sources)) {
            return [];
        }

        $byIndex = [];
        foreach ($sources as $sourceKey => $source) {
            if (!is_array($source)) {
                continue;
            }

            $index = $source['index'] ?? $sourceKey;
            if (is_string($index) && preg_match('/^-?\d+$/', $index) === 1) {
                $index = (int) $index;
            }
            if (!is_int($index) || $index < 0) {
                continue;
            }

            $byIndex[$index] = $source;
        }

        return $byIndex;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function alternateRootfileBodyBlockWithSourceAttributes(AstNode $node, string $rootfilePath, array $source): AstNode
    {
        $sourceAttributes = [];
        $rootfilePath = trim($rootfilePath);
        if ($rootfilePath !== '') {
            $sourceAttributes['data-epub-rootfile'] = $rootfilePath;
        }

        foreach ([
            'path' => 'data-epub-spine-path',
            'idref' => 'data-epub-spine-idref',
            'spineIndex' => 'data-epub-spine-index',
            'index' => 'data-epub-body-block-index',
            'spineIdref' => 'data-epub-spine-source-idref',
            'spinePath' => 'data-epub-spine-source-path',
        ] as $sourceKey => $attributeName) {
            $value = $this->epubHtmlDataAttributeValue($source[$sourceKey] ?? null);
            if ($value !== null) {
                $sourceAttributes[$attributeName] = $value;
            }
        }

        if ($sourceAttributes === []) {
            return $node;
        }

        $attrs = $node->attrs;
        $attributes = $attrs['attributes'] ?? [];
        if (!is_array($attributes)) {
            $attributes = [];
        }
        foreach ($sourceAttributes as $name => $value) {
            if (!array_key_exists($name, $attributes)) {
                $attributes[$name] = $value;
            }
        }
        $attrs['attributes'] = $attributes;

        return new AstNode($node->type, $attrs, $node->children);
    }

    private function epubHtmlDataAttributeValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function alternateRootfileBodyBlockId(string $rootfilePath): string
    {
        return 'epub-alternate-rootfile-' . substr(sha1($rootfilePath), 0, 12);
    }

    private function astNodeFromData(mixed $data): ?AstNode
    {
        if (!is_array($data)) {
            return null;
        }

        $type = $data['type'] ?? null;
        if (!is_scalar($type) || trim((string) $type) === '') {
            return null;
        }

        $attrs = $data['attrs'] ?? [];
        if (!is_array($attrs)) {
            $attrs = [];
        }

        $children = [];
        $childrenData = $data['children'] ?? [];
        if (is_array($childrenData)) {
            foreach ($childrenData as $childData) {
                $child = $this->astNodeFromData($childData);
                if ($child !== null) {
                    $children[] = $child;
                }
            }
        }

        return new AstNode((string) $type, $attrs, $children);
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>, fallback: string, fallback-style: string, media-overlay: string}> $manifest
     * @param list<array{idref: string, linear: bool, properties: list<string>, id?: string}> $spineItems
     * @param array<string, array{id: string, href: string, path: string, mediaType: string, properties: list<string>}> $manifestResourcesByPath
     * @param array<string, array<string, mixed>> $spineXhtmlMetadata
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, mixed>
     */
    private function alternatePackageReadableBodySummary(
        \ZipArchive $zip,
        array $manifest,
        array $spineItems,
        string $basePath,
        array $manifestResourcesByPath,
        array &$spineXhtmlMetadata,
        array &$diagnostics
    ): array
    {
        $children = [];
        $resources = [];
        $referencedResources = [];
        $iframeResources = [];
        $fallbackSpineResources = [];
        $bodyBlockSources = [];
        $hyperlinkDiagnosticSeen = [];
        $hyperlinkResourceScanSeen = [];
        foreach ($spineItems as $spineIndex => $spineItem) {
            if (!($spineItem['linear'] ?? false)) {
                continue;
            }
            $idref = (string) ($spineItem['idref'] ?? '');
            if ($idref === '' || !isset($manifest[$idref])) {
                continue;
            }
            $readableItem = $this->readableSpineManifestItem($manifest, $idref, $basePath);
            if ($readableItem === null) {
                continue;
            }

            if ($readableItem['idref'] !== $idref) {
                $sourcePath = $this->packageResourcePath($basePath, $manifest[$idref]['href']);
                $fallbackSpineResources[] = [
                    'idref' => $idref,
                    'path' => $sourcePath,
                    'mediaType' => $this->manifestResourceMediaType($sourcePath, $manifest[$idref]['media-type']),
                    'fallbackIdref' => $readableItem['idref'],
                    'fallbackPath' => $readableItem['path'],
                    'fallbackMediaType' => $this->manifestResourceMediaType($readableItem['path'], $readableItem['item']['media-type']),
                ];
            }
            $href = $readableItem['path'];
            $xhtml = $zip->getFromName($href);
            if (!is_string($xhtml)) {
                continue;
            }

            $diagnostics = array_merge(
                $diagnostics,
                $this->malformedSpineXhtmlDiagnostics($xhtml, $href, $readableItem['idref'])
            );
            $xhtml = $this->normalizeEpubSwitches($xhtml);
            $resources[] = $href;
            $xhtmlBasePath = $this->dirname($href);
            $xhtmlMetadata = $this->xhtmlMetadata($xhtml, $xhtmlBasePath);
            if ($xhtmlMetadata !== []) {
                $spineXhtmlMetadata[$idref] = $xhtmlMetadata;
            }
            $resourceBasePath = $this->xhtmlResourceBasePath($xhtmlMetadata, $xhtmlBasePath);
            $diagnostics = array_merge(
                $diagnostics,
                $this->xhtmlImageFallbackDiagnostics(
                    $xhtml,
                    $href,
                    $resourceBasePath,
                    $manifest,
                    $manifestResourcesByPath,
                    $basePath
                )
            );
            $diagnostics = array_merge(
                $diagnostics,
                $this->xhtmlHyperlinkedSpineTargetDiagnostics(
                    $zip,
                    $xhtml,
                    $href,
                    $resourceBasePath,
                    $manifest,
                    $manifestResourcesByPath,
                    $spineItems,
                    $basePath,
                    $hyperlinkDiagnosticSeen,
                    $hyperlinkResourceScanSeen
                )
            );
            $spineIframeResources = $this->iframeResourcesForXhtml(
                $zip,
                $xhtml,
                $resourceBasePath,
                $manifestResourcesByPath,
                $referencedResources,
                $iframeResources
            );
            $rewritten = $this->rewriteRelativeLinks($xhtml, $resourceBasePath, $referencedResources);
            $document = (new MarkdownReader($this->htmlReaderOptions($spineIframeResources)))->read($rewritten);
            $blockStartIndex = count($children);
            array_push($children, ...$document->children);
            foreach ($document->children as $blockOffset => $_block) {
                $source = [
                    'index' => $blockStartIndex + $blockOffset,
                    'path' => $href,
                    'idref' => $readableItem['idref'],
                    'spineIndex' => $spineIndex,
                ];
                if ($readableItem['idref'] !== $idref) {
                    $source['spineIdref'] = $idref;
                    $source['spinePath'] = $this->packageResourcePath($basePath, $manifest[$idref]['href']);
                }
                $bodyBlockSources[] = $source;
            }
        }

        $summary = [];
        if ($resources !== []) {
            $summary['epubReadableResources'] = array_values(array_unique($resources));
        }
        if ($children !== []) {
            $document = new AstNode('document', [], $children);
            $bodyBlocks = array_map(fn (AstNode $child): array => $this->astNodeData($child), $children);
            $summary['epubBodyAst'] = [
                'type' => 'document',
                'attrs' => [],
                'children' => $bodyBlocks,
            ];
            $summary['epubBodyBlocks'] = $bodyBlocks;
            $summary['epubBodyBlockSources'] = $bodyBlockSources;
            $summary['epubBodyBlockCount'] = count($children);
            $summary['epubBodyText'] = $this->astNodeListText($children);
            $summary['epubWordPressBlocks'] = (new WordPressBlockWriter())->write($document);
        }
        if ($fallbackSpineResources !== []) {
            $summary['epubFallbackSpineResources'] = $this->uniqueFallbackSpineResources($fallbackSpineResources);
            $summary['epubFallbackSpineResourceCount'] = count($summary['epubFallbackSpineResources']);
        }
        if ($referencedResources !== []) {
            $summary['epubReferencedResources'] = array_values(array_unique($referencedResources));
        }
        if ($iframeResources !== []) {
            $summary['epubIframeResources'] = array_values(array_unique($iframeResources));
            $summary['epubIframeResourceCount'] = count($summary['epubIframeResources']);
        }

        return $summary;
    }

    /**
     * @return array{type: string, attrs: array<string, mixed>, children: list<array<string, mixed>>}
     */
    private function astNodeData(AstNode $node): array
    {
        return [
            'type' => $node->type,
            'attrs' => $this->astNodeAttributeData($node->attrs),
            'children' => array_map(fn (AstNode $child): array => $this->astNodeData($child), $node->children),
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function astNodeAttributeData(array $attrs): array
    {
        $data = [];
        foreach ($attrs as $key => $value) {
            $normalized = $this->astNodeMetadataValue($value);
            if ($normalized !== null) {
                $data[(string) $key] = $normalized;
            }
        }

        return $data;
    }

    private function astNodeMetadataValue(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if ($value instanceof AstNode) {
            return $this->astNodeData($value);
        }
        if (!is_array($value)) {
            return null;
        }

        $data = [];
        foreach ($value as $key => $entry) {
            $normalized = $this->astNodeMetadataValue($entry);
            if ($normalized !== null) {
                $data[$key] = $normalized;
            }
        }

        return $data;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function astNodeListText(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            $text = $this->astNodeText($node);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? implode(' ', $parts));
    }

    private function astNodeText(AstNode $node): string
    {
        $text = match ($node->type) {
            'text', 'code', 'math' => (string) $node->attr('text', ''),
            'softbreak', 'linebreak' => ' ',
            'raw_html', 'raw_html_inline' => (string) $node->attr('html', ''),
            'raw_tex', 'raw_tex_inline' => (string) $node->attr('tex', $node->attr('text', '')),
            'raw_block', 'raw_inline', 'code_block' => (string) $node->attr('text', ''),
            'image' => (string) $node->attr('alt', $this->astNodeListText($node->children)),
            default => $node->children !== [] ? $this->astNodeListText($node->children) : (string) $node->attr('text', ''),
        };

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<array<string, mixed>> $links
     * @param list<array<string, mixed>> $rootfiles
     * @param list<string> $knownSidecarPaths
     * @return array<string, array<string, mixed>>
     */
    private function extractedContainerLinkPayloads(\ZipArchive $zip, array $links, array $rootfiles, string $selectedRootfile, array $knownSidecarPaths): array
    {
        $payloads = [];
        $max_bytes = $this->resourceMaxBytes();
        $known_sidecars = array_fill_keys(array_map(
            fn (string $path): string => $this->normalizeZipPath($path),
            $knownSidecarPaths
        ), true);
        $rootfile_paths = [$this->normalizeZipPath($selectedRootfile) => true];
        foreach ($rootfiles as $rootfile) {
            if (!is_array($rootfile)) {
                continue;
            }
            $path = $this->normalizeZipPath((string) ($rootfile['path'] ?? $rootfile['fullPath'] ?? ''));
            if ($path !== '') {
                $rootfile_paths[$path] = true;
            }
        }

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $href = trim((string) ($link['href'] ?? ''));
            if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
                continue;
            }
            $source_href = trim((string) ($link['sourceHref'] ?? $href));
            if ($this->containerLinkHrefPathDiagnosticReason($source_href) !== '') {
                continue;
            }

            [$path] = $this->splitUrlPathSuffix($href);
            $path = $this->normalizeZipPath($path);
            if (
                $path === ''
                || $path === 'mimetype'
                || $path === 'META-INF/container.xml'
                || !str_starts_with($path, 'META-INF/')
                || isset($known_sidecars[$path])
                || isset($rootfile_paths[$path])
                || isset($payloads[$path])
            ) {
                continue;
            }

            $stat = $zip->statName($path);
            $declared_size = is_array($stat) && isset($stat['size']) ? (int) $stat['size'] : null;
            if ($declared_size !== null && $declared_size > $max_bytes) {
                continue;
            }

            $bytes = $zip->getFromName($path);
            if (!is_string($bytes) || strlen($bytes) > $max_bytes) {
                continue;
            }

            $payload = [
                'kind' => 'container-link',
                'href' => $href,
                'encoding' => 'base64',
                'bytes' => strlen($bytes),
                'data' => base64_encode($bytes),
            ];
            foreach (['id', 'rel', 'mediaType', 'sourceHref'] as $key) {
                $value = trim((string) ($link[$key] ?? ''));
                if ($value !== '') {
                    $payload[$key] = $value;
                }
            }
            $properties = $link['properties'] ?? [];
            if (is_scalar($properties)) {
                $properties = $this->tokenList((string) $properties);
            }
            if (is_array($properties)) {
                $properties = array_values(array_filter(
                    array_map(static fn (mixed $property): string => trim((string) $property), $properties),
                    static fn (string $property): bool => $property !== ''
                ));
                if ($properties !== []) {
                    $payload['properties'] = $properties;
                }
            }

            $payloads[$path] = $payload;
        }

        return $payloads;
    }

    /**
     * @param list<array<string, mixed>> $rootfiles
     * @return array<string, array{mediaType: string, properties: list<string>, selected: bool, encoding: string, bytes: int, data: string, id?: string, fullPath?: string}>
     */
    private function extractedContainerRootfilePayloads(\ZipArchive $zip, array $rootfiles, string $selectedRootfile): array
    {
        $payloads = [];
        $max_bytes = $this->resourceMaxBytes();
        foreach ($rootfiles as $rootfile) {
            if (!is_array($rootfile)) {
                continue;
            }
            $path = $this->normalizeZipPath((string) ($rootfile['path'] ?? $rootfile['fullPath'] ?? ''));
            if ($path === '' || $path === $selectedRootfile || isset($payloads[$path])) {
                continue;
            }

            $media_type = trim((string) ($rootfile['mediaType'] ?? ''));
            if (!$this->mediaTypeMatches($media_type, self::OPF_MEDIA_TYPE) && !str_ends_with(strtolower($path), '.opf')) {
                continue;
            }

            $bytes = $zip->getFromName($path);
            if (!is_string($bytes) || strlen($bytes) > $max_bytes) {
                continue;
            }

            $properties = $rootfile['properties'] ?? [];
            if (!is_array($properties)) {
                $properties = [];
            }
            $payload = [
                'mediaType' => $media_type !== '' ? $media_type : self::OPF_MEDIA_TYPE,
                'properties' => array_values(array_unique(array_filter(
                    array_map(static fn (mixed $property): string => trim((string) $property), $properties),
                    static fn (string $property): bool => $property !== ''
                ))),
                'selected' => false,
                'encoding' => 'base64',
                'bytes' => strlen($bytes),
                'data' => base64_encode($bytes),
            ];
            foreach (['id', 'fullPath'] as $key) {
                $value = trim((string) ($rootfile[$key] ?? ''));
                if ($value !== '') {
                    $payload[$key] = $value;
                }
            }
            $payloads[$path] = $payload;
        }

        return $payloads;
    }

    /**
     * @param array<string, array{id: string, href: string, path: string, mediaType: string, properties: list<string>}> $manifest_resources
     * @param list<string> $referenced_resources
     * @param list<string> $iframe_resources
     * @return array<string, array{mime: string, body: string}>
     */
    private function iframeResourcesForXhtml(\ZipArchive $zip, string $html, string $base_path, array $manifest_resources, array &$referenced_resources, array &$iframe_resources): array
    {
        $resources = [];
        $queue = $this->iframeResourceReferences($html, $base_path);
        $seen = [];
        $loaded_bytes = 0;
        $max_bytes = $this->resourceMaxBytes();
        $total_max_bytes = $this->resourceTotalMaxBytes();

        while ($queue !== []) {
            $entry = array_shift($queue);
            if (!is_array($entry)) {
                continue;
            }

            $path = (string) ($entry['path'] ?? '');
            if ($path === '' || isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;

            $stat = $zip->statName($path);
            if (!is_array($stat)) {
                continue;
            }

            $resource = $manifest_resources[$path] ?? null;
            $media_type = $this->iframeResourceMediaType($path, is_array($resource) ? (string) ($resource['mediaType'] ?? '') : '');
            if ($media_type === '') {
                continue;
            }

            $body = '';
            if ($this->iframeResourceIsHtml($path, $media_type)) {
                $declared_size = isset($stat['size']) ? (int) $stat['size'] : null;
                if ($declared_size !== null && ($declared_size > $max_bytes || $loaded_bytes + $declared_size > $total_max_bytes)) {
                    continue;
                }

                $bytes = $zip->getFromName($path);
                if (!is_string($bytes)) {
                    continue;
                }

                $size = strlen($bytes);
                if ($size > $max_bytes || $loaded_bytes + $size > $total_max_bytes) {
                    continue;
                }

                $bytes = $this->normalizeEpubSwitches($bytes);
                foreach ($this->iframeResourceReferences($bytes, $this->dirname($path)) as $nested) {
                    $queue[] = $nested;
                }
                $body = $this->rewriteRelativeLinks($bytes, $this->dirname($path), $referenced_resources);
                $loaded_bytes += $size;
            }

            $resources[$path] = [
                'mime' => $media_type,
                'body' => $body,
            ];
            $iframe_resources[] = $path;
        }

        return $resources;
    }

    /**
     * @return list<array{path: string}>
     */
    private function iframeResourceReferences(string $html, string $base_path): array
    {
        $references = [];
        $match_count = preg_match_all('/<iframe\b[^>]*\bsrc=(["\'])(.*?)\1/is', $html, $matches);
        if ($match_count === false || $match_count === 0) {
            return [];
        }

        foreach ($matches[2] as $raw_url) {
            $url = html_entity_decode((string) $raw_url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!$this->isPackageRelativeResourceUrl($url)) {
                continue;
            }

            $path = $this->rewriteRelativeResourceUrl($url, $base_path);
            if ($path !== '') {
                $references[] = ['path' => $path];
            }
        }

        return $references;
    }

    private function iframeResourceMediaType(string $path, string $media_type): string
    {
        $media_type = strtolower(trim($media_type));
        if ($media_type !== '') {
            return $media_type;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'xhtml', 'html', 'htm' => 'text/html',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private function iframeResourceIsHtml(string $path, string $media_type): bool
    {
        $media_type = strtolower($media_type);

        return str_contains($media_type, 'html')
            || str_ends_with(strtolower($path), '.xhtml')
            || str_ends_with(strtolower($path), '.html')
            || str_ends_with(strtolower($path), '.htm');
    }

    /**
     * @param array<string, array{mime: string, body: string}> $iframe_resources
     * @return array<string, mixed>
     */
    private function htmlReaderOptions(array $iframe_resources): array
    {
        $options = array_replace($this->options, [
            'htmlNativeDivs' => true,
            'preserveHtmlInputControls' => true,
        ]);
        $provided = $this->options['htmlIframeResources'] ?? [];
        if (is_array($provided) && $provided !== []) {
            $iframe_resources = array_replace($provided, $iframe_resources);
        }
        if ($iframe_resources !== []) {
            $options['htmlIframeResources'] = $iframe_resources;
        }

        return $options;
    }

    private function normalizeEpubSwitches(string $xhtml): string
    {
        if (!str_contains($xhtml, ':switch')) {
            return $xhtml;
        }

        try {
            $dom = $this->loadXml($xhtml, 'EPUB spine XHTML switch content');
        } catch (\InvalidArgumentException) {
            return $xhtml;
        }

        $changed = false;
        while (($switch = $this->firstEpubSwitchElement($dom)) instanceof \DOMElement) {
            $parent = $switch->parentNode;
            if (!$parent instanceof \DOMNode) {
                break;
            }

            $branch = $this->selectedEpubSwitchBranch($switch);
            if ($branch instanceof \DOMElement) {
                foreach (iterator_to_array($branch->childNodes) as $child) {
                    if ($child instanceof \DOMNode) {
                        $parent->insertBefore($child->cloneNode(true), $switch);
                    }
                }
            }
            $parent->removeChild($switch);
            $changed = true;
        }

        $root = $dom->documentElement;
        if (!$changed || !$root instanceof \DOMElement) {
            return $xhtml;
        }

        $normalized = $dom->saveXML($root);

        return is_string($normalized) && $normalized !== '' ? $normalized : $xhtml;
    }

    private function firstEpubSwitchElement(\DOMDocument $dom): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $this->isEpubSwitchElement($element, 'switch')) {
                return $element;
            }
        }

        return null;
    }

    private function selectedEpubSwitchBranch(\DOMElement $switch): ?\DOMElement
    {
        $firstCase = null;
        $default = null;
        foreach ($switch->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($this->isEpubSwitchElement($child, 'case')) {
                $firstCase ??= $child;
                if ($this->epubSwitchCaseIsSupported($child)) {
                    return $child;
                }
                continue;
            }
            if ($this->isEpubSwitchElement($child, 'default')) {
                $default = $child;
            }
        }

        return $default ?? $firstCase;
    }

    private function isEpubSwitchElement(\DOMElement $element, string $localName): bool
    {
        if (strtolower($element->localName) !== $localName) {
            return false;
        }

        return $element->namespaceURI === 'http://www.idpf.org/2007/ops'
            || str_starts_with(strtolower($element->nodeName), 'epub:');
    }

    private function epubSwitchCaseIsSupported(\DOMElement $case): bool
    {
        $namespace = trim($this->attributeByLocalName($case, 'required-namespace'));
        if ($namespace !== '' && !in_array($namespace, $this->epubSwitchSupportedNamespaces(), true)) {
            return false;
        }

        $modules = preg_split('/\s+/', trim($this->attributeByLocalName($case, 'required-modules'))) ?: [];
        $supportedModules = array_flip($this->epubSwitchSupportedModules());
        foreach ($modules as $module) {
            $module = strtolower(trim($module));
            if ($module !== '' && !isset($supportedModules[$module])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function epubSwitchSupportedNamespaces(): array
    {
        return [
            'http://www.w3.org/1999/xhtml',
            'http://www.w3.org/1998/Math/MathML',
            'http://www.w3.org/2000/svg',
        ];
    }

    /**
     * @return list<string>
     */
    private function epubSwitchSupportedModules(): array
    {
        return ['xhtml', 'mathml', 'svg'];
    }

    /**
     * @param list<string> $referenced_resources
     */
    private function rewriteRelativeLinks(string $html, string $base_path, array &$referenced_resources): string
    {
        $baseTags = [];
        $html = preg_replace_callback('/<base\b[^>]*>/i', static function (array $match) use (&$baseTags): string {
            $token = "\0EPUB_BASE_TAG_" . count($baseTags) . "\0";
            $baseTags[$token] = $match[0];

            return $token;
        }, $html) ?? $html;

        $html = preg_replace_callback('/(?<![\w-])(src|href|poster|data|action|formaction|background)=(["\'])([^"\']+)\2/i', function (array $match) use ($base_path, &$referenced_resources): string {
            $url = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->rewriteRelativeResourceUrl($url, $base_path);
            if ($this->isPackageRelativeResourceUrl($url)) {
                $referenced_resources[] = $rewritten;
            }
            if ($rewritten === $url) {
                return $match[0];
            }

            return $match[1] . '=' . $match[2] . $rewritten . $match[2];
        }, $html) ?? $html;

        $html = preg_replace_callback('/(?<![\w-])(srcset|imagesrcset)=(["\'])([^"\']+)\2/i', function (array $match) use ($base_path, &$referenced_resources): string {
            $rewritten = $this->rewriteSrcsetValue(html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $base_path, $referenced_resources);
            if ($rewritten === $match[3]) {
                return $match[0];
            }

            return $match[1] . '=' . $match[2] . htmlspecialchars($rewritten, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $match[2];
        }, $html) ?? $html;

        $html = preg_replace_callback('/(?<![\w-])style=(["\'])(.*?)\1/is', function (array $match) use ($base_path, &$referenced_resources): string {
            $style = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->rewriteCssResourceReferences($style, $base_path, $referenced_resources);
            if ($rewritten === $style) {
                return $match[0];
            }

            return 'style=' . $match[1] . htmlspecialchars($rewritten, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $match[1];
        }, $html) ?? $html;

        $html = preg_replace_callback('/<style\b([^>]*)>(.*?)<\/style>/is', function (array $match) use ($base_path, &$referenced_resources): string {
            $css = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->rewriteCssResourceReferences($css, $base_path, $referenced_resources);
            if ($rewritten === $css) {
                return $match[0];
            }

            return '<style' . $match[1] . '>' . htmlspecialchars($rewritten, ENT_NOQUOTES | ENT_HTML5, 'UTF-8') . '</style>';
        }, $html) ?? $html;

        return $baseTags === [] ? $html : strtr($html, $baseTags);
    }

    /**
     * @param list<string> $referenced_resources
     */
    private function rewriteSrcsetValue(string $value, string $base_path, array &$referenced_resources): string
    {
        $candidates = $this->srcsetCandidates($value);
        if ($candidates === []) {
            return $value;
        }

        $changed = false;
        $rewritten = [];
        foreach ($candidates as $candidate) {
            $url = $candidate['url'];
            $descriptor = $candidate['descriptor'];
            $rewrittenUrl = $this->rewriteRelativeResourceUrl($url, $base_path);
            if ($this->isPackageRelativeResourceUrl($url)) {
                $referenced_resources[] = $rewrittenUrl;
            }
            if ($rewrittenUrl !== $url) {
                $changed = true;
            }

            $rewritten[] = $descriptor === '' ? $rewrittenUrl : $rewrittenUrl . ' ' . $descriptor;
        }

        return $changed ? implode(', ', $rewritten) : $value;
    }

    /**
     * @return list<array{url: string, descriptor: string}>
     */
    private function srcsetCandidates(string $value): array
    {
        $length = strlen($value);
        $index = 0;
        $candidates = [];

        while ($index < $length) {
            while ($index < $length && (ctype_space($value[$index]) || $value[$index] === ',')) {
                $index++;
            }
            if ($index >= $length) {
                break;
            }

            $urlStart = $index;
            $isDataUrl = str_starts_with(strtolower(substr($value, $urlStart, 5)), 'data:');
            while ($index < $length) {
                $char = $value[$index];
                if (ctype_space($char) || ($char === ',' && !$isDataUrl)) {
                    break;
                }
                $index++;
            }
            $url = trim(substr($value, $urlStart, $index - $urlStart));
            if ($url === '') {
                break;
            }

            while ($index < $length && ctype_space($value[$index])) {
                $index++;
            }
            $descriptorStart = $index;
            while ($index < $length && $value[$index] !== ',') {
                $index++;
            }
            $descriptor = trim(substr($value, $descriptorStart, $index - $descriptorStart));

            $candidates[] = [
                'url' => $url,
                'descriptor' => $descriptor,
            ];

            if ($index < $length && $value[$index] === ',') {
                $index++;
            }
        }

        return $candidates;
    }

    private function srcsetContainsRemoteResource(string $value): bool
    {
        foreach ($this->srcsetCandidates($value) as $candidate) {
            if ($this->isRemoteResourceUrl($candidate['url'])) {
                return true;
            }
        }

        return false;
    }

    private function rewriteRelativeResourceUrl(string $url, string $base_path): string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return $url;
        }

        [, $suffix] = $this->splitUrlPathSuffix($url);
        $path = $this->localResourceZipPath($base_path, $url);

        return $path === '' ? $url : $path . $suffix;
    }

    private function rewriteRelativeBaseHref(string $href, string $base_path): string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return $href;
        }
        [$path, $suffix] = $this->splitUrlPathSuffix($href);
        if ($path === '') {
            return $href;
        }

        $rewritten = $this->normalizeZipPath($base_path . '/' . $path);
        if ($rewritten !== '' && str_ends_with($path, '/')) {
            $rewritten .= '/';
        }

        return $rewritten . $suffix;
    }

    /**
     * @param array<string, mixed> $xhtmlMetadata
     */
    private function xhtmlResourceBasePath(array $xhtmlMetadata, string $fallbackBasePath): string
    {
        $headBases = $xhtmlMetadata['headBases'] ?? [];
        if (!is_array($headBases)) {
            return $fallbackBasePath;
        }

        foreach ($headBases as $headBase) {
            if (!is_array($headBase)) {
                continue;
            }
            $href = $headBase['href'] ?? null;
            if (!is_scalar($href)) {
                continue;
            }
            $basePath = $this->resourceBasePathForHeadBaseHref((string) $href, $fallbackBasePath);
            if ($basePath !== $fallbackBasePath) {
                return $basePath;
            }
        }

        return $fallbackBasePath;
    }

    private function resourceBasePathForHeadBaseHref(string $href, string $fallbackBasePath): string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || !$this->isPackageRelativeResourceUrl($href)) {
            return $fallbackBasePath;
        }

        [$path] = $this->splitUrlPathSuffix($href);
        if ($path === '') {
            return $fallbackBasePath;
        }

        $basePath = $this->normalizeZipPath($path);
        if ($basePath === '') {
            return $fallbackBasePath;
        }

        return str_ends_with($path, '/') ? $basePath : $this->dirname($basePath);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitUrlPathSuffix(string $url): array
    {
        $query = strpos($url, '?');
        $fragment = strpos($url, '#');
        $positions = array_values(array_filter([$query, $fragment], static fn (int|false $position): bool => $position !== false));
        if ($positions === []) {
            return [$url, ''];
        }

        $position = min($positions);

        return [substr($url, 0, $position), substr($url, $position)];
    }

    /**
     * @param list<string> $referenced_resources
     */
    private function rewriteCssResourceReferences(string $css, string $base_path, array &$referenced_resources): string
    {
        $css = $this->rewriteCssResourceUrls($css, $base_path, $referenced_resources);

        return $this->rewriteCssImportStringResourceUrls($css, $base_path, $referenced_resources);
    }

    /**
     * @param list<string> $referenced_resources
     */
    private function rewriteCssResourceUrls(string $css, string $base_path, array &$referenced_resources): string
    {
        return preg_replace_callback('/url\(\s*(?:(["\'])(.*?)\1|([^)]*?))\s*\)/is', function (array $match) use ($base_path, &$referenced_resources): string {
            $quote = $match[1] ?? '';
            $url = trim($match[2] ?? ($match[3] ?? ''));
            if ($url === '') {
                return $match[0];
            }

            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->rewriteRelativeResourceUrl($url, $base_path);
            if ($this->isPackageRelativeResourceUrl($url)) {
                $referenced_resources[] = $rewritten;
            }
            if ($rewritten === $url) {
                return $match[0];
            }

            return 'url(' . $this->cssUrlLiteral($rewritten, $quote) . ')';
        }, $css) ?? $css;
    }

    /**
     * @param list<string> $referenced_resources
     */
    private function rewriteCssImportStringResourceUrls(string $css, string $base_path, array &$referenced_resources): string
    {
        return preg_replace_callback('/(@import\s+)(["\'])([^"\']+)\2/is', function (array $match) use ($base_path, &$referenced_resources): string {
            $url = trim($match[3]);
            if ($url === '') {
                return $match[0];
            }

            $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->rewriteRelativeResourceUrl($url, $base_path);
            if ($this->isPackageRelativeResourceUrl($url)) {
                $referenced_resources[] = $rewritten;
            }
            if ($rewritten === $url) {
                return $match[0];
            }

            return $match[1] . $this->cssUrlLiteral($rewritten, $match[2]);
        }, $css) ?? $css;
    }

    private function cssUrlLiteral(string $url, string $preferredQuote = ''): string
    {
        $quote = $preferredQuote;
        if ($quote === '' && preg_match('/[\s()"\']/', $url) === 1) {
            $quote = '"';
        }
        if ($quote === '') {
            return $url;
        }

        return $quote . str_replace(['\\', $quote], ['\\\\', '\\' . $quote], $url) . $quote;
    }

    private function isPackageRelativeResourceUrl(string $url): bool
    {
        return !$this->isAbsoluteUrl($url)
            && !str_starts_with($url, '#')
            && !str_starts_with(strtolower($url), 'data:')
            && !str_starts_with(strtolower($url), 'mailto:');
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '//');
    }

    private function isRemoteResourceUrl(string $url): bool
    {
        $url = strtolower(trim($url));

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function pathLooksLikeImage(string $path): bool
    {
        return (bool) preg_match('/\.(?:apng|avif|bmp|gif|ico|jpe?g|png|svgz?|tiff?|webp)$/i', $path);
    }

    /**
     * @param list<array{idref: string, path: string, mediaType: string, fallbackIdref: string, fallbackPath: string, fallbackMediaType: string}> $resources
     * @return list<array{idref: string, path: string, mediaType: string, fallbackIdref: string, fallbackPath: string, fallbackMediaType: string}>
     */
    private function uniqueFallbackSpineResources(array $resources): array
    {
        $seen = [];
        $unique = [];
        foreach ($resources as $resource) {
            $key = implode("\0", [
                $resource['idref'],
                $resource['path'],
                $resource['fallbackIdref'],
                $resource['fallbackPath'],
            ]);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $resource;
        }

        return $unique;
    }

    private function extractResources(): bool
    {
        $value = $this->options['extractResources'] ?? false;
        if (is_bool($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    private function resourceMaxBytes(): int
    {
        return $this->positiveIntegerOption('resourceMaxBytes', 1048576);
    }

    private function resourceTotalMaxBytes(): int
    {
        return $this->positiveIntegerOption('resourceTotalMaxBytes', 4194304);
    }

    private function positiveIntegerOption(string $key, int $default): int
    {
        $value = $this->options[$key] ?? $default;
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            return $default;
        }

        return max(0, (int) $value);
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

    private function tokenListContains(string $tokens, string $needle): bool
    {
        return in_array($needle, preg_split('/\s+/', trim($tokens)) ?: [], true);
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
