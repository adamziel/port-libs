<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxOpenXmlReader
{
    private const NS_CT = 'http://schemas.openxmlformats.org/package/2006/content-types';
    private const NS_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_CP = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    private const NS_DCTERMS = 'http://purl.org/dc/terms/';
    private const NS_EP = 'http://schemas.openxmlformats.org/officeDocument/2006/extended-properties';
    private const NS_CUSTOM_PROPS = 'http://schemas.openxmlformats.org/officeDocument/2006/custom-properties';
    private const NS_VT = 'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes';
    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const OFFICE_DOCUMENT_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const CORE_PROPERTIES_REL = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    private const EXTENDED_PROPERTIES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
    private const CUSTOM_PROPERTIES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';
    private const STYLES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    private const NUMBERING_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';
    private const SETTINGS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings';
    private const WEB_SETTINGS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings';
    private const FONT_TABLE_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable';
    private const THEME_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme';
    private const FOOTNOTES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes';
    private const ENDNOTES_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes';
    private const COMMENTS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';

    public function readFile(string $path): AstNode
    {
        if (!is_file($path)) {
            throw new \RuntimeException("DOCX package does not exist: {$path}");
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read DOCX package: {$path}");
        }

        return $this->readZipPackage(ZipPackage::fromString($bytes));
    }

    public function readZipPackage(ZipPackage $package): AstNode
    {
        $parts = [];
        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            $parts[$entry->name] = $package->read($entry->name);
        }

        return $this->readPackage($parts);
    }

    /**
     * @param array<string, string> $parts
     */
    public function readPackage(array $parts): AstNode
    {
        $parts = $this->normalizeParts($parts);
        $contentTypes = $this->readContentTypes($parts);
        $rootRelationships = $this->readRelationshipsPart($parts, '_rels/.rels');
        $documentPart = $this->officeDocumentPart($rootRelationships);

        if (!isset($parts[$documentPart])) {
            throw new \RuntimeException("DOCX package is missing {$documentPart}");
        }

        $documentRelationshipsPart = $this->relationshipsPartFor($documentPart);
        $documentRelationships = $this->readRelationshipsPart($parts, $documentRelationshipsPart);
        $packageProvenance = $this->packageProvenance(
            $parts,
            $contentTypes,
            $rootRelationships,
            $documentPart,
            $documentRelationshipsPart,
            $documentRelationships,
        );
        $stylesPart = $this->stylesPart($parts, $documentRelationships, $documentPart);
        $styles = $this->readStyles($stylesPart['xml'], $stylesPart['partName']);
        $numberingPart = $this->numberingPart($parts, $documentRelationships, $documentPart);
        $numbering = $this->readNumbering($numberingPart['xml'], $numberingPart['partName']);
        $settingsPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::SETTINGS_REL, 'settings.xml');
        $settings = $this->readSettings($settingsPart['xml'], $settingsPart['partName']);
        $webSettingsPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::WEB_SETTINGS_REL, 'webSettings.xml');
        $webSettings = $this->readWebSettings($webSettingsPart['xml'], $webSettingsPart['partName']);
        $fontTablePart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::FONT_TABLE_REL, 'fontTable.xml');
        $fontTable = $this->readFontTable($fontTablePart['xml'], $fontTablePart['partName']);
        $themePart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::THEME_REL, 'theme/theme1.xml');
        $theme = $this->readTheme($themePart['xml'], $themePart['partName']);
        $footnotesPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::FOOTNOTES_REL, 'footnotes.xml');
        $footnoteRelationships = $this->readRelationshipsPart($parts, $this->relationshipsPartFor($footnotesPart['partName']));
        $footnotes = $this->readNotes(
            $footnotesPart['xml'],
            $footnotesPart['partName'],
            'footnotes',
            'footnote',
            'footnote',
            $footnoteRelationships,
            $contentTypes,
            $styles,
            $numbering,
        );
        $endnotesPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::ENDNOTES_REL, 'endnotes.xml');
        $endnoteRelationships = $this->readRelationshipsPart($parts, $this->relationshipsPartFor($endnotesPart['partName']));
        $endnotes = $this->readNotes(
            $endnotesPart['xml'],
            $endnotesPart['partName'],
            'endnotes',
            'endnote',
            'endnote',
            $endnoteRelationships,
            $contentTypes,
            $styles,
            $numbering,
        );
        $commentsPart = $this->relatedDocumentPart($parts, $documentRelationships, $documentPart, self::COMMENTS_REL, 'comments.xml');
        $commentRelationships = $this->readRelationshipsPart($parts, $this->relationshipsPartFor($commentsPart['partName']));
        $comments = $this->readComments(
            $commentsPart['xml'],
            $commentsPart['partName'],
            $commentRelationships,
            $contentTypes,
            $styles,
            $numbering,
        );
        $corePropertiesPart = $this->corePropertiesPart($parts, $rootRelationships);
        $meta = $this->readCoreProperties($corePropertiesPart['xml'], $corePropertiesPart['partName']);
        $extendedPropertiesPart = $this->rootRelatedPart($parts, $rootRelationships, self::EXTENDED_PROPERTIES_REL, 'docProps/app.xml');
        $extendedProperties = $this->readExtendedProperties($extendedPropertiesPart['xml'], $extendedPropertiesPart['partName']);
        $customPropertiesPart = $this->rootRelatedPart($parts, $rootRelationships, self::CUSTOM_PROPERTIES_REL, 'docProps/custom.xml');
        $customProperties = $this->readCustomProperties($customPropertiesPart['xml'], $customPropertiesPart['partName']);
        $media = $this->mediaMetadata($parts, $contentTypes);
        $referencedNotes = [
            'footnote' => $footnotes['nodes'],
            'endnote' => $endnotes['nodes'],
            'comment' => $comments['nodes'],
        ];
        $blocks = $this->readDocumentBlocks($parts[$documentPart], $documentRelationships, $contentTypes, $styles, $numbering, $referencedNotes);

        $attrs = [
            'docx' => [
                'documentPart' => $documentPart,
                'corePropertiesPart' => $corePropertiesPart['partName'],
                'contentTypes' => $contentTypes,
                'rootRelationships' => $rootRelationships,
                'documentRelationships' => $documentRelationships,
                'packageProvenance' => $packageProvenance,
                'stylesPart' => $stylesPart['partName'],
                'styles' => $styles,
                'numberingPart' => $numberingPart['partName'],
                'numbering' => $numbering,
                'settingsPart' => $settingsPart['partName'],
                'settings' => $settings,
                'webSettingsPart' => $webSettingsPart['partName'],
                'webSettings' => $webSettings,
                'fontTablePart' => $fontTablePart['partName'],
                'fontTable' => $fontTable,
                'themePart' => $themePart['partName'],
                'theme' => $theme,
                'extendedPropertiesPart' => $extendedPropertiesPart['partName'],
                'extendedProperties' => $extendedProperties,
                'customPropertiesPart' => $customPropertiesPart['partName'],
                'customProperties' => $customProperties,
                'footnotesPart' => $footnotesPart['partName'],
                'footnotes' => $footnotes['summary'],
                'endnotesPart' => $endnotesPart['partName'],
                'endnotes' => $endnotes['summary'],
                'commentsPart' => $commentsPart['partName'],
                'comments' => $comments['summary'],
                'media' => $media,
            ],
        ];
        if ($corePropertiesPart['relationship'] !== null) {
            $attrs['docx']['corePropertiesRelationship'] = $this->relationshipSummary(
                $corePropertiesPart['relationship'],
                '/',
                '_rels/.rels',
                $corePropertiesPart['partName'],
                $corePropertiesPart['exists'],
                $contentTypes,
            );
        }
        if ($extendedPropertiesPart['relationship'] !== null) {
            $attrs['docx']['extendedPropertiesRelationship'] = $this->relationshipSummary(
                $extendedPropertiesPart['relationship'],
                '/',
                '_rels/.rels',
                $extendedPropertiesPart['partName'],
                $extendedPropertiesPart['exists'],
                $contentTypes,
            );
        }
        if ($customPropertiesPart['relationship'] !== null) {
            $attrs['docx']['customPropertiesRelationship'] = $this->relationshipSummary(
                $customPropertiesPart['relationship'],
                '/',
                '_rels/.rels',
                $customPropertiesPart['partName'],
                $customPropertiesPart['exists'],
                $contentTypes,
            );
        }
        if ($stylesPart['relationship'] !== null) {
            $attrs['docx']['stylesRelationship'] = $this->relationshipSummary(
                $stylesPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $stylesPart['partName'],
                $stylesPart['exists'],
                $contentTypes,
            );
        }
        if ($numberingPart['relationship'] !== null) {
            $attrs['docx']['numberingRelationship'] = $this->relationshipSummary(
                $numberingPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $numberingPart['partName'],
                $numberingPart['exists'],
                $contentTypes,
            );
        }
        if ($settingsPart['relationship'] !== null) {
            $attrs['docx']['settingsRelationship'] = $this->relationshipSummary(
                $settingsPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $settingsPart['partName'],
                $settingsPart['exists'],
                $contentTypes,
            );
        }
        if ($webSettingsPart['relationship'] !== null) {
            $attrs['docx']['webSettingsRelationship'] = $this->relationshipSummary(
                $webSettingsPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $webSettingsPart['partName'],
                $webSettingsPart['exists'],
                $contentTypes,
            );
        }
        if ($fontTablePart['relationship'] !== null) {
            $attrs['docx']['fontTableRelationship'] = $this->relationshipSummary(
                $fontTablePart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $fontTablePart['partName'],
                $fontTablePart['exists'],
                $contentTypes,
            );
        }
        if ($themePart['relationship'] !== null) {
            $attrs['docx']['themeRelationship'] = $this->relationshipSummary(
                $themePart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $themePart['partName'],
                $themePart['exists'],
                $contentTypes,
            );
        }
        if ($footnotesPart['relationship'] !== null) {
            $attrs['docx']['footnotesRelationship'] = $this->relationshipSummary(
                $footnotesPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $footnotesPart['partName'],
                $footnotesPart['exists'],
                $contentTypes,
            );
        }
        if ($endnotesPart['relationship'] !== null) {
            $attrs['docx']['endnotesRelationship'] = $this->relationshipSummary(
                $endnotesPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $endnotesPart['partName'],
                $endnotesPart['exists'],
                $contentTypes,
            );
        }
        if ($commentsPart['relationship'] !== null) {
            $attrs['docx']['commentsRelationship'] = $this->relationshipSummary(
                $commentsPart['relationship'],
                $documentPart,
                $this->relationshipsPartFor($documentPart),
                $commentsPart['partName'],
                $commentsPart['exists'],
                $contentTypes,
            );
        }
        if ($meta !== []) {
            $attrs['meta'] = $meta;
        }
        if ($extendedProperties !== []) {
            $attrs['meta']['docxExtendedProperties'] = $extendedProperties;
        }
        if ($customProperties !== []) {
            $attrs['meta']['docxCustomProperties'] = $customProperties;
            $attrs['meta']['customProperties'] = $customProperties['byName'];
        }

        return new AstNode('document', $attrs, $blocks);
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    private function normalizeParts(array $parts): array
    {
        $normalized = [];
        foreach ($parts as $name => $contents) {
            $partName = $this->normalizePartName((string) $name);
            if ($partName !== '') {
                $normalized[$partName] = $contents;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $parts
     * @return array{defaults:array<string, string>, overrides:array<string, string>}
     */
    private function readContentTypes(array $parts): array
    {
        if (!isset($parts['[Content_Types].xml'])) {
            return ['defaults' => [], 'overrides' => []];
        }

        $dom = $this->loadXml($parts['[Content_Types].xml'], '[Content_Types].xml');
        $xpath = $this->xpath($dom);
        $defaults = [];
        $overrides = [];

        foreach ($this->elements($xpath, '/ct:Types/ct:Default') as $node) {
            $extension = strtolower($node->getAttribute('Extension'));
            if ($extension !== '') {
                $defaults[$extension] = $node->getAttribute('ContentType');
            }
        }

        foreach ($this->elements($xpath, '/ct:Types/ct:Override') as $node) {
            $partName = $this->normalizePartName($node->getAttribute('PartName'));
            if ($partName !== '') {
                $overrides[$partName] = $node->getAttribute('ContentType');
            }
        }

        return ['defaults' => $defaults, 'overrides' => $overrides];
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}>
     */
    private function readRelationshipsPart(array $parts, string $partName): array
    {
        if (!isset($parts[$partName])) {
            return [];
        }

        $dom = $this->loadXml($parts[$partName], $partName);
        $xpath = $this->xpath($dom);
        $relationships = [];
        foreach ($this->elements($xpath, '/rel:Relationships/rel:Relationship') as $node) {
            $id = $node->getAttribute('Id');
            if ($id === '') {
                continue;
            }
            $targetMode = $node->getAttribute('TargetMode');
            $target = $node->getAttribute('Target');
            $relationships[$id] = [
                'id' => $id,
                'type' => $node->getAttribute('Type'),
                'target' => $target,
                'targetMode' => $targetMode,
                'resolvedTarget' => $this->resolveRelationshipTarget($partName, $target, $targetMode),
            ];
        }

        return $relationships;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     */
    private function officeDocumentPart(array $relationships): string
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] === self::OFFICE_DOCUMENT_REL) {
                return $this->stripQueryAndFragment($relationship['resolvedTarget']);
            }
        }

        return 'word/document.xml';
    }

    /**
     * @return array<string, array{id:string, name:string, headingLevel:int|null}>
     */
    private function readStyles(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $styles = [];
        foreach ($this->elements($xpath, '/w:styles/w:style[@w:type="paragraph"]') as $style) {
            $styleId = $style->getAttributeNS(self::NS_W, 'styleId');
            if ($styleId === '') {
                continue;
            }

            $name = $this->childAttr($style, 'name', 'val');
            $outline = $this->childElement($this->childElement($style, 'pPr'), 'outlineLvl');
            $headingLevel = null;
            if ($outline instanceof \DOMElement && is_numeric($outline->getAttributeNS(self::NS_W, 'val'))) {
                $headingLevel = min(6, max(1, ((int) $outline->getAttributeNS(self::NS_W, 'val')) + 1));
            } elseif (preg_match('/(?:^|[^a-z])heading\s*([1-6])(?:$|[^0-9])/i', $name, $match) === 1) {
                $headingLevel = (int) $match[1];
            } elseif (preg_match('/^Heading([1-6])$/', $styleId, $match) === 1) {
                $headingLevel = (int) $match[1];
            }

            $styles[$styleId] = [
                'id' => $styleId,
                'name' => $name,
                'headingLevel' => $headingLevel,
            ];
        }

        return $styles;
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function corePropertiesPart(array $parts, array $relationships): array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::CORE_PROPERTIES_REL || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = 'docProps/core.xml';

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function rootRelatedPart(array $parts, array $relationships, string $relationshipType, string $fallbackPart): array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== $relationshipType || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = $this->normalizePartName($fallbackPart);

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function stylesPart(array $parts, array $relationships, string $documentPart): array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::STYLES_REL || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = $this->documentSiblingPart($documentPart, 'styles.xml');

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function numberingPart(array $parts, array $relationships, string $documentPart): array
    {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== self::NUMBERING_REL || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = $this->documentSiblingPart($documentPart, 'numbering.xml');

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array{partName:string, xml:string, relationship:array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}|null, exists:bool}
     */
    private function relatedDocumentPart(
        array $parts,
        array $relationships,
        string $documentPart,
        string $relationshipType,
        string $fallbackFileName
    ): array {
        foreach ($relationships as $relationship) {
            if ($relationship['type'] !== $relationshipType || $relationship['targetMode'] === 'External') {
                continue;
            }

            $partName = $this->stripQueryAndFragment($relationship['resolvedTarget']);

            return [
                'partName' => $partName,
                'xml' => $parts[$partName] ?? '',
                'relationship' => $relationship,
                'exists' => isset($parts[$partName]),
            ];
        }

        $partName = $this->documentSiblingPart($documentPart, $fallbackFileName);

        return [
            'partName' => $partName,
            'xml' => $parts[$partName] ?? '',
            'relationship' => null,
            'exists' => isset($parts[$partName]),
        ];
    }

    /**
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array{id:string, type:string, sourcePart:string, relationshipsPart:string, target:string, targetMode:string, resolvedTarget:string, targetPart:string, exists:bool, contentType:string}
     */
    private function relationshipSummary(array $relationship, string $sourcePart, string $relationshipsPart, string $targetPart, bool $exists, array $contentTypes): array
    {
        return [
            'id' => $relationship['id'],
            'type' => $relationship['type'],
            'sourcePart' => $sourcePart,
            'relationshipsPart' => $relationshipsPart,
            'target' => $relationship['target'],
            'targetMode' => $relationship['targetMode'],
            'resolvedTarget' => $relationship['resolvedTarget'],
            'targetPart' => $targetPart,
            'exists' => $exists,
            'contentType' => $this->contentTypeFor($targetPart, $contentTypes),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $rootRelationships
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $documentRelationships
     * @return array<string, mixed>
     */
    private function packageProvenance(
        array $parts,
        array $contentTypes,
        array $rootRelationships,
        string $documentPart,
        string $documentRelationshipsPart,
        array $documentRelationships,
    ): array {
        return [
            'contentTypesPart' => $this->contentTypesPartProvenance($parts, $contentTypes),
            'relationshipParts' => $this->packageRelationshipPartsProvenance(
                $parts,
                $contentTypes,
                $documentPart,
                $documentRelationshipsPart,
                $rootRelationships,
                $documentRelationships,
            ),
            'documentPart' => $documentPart,
            'documentRelationshipsPart' => $documentRelationshipsPart,
            'parts' => $this->packagePartInventory(
                $parts,
                $contentTypes,
                $rootRelationships,
                $documentPart,
                $documentRelationshipsPart,
                $documentRelationships,
            ),
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $rootRelationships
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $documentRelationships
     * @return array<string, mixed>
     */
    private function packageRelationshipPartsProvenance(
        array $parts,
        array $contentTypes,
        string $documentPart,
        string $documentRelationshipsPart,
        array $rootRelationships,
        array $documentRelationships,
    ): array {
        $relationshipParts = [
            '_rels/.rels' => $this->relationshipPartProvenance($parts, '_rels/.rels', '/', $rootRelationships, $contentTypes),
            $documentRelationshipsPart => $this->relationshipPartProvenance(
                $parts,
                $documentRelationshipsPart,
                $documentPart,
                $documentRelationships,
                $contentTypes,
            ),
        ];

        foreach ($parts as $partName => $_contents) {
            if (!$this->isRelationshipPartName($partName) || isset($relationshipParts[$partName])) {
                continue;
            }

            $relationshipParts[$partName] = $this->relationshipPartProvenance(
                $parts,
                $partName,
                $this->relationshipSourcePartForInventory($partName),
                $this->readRelationshipsPart($parts, $partName),
                $contentTypes,
            );
        }

        return $relationshipParts;
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function contentTypesPartProvenance(array $parts, array $contentTypes): array
    {
        $defaults = [];
        foreach ($contentTypes['defaults'] as $extension => $contentType) {
            $defaults[$extension] = [
                'extension' => $extension,
                'contentType' => $contentType,
            ];
        }

        $overrides = [];
        foreach ($contentTypes['overrides'] as $partName => $contentType) {
            $overrides[$partName] = [
                'partName' => $partName,
                'contentType' => $contentType,
                'exists' => isset($parts[$partName]),
            ];
        }

        return [
            'partName' => '[Content_Types].xml',
            'exists' => isset($parts['[Content_Types].xml']),
            'bytes' => isset($parts['[Content_Types].xml']) ? strlen($parts['[Content_Types].xml']) : 0,
            'defaultCount' => count($defaults),
            'overrideCount' => count($overrides),
            'defaults' => $defaults,
            'overrides' => $overrides,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function relationshipPartProvenance(
        array $parts,
        string $relationshipsPart,
        string $sourcePart,
        array $relationships,
        array $contentTypes,
    ): array {
        $relationshipSummaries = [];
        foreach ($relationships as $id => $relationship) {
            $relationshipSummaries[$id] = $this->relationshipInventorySummary($parts, $relationship, $sourcePart, $relationshipsPart, $contentTypes);
        }

        return [
            'partName' => $relationshipsPart,
            'sourcePart' => $sourcePart,
            'sourceExists' => $this->relationshipSourceExists($parts, $sourcePart),
            'exists' => isset($parts[$relationshipsPart]),
            'bytes' => isset($parts[$relationshipsPart]) ? strlen($parts[$relationshipsPart]) : 0,
            'relationshipCount' => count($relationshipSummaries),
            'relationships' => $relationshipSummaries,
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, mixed>
     */
    private function relationshipInventorySummary(
        array $parts,
        array $relationship,
        string $sourcePart,
        string $relationshipsPart,
        array $contentTypes,
    ): array {
        $external = $this->isExternalRelationshipTarget($relationship);
        $targetPart = $external ? null : $this->stripQueryAndFragment($relationship['resolvedTarget']);
        $suffix = $external ? ['query' => null, 'fragment' => null, 'suffix' => ''] : $this->targetReferenceSuffix($relationship['resolvedTarget']);
        $contentTypeResolution = $targetPart === null
            ? $this->missingContentTypeResolution(null)
            : $this->contentTypeResolutionForPart($targetPart, $contentTypes);

        return [
            'id' => $relationship['id'],
            'type' => $relationship['type'],
            'sourcePart' => $sourcePart,
            'relationshipsPart' => $relationshipsPart,
            'target' => $relationship['target'],
            'targetMode' => $relationship['targetMode'],
            'external' => $external,
            'resolvedTarget' => $relationship['resolvedTarget'],
            'targetPart' => $targetPart,
            'targetQuery' => $suffix['query'],
            'targetFragment' => $suffix['fragment'],
            'targetReferenceSuffix' => $suffix['suffix'],
            'exists' => $targetPart !== null && isset($parts[$targetPart]),
            'contentType' => $contentTypeResolution['contentType'],
            'contentTypeSource' => $contentTypeResolution['contentTypeSource'],
            'defaultExtension' => $contentTypeResolution['defaultExtension'],
            'overridePartName' => $contentTypeResolution['overridePartName'],
        ];
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $rootRelationships
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $documentRelationships
     * @return array<string, array<string, mixed>>
     */
    private function packagePartInventory(
        array $parts,
        array $contentTypes,
        array $rootRelationships,
        string $documentPart,
        string $documentRelationshipsPart,
        array $documentRelationships,
    ): array {
        $rolesByPart = [];
        $this->addPartRole($rolesByPart, '[Content_Types].xml', 'content-types');
        $this->addPartRole($rolesByPart, '_rels/.rels', 'package-relationships');
        $this->addPartRole($rolesByPart, $documentPart, 'office-document');
        $this->addPartRole($rolesByPart, $documentRelationshipsPart, 'office-document-relationships');
        foreach ($parts as $partName => $_contents) {
            if ($this->isRelationshipPartName($partName)) {
                $this->addPartRole($rolesByPart, $partName, 'relationship-part');
            }
        }

        foreach ($rootRelationships as $relationship) {
            if ($this->isExternalRelationshipTarget($relationship)) {
                continue;
            }
            $this->addPartRole($rolesByPart, $this->stripQueryAndFragment($relationship['resolvedTarget']), 'root-relationship-target');
        }
        foreach ($documentRelationships as $relationship) {
            if ($this->isExternalRelationshipTarget($relationship)) {
                continue;
            }
            $this->addPartRole($rolesByPart, $this->stripQueryAndFragment($relationship['resolvedTarget']), 'document-relationship-target');
        }
        foreach ($parts as $relationshipPart => $_contents) {
            if (
                !$this->isRelationshipPartName($relationshipPart)
                || $relationshipPart === '_rels/.rels'
                || $relationshipPart === $documentRelationshipsPart
            ) {
                continue;
            }

            foreach ($this->readRelationshipsPart($parts, $relationshipPart) as $relationship) {
                if ($this->isExternalRelationshipTarget($relationship)) {
                    continue;
                }
                $this->addPartRole($rolesByPart, $this->stripQueryAndFragment($relationship['resolvedTarget']), 'relationship-target');
            }
        }

        $inventory = [];
        foreach ($parts as $partName => $contents) {
            $contentTypeResolution = $this->contentTypeResolutionForPart($partName, $contentTypes);
            $roles = array_keys($rolesByPart[$partName] ?? []);
            if ($roles === []) {
                $roles = ['package-part'];
            }

            $entry = [
                'partName' => $partName,
                'bytes' => strlen($contents),
                'contentType' => $contentTypeResolution['contentType'],
                'contentTypeSource' => $contentTypeResolution['contentTypeSource'],
                'defaultExtension' => $contentTypeResolution['defaultExtension'],
                'overridePartName' => $contentTypeResolution['overridePartName'],
                'isRelationshipPart' => $this->isRelationshipPartName($partName),
                'roles' => $roles,
            ];
            if ($entry['isRelationshipPart']) {
                $relationshipSourcePart = $this->relationshipSourcePartForInventory($partName);
                $entry['relationshipSourcePart'] = $relationshipSourcePart;
                $entry['relationshipSourceExists'] = $this->relationshipSourceExists($parts, $relationshipSourcePart);
            }
            $inventory[$partName] = $entry;
        }

        return $inventory;
    }

    /**
     * @param array<string, array<string, true>> $rolesByPart
     */
    private function addPartRole(array &$rolesByPart, ?string $partName, string $role): void
    {
        if ($partName === null || $partName === '') {
            return;
        }

        $rolesByPart[$partName][$role] = true;
    }

    /**
     * @param array<string, string> $parts
     */
    private function relationshipSourceExists(array $parts, string $sourcePart): bool
    {
        return $sourcePart === '/' || ($sourcePart !== '' && isset($parts[$sourcePart]));
    }

    /**
     * @param array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string} $relationship
     */
    private function isExternalRelationshipTarget(array $relationship): bool
    {
        return $relationship['targetMode'] === 'External'
            || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $relationship['resolvedTarget']) === 1;
    }

    /**
     * @return array{query:?string, fragment:?string, suffix:string}
     */
    private function targetReferenceSuffix(string $resolvedTarget): array
    {
        $fragment = null;
        $query = null;
        $suffix = '';
        $beforeFragment = $resolvedTarget;
        $fragmentPosition = strpos($resolvedTarget, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($resolvedTarget, $fragmentPosition + 1);
            $beforeFragment = substr($resolvedTarget, 0, $fragmentPosition);
            $suffix = '#' . $fragment;
        }

        $queryPosition = strpos($beforeFragment, '?');
        if ($queryPosition !== false) {
            $query = substr($beforeFragment, $queryPosition + 1);
            $suffix = '?' . $query . ($fragment === null ? '' : '#' . $fragment);
        }

        return [
            'query' => $query,
            'fragment' => $fragment,
            'suffix' => $suffix,
        ];
    }

    /**
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array{contentType:string, contentTypeSource:string, defaultExtension:?string, overridePartName:?string}
     */
    private function contentTypeResolutionForPart(string $partName, array $contentTypes): array
    {
        $partName = $this->stripQueryAndFragment($partName);
        if (isset($contentTypes['overrides'][$partName])) {
            return [
                'contentType' => $contentTypes['overrides'][$partName],
                'contentTypeSource' => 'override',
                'defaultExtension' => null,
                'overridePartName' => $partName,
            ];
        }

        $extension = strtolower(pathinfo($partName, PATHINFO_EXTENSION));
        if ($extension !== '' && isset($contentTypes['defaults'][$extension])) {
            return [
                'contentType' => $contentTypes['defaults'][$extension],
                'contentTypeSource' => 'default',
                'defaultExtension' => $extension,
                'overridePartName' => null,
            ];
        }

        return $this->missingContentTypeResolution($extension === '' ? null : $extension);
    }

    /**
     * @return array{contentType:string, contentTypeSource:string, defaultExtension:?string, overridePartName:?string}
     */
    private function missingContentTypeResolution(?string $defaultExtension): array
    {
        return [
            'contentType' => '',
            'contentTypeSource' => 'missing',
            'defaultExtension' => $defaultExtension,
            'overridePartName' => null,
        ];
    }

    private function isRelationshipPartName(string $partName): bool
    {
        return $partName === '_rels/.rels'
            || (str_ends_with($partName, '.rels') && str_contains($partName, '/_rels/'));
    }

    private function relationshipSourcePartForInventory(string $relationshipPart): string
    {
        if ($relationshipPart === '_rels/.rels') {
            return '/';
        }

        return $this->sourcePartForRelationshipsPart($relationshipPart);
    }

    /**
     * @return array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}>
     */
    private function readNumbering(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $abstracts = [];
        foreach ($this->elements($xpath, '/w:numbering/w:abstractNum') as $abstract) {
            $abstractId = $abstract->getAttributeNS(self::NS_W, 'abstractNumId');
            if ($abstractId === '') {
                continue;
            }

            $levels = [];
            foreach ($this->elements($xpath, 'w:lvl', $abstract) as $level) {
                $ilvl = (int) $level->getAttributeNS(self::NS_W, 'ilvl');
                $levels[$ilvl] = [
                    'format' => $this->childAttr($level, 'numFmt', 'val') ?: 'decimal',
                    'text' => $this->childAttr($level, 'lvlText', 'val') ?: '%' . ($ilvl + 1) . '.',
                    'start' => max(1, (int) ($this->childAttr($level, 'start', 'val') ?: '1')),
                ];
            }
            $abstracts[$abstractId] = $levels;
        }

        $numbering = [];
        foreach ($this->elements($xpath, '/w:numbering/w:num') as $num) {
            $numId = $num->getAttributeNS(self::NS_W, 'numId');
            $abstractNumId = $this->childAttr($num, 'abstractNumId', 'val');
            if ($numId === '' || $abstractNumId === '' || !isset($abstracts[$abstractNumId])) {
                continue;
            }

            $levels = $abstracts[$abstractNumId];
            foreach ($this->elements($xpath, 'w:lvlOverride', $num) as $override) {
                $ilvl = (int) $override->getAttributeNS(self::NS_W, 'ilvl');
                $startOverride = $this->childAttr($override, 'startOverride', 'val');
                if ($startOverride !== '') {
                    $levels[$ilvl]['start'] = max(1, (int) $startOverride);
                }
            }

            $numbering[$numId] = [
                'abstractNumId' => $abstractNumId,
                'levels' => $levels,
            ];
        }

        return $numbering;
    }

    /**
     * @return array<string, mixed>
     */
    private function readCoreProperties(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $meta = [];
        foreach ([
            'title' => 'string(/cp:coreProperties/dc:title)',
            'creator' => 'string(/cp:coreProperties/dc:creator)',
            'description' => 'string(/cp:coreProperties/dc:description)',
            'subject' => 'string(/cp:coreProperties/dc:subject)',
            'keywords' => 'string(/cp:coreProperties/cp:keywords)',
            'created' => 'string(/cp:coreProperties/dcterms:created)',
            'modified' => 'string(/cp:coreProperties/dcterms:modified)',
        ] as $name => $query) {
            $value = trim((string) $xpath->evaluate($query));
            if ($value !== '') {
                $meta[$name] = $value;
            }
        }

        if (isset($meta['creator'])) {
            $meta['author'] = [$meta['creator']];
            $meta['authors'] = [$meta['creator']];
        }
        if (isset($meta['title'])) {
            $meta['titleInlines'] = [new AstNode('text', ['text' => $meta['title']])];
        }

        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function readExtendedProperties(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $properties = [];

        foreach ([
            'Template' => 'template',
            'Manager' => 'manager',
            'Company' => 'company',
            'Application' => 'application',
            'AppVersion' => 'appVersion',
            'HyperlinkBase' => 'hyperlinkBase',
        ] as $source => $target) {
            $value = $this->extendedPropertyText($xpath, $source);
            if ($value !== '') {
                $properties[$target] = $value;
            }
        }

        foreach ([
            'Pages' => 'pages',
            'Words' => 'words',
            'Characters' => 'characters',
            'CharactersWithSpaces' => 'charactersWithSpaces',
            'Lines' => 'lines',
            'Paragraphs' => 'paragraphs',
            'DocSecurity' => 'docSecurity',
        ] as $source => $target) {
            $value = $this->extendedPropertyText($xpath, $source);
            if ($value !== '' && is_numeric($value)) {
                $properties[$target] = (int) $value;
            }
        }

        foreach ([
            'ScaleCrop' => 'scaleCrop',
            'LinksUpToDate' => 'linksUpToDate',
            'SharedDoc' => 'sharedDoc',
            'HyperlinksChanged' => 'hyperlinksChanged',
        ] as $source => $target) {
            $element = $this->firstElement($xpath, '/ep:Properties/ep:' . $source, $dom);
            if ($element instanceof \DOMElement) {
                $properties[$target] = $this->openXmlBooleanText($element->textContent);
            }
        }

        $headingPairs = $this->extendedHeadingPairs($xpath, $dom);
        if ($headingPairs !== []) {
            $properties['headingPairs'] = $headingPairs;
        }

        $titlesOfParts = $this->extendedTitlesOfParts($xpath, $dom);
        if ($titlesOfParts !== []) {
            $properties['titlesOfParts'] = $titlesOfParts;
        }

        return $properties;
    }

    private function extendedPropertyText(\DOMXPath $xpath, string $localName): string
    {
        return trim((string) $xpath->evaluate('string(/ep:Properties/ep:' . $localName . ')'));
    }

    /**
     * @return list<array{name:string, count:int}>
     */
    private function extendedHeadingPairs(\DOMXPath $xpath, \DOMDocument $dom): array
    {
        $vector = $this->firstElement($xpath, '/ep:Properties/ep:HeadingPairs/vt:vector', $dom);
        if (!$vector instanceof \DOMElement) {
            return [];
        }

        $values = $this->docPropsVectorValues($vector);
        $pairs = [];
        for ($index = 0; $index + 1 < count($values); $index += 2) {
            $name = trim((string) $values[$index]);
            $count = $values[$index + 1];
            if ($name !== '' && is_int($count)) {
                $pairs[] = ['name' => $name, 'count' => $count];
            }
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function extendedTitlesOfParts(\DOMXPath $xpath, \DOMDocument $dom): array
    {
        $vector = $this->firstElement($xpath, '/ep:Properties/ep:TitlesOfParts/vt:vector', $dom);
        if (!$vector instanceof \DOMElement) {
            return [];
        }

        $titles = [];
        foreach ($this->docPropsVectorValues($vector) as $value) {
            $title = trim((string) $value);
            if ($title !== '') {
                $titles[] = $title;
            }
        }

        return $titles;
    }

    /**
     * @return array{count:int, duplicateNameCount:int, duplicateNames:list<string>, items:list<array<string, mixed>>, byName:array<string, mixed>}
     */
    private function readCustomProperties(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $items = [];
        $byName = [];
        $seenNames = [];
        $duplicateNames = [];

        foreach ($this->elements($xpath, '/cust:Properties/cust:property') as $property) {
            $name = trim($property->getAttribute('name'));
            if ($name === '') {
                continue;
            }

            $valueElement = $this->firstDocPropsValueElement($property);
            if (!$valueElement instanceof \DOMElement) {
                continue;
            }

            $duplicate = isset($seenNames[$name]);
            $seenNames[$name] = true;
            if ($duplicate) {
                $duplicateNames[$name] = true;
            } else {
                $byName[$name] = $this->docPropsTypedValue($valueElement);
            }

            $item = [
                'name' => $name,
                'valueType' => $valueElement->localName,
                'value' => $this->docPropsTypedValue($valueElement),
                'duplicate' => $duplicate,
            ];
            $pid = $property->getAttribute('pid');
            if ($pid !== '' && is_numeric($pid)) {
                $item['pid'] = (int) $pid;
            }
            $fmtid = trim($property->getAttribute('fmtid'));
            if ($fmtid !== '') {
                $item['fmtid'] = $fmtid;
            }

            $items[] = $item;
        }

        if ($items === []) {
            return [];
        }

        return [
            'count' => count($items),
            'duplicateNameCount' => count($duplicateNames),
            'duplicateNames' => array_keys($duplicateNames),
            'items' => $items,
            'byName' => $byName,
        ];
    }

    /**
     * @return list<mixed>
     */
    private function docPropsVectorValues(\DOMElement $vector): array
    {
        $values = [];
        foreach ($vector->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_VT) {
                continue;
            }

            if ($child->localName === 'variant') {
                $valueElement = $this->firstDocPropsValueElement($child);
                if ($valueElement instanceof \DOMElement) {
                    $values[] = $this->docPropsTypedValue($valueElement);
                }
                continue;
            }

            $values[] = $this->docPropsTypedValue($child);
        }

        return $values;
    }

    private function firstDocPropsValueElement(\DOMElement $parent): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::NS_VT) {
                return $child;
            }
        }

        return null;
    }

    private function docPropsTypedValue(\DOMElement $valueElement): mixed
    {
        $value = trim($valueElement->textContent);

        return match ($valueElement->localName) {
            'bool' => $this->openXmlBooleanText($value),
            'i1', 'i2', 'i4', 'i8', 'int', 'ui1', 'ui2', 'ui4', 'ui8', 'uint' => is_numeric($value) ? (int) $value : 0,
            'r4', 'r8', 'decimal' => is_numeric($value) ? (float) $value : 0.0,
            default => $value,
        };
    }

    private function openXmlBooleanText(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function readSettings(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $settings = [];

        foreach ([
            'trackRevisions' => 'trackRevisions',
            'doNotTrackMoves' => 'doNotTrackMoves',
            'doNotTrackFormatting' => 'doNotTrackFormatting',
            'evenAndOddHeaders' => 'evenAndOddHeaders',
            'updateFields' => 'updateFields',
        ] as $key => $localName) {
            $element = $this->firstElement($xpath, '/w:settings/w:' . $localName, $dom);
            if ($element instanceof \DOMElement) {
                $settings[$key] = $this->wordBoolean($element);
            }
        }

        $defaultTabStop = $this->firstElement($xpath, '/w:settings/w:defaultTabStop', $dom);
        if ($defaultTabStop instanceof \DOMElement && is_numeric($defaultTabStop->getAttributeNS(self::NS_W, 'val'))) {
            $settings['defaultTabStopTwips'] = (int) $defaultTabStop->getAttributeNS(self::NS_W, 'val');
        }

        $zoom = $this->firstElement($xpath, '/w:settings/w:zoom', $dom);
        if ($zoom instanceof \DOMElement) {
            $settings['zoom'] = array_filter([
                'percent' => is_numeric($zoom->getAttributeNS(self::NS_W, 'percent'))
                    ? (int) $zoom->getAttributeNS(self::NS_W, 'percent')
                    : null,
                'value' => $zoom->getAttributeNS(self::NS_W, 'val') ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        foreach ([
            'decimalSymbol' => 'decimalSymbol',
            'listSeparator' => 'listSeparator',
        ] as $key => $localName) {
            $element = $this->firstElement($xpath, '/w:settings/w:' . $localName, $dom);
            if ($element instanceof \DOMElement) {
                $value = $element->getAttributeNS(self::NS_W, 'val');
                if ($value !== '') {
                    $settings[$key] = $value;
                }
            }
        }

        $protection = $this->firstElement($xpath, '/w:settings/w:documentProtection', $dom);
        if ($protection instanceof \DOMElement) {
            $settings['documentProtection'] = $this->wordAttributeMap($protection, [
                'edit',
                'enforcement',
                'cryptProviderType',
                'cryptAlgorithmClass',
                'cryptAlgorithmType',
                'cryptAlgorithmSid',
                'cryptSpinCount',
            ]);
            if (isset($settings['documentProtection']['enforcement'])) {
                $settings['documentProtection']['enforcement'] = $this->wordBoolean($protection, 'enforcement');
            }
            foreach (['cryptAlgorithmSid', 'cryptSpinCount'] as $numericKey) {
                if (isset($settings['documentProtection'][$numericKey]) && is_numeric($settings['documentProtection'][$numericKey])) {
                    $settings['documentProtection'][$numericKey] = (int) $settings['documentProtection'][$numericKey];
                }
            }
        }

        $compatibility = [];
        foreach ($this->elements($xpath, '/w:settings/w:compat/w:compatSetting') as $setting) {
            $compatibility[] = array_filter([
                'name' => $setting->getAttributeNS(self::NS_W, 'name') ?: null,
                'uri' => $setting->getAttributeNS(self::NS_W, 'uri') ?: null,
                'value' => $setting->getAttributeNS(self::NS_W, 'val') ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }
        if ($compatibility !== []) {
            $settings['compatibility'] = $compatibility;
        }

        $documentVariables = [];
        foreach ($this->elements($xpath, '/w:settings/w:docVars/w:docVar') as $variable) {
            $name = $variable->getAttributeNS(self::NS_W, 'name');
            if ($name !== '') {
                $documentVariables[$name] = $variable->getAttributeNS(self::NS_W, 'val');
            }
        }
        if ($documentVariables !== []) {
            $settings['documentVariables'] = $documentVariables;
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function readWebSettings(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $webSettings = [];

        foreach ([
            'optimizeForBrowser' => 'optimizeForBrowser',
            'allowPNG' => 'allowPng',
            'relyOnVML' => 'relyOnVml',
            'doNotRelyOnCSS' => 'doNotRelyOnCss',
            'doNotSaveAsSingleFile' => 'doNotSaveAsSingleFile',
            'doNotOrganizeInFolder' => 'doNotOrganizeInFolder',
            'doNotUseLongFileNames' => 'doNotUseLongFileNames',
        ] as $source => $target) {
            $element = $this->firstElement($xpath, '/w:webSettings/w:' . $source, $dom);
            if ($element instanceof \DOMElement) {
                $webSettings[$target] = $this->wordBoolean($element);
            }
        }

        foreach ([
            'encoding' => 'encoding',
            'targetScreenSz' => 'targetScreenSize',
        ] as $source => $target) {
            $element = $this->firstElement($xpath, '/w:webSettings/w:' . $source, $dom);
            if ($element instanceof \DOMElement) {
                $value = $element->getAttributeNS(self::NS_W, 'val');
                if ($value !== '') {
                    $webSettings[$target] = $value;
                }
            }
        }

        $pixelsPerInch = $this->firstElement($xpath, '/w:webSettings/w:pixelsPerInch', $dom);
        if ($pixelsPerInch instanceof \DOMElement && is_numeric($pixelsPerInch->getAttributeNS(self::NS_W, 'val'))) {
            $webSettings['pixelsPerInch'] = (int) $pixelsPerInch->getAttributeNS(self::NS_W, 'val');
        }

        return $webSettings;
    }

    /**
     * @return array<string, mixed>
     */
    private function readFontTable(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $fonts = [];
        $byName = [];
        foreach ($this->elements($xpath, '/w:fonts/w:font') as $font) {
            $name = $font->getAttributeNS(self::NS_W, 'name');
            if ($name === '') {
                continue;
            }

            $record = array_filter([
                'name' => $name,
                'alternateName' => $this->childAttr($font, 'altName', 'val') ?: null,
                'charset' => $this->childAttr($font, 'charset', 'val') ?: null,
                'family' => $this->childAttr($font, 'family', 'val') ?: null,
                'pitch' => $this->childAttr($font, 'pitch', 'val') ?: null,
                'panose1' => $this->childAttr($font, 'panose1', 'val') ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            $fonts[] = $record;
            $byName[$name] = $record;
        }

        return [
            'fontCount' => count($fonts),
            'declaredNames' => array_column($fonts, 'name'),
            'fonts' => $fonts,
            'byName' => $byName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readTheme(string $xml, string $partName): array
    {
        if ($xml === '') {
            return [];
        }

        $dom = $this->loadXml($xml, $partName);
        $xpath = $this->xpath($dom);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::NS_A || $root->localName !== 'theme') {
            return [];
        }

        $theme = [];
        $name = trim($root->getAttribute('name'));
        if ($name !== '') {
            $theme['name'] = $name;
        }

        $fonts = $this->themeFontScheme($xpath, $root);
        if ($fonts !== []) {
            $theme['fonts'] = $fonts;
        }

        $colors = $this->themeColorScheme($xpath, $root);
        if ($colors !== []) {
            $theme['colors'] = $colors;
        }

        return $theme;
    }

    /**
     * @return array<string, string>
     */
    private function themeFontScheme(\DOMXPath $xpath, \DOMElement $theme): array
    {
        $fontScheme = $this->firstElement($xpath, 'a:themeElements/a:fontScheme', $theme);
        if (!$fontScheme instanceof \DOMElement) {
            return [];
        }

        $fonts = [];
        $schemeName = trim($fontScheme->getAttribute('name'));
        if ($schemeName !== '') {
            $fonts['schemeName'] = $schemeName;
        }

        foreach ([
            'majorFont' => 'major',
            'minorFont' => 'minor',
        ] as $fontElementName => $prefix) {
            $fontElement = $this->firstElement($xpath, 'a:' . $fontElementName, $fontScheme);
            if (!$fontElement instanceof \DOMElement) {
                continue;
            }

            foreach ([
                'latin' => 'Latin',
                'ea' => 'EastAsia',
                'cs' => 'ComplexScript',
            ] as $source => $target) {
                $sourceElement = $this->firstElement($xpath, 'a:' . $source, $fontElement);
                if (!$sourceElement instanceof \DOMElement) {
                    continue;
                }

                $typeface = trim($sourceElement->getAttribute('typeface'));
                if ($typeface !== '') {
                    $fonts[$prefix . $target] = $typeface;
                }
            }
        }

        return $fonts;
    }

    /**
     * @return array<string, mixed>
     */
    private function themeColorScheme(\DOMXPath $xpath, \DOMElement $theme): array
    {
        $colorScheme = $this->firstElement($xpath, 'a:themeElements/a:clrScheme', $theme);
        if (!$colorScheme instanceof \DOMElement) {
            return [];
        }

        $items = [];
        $byName = [];
        foreach ($colorScheme->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_A) {
                continue;
            }

            $name = $child->localName;
            if (!in_array($name, ['dk1', 'lt1', 'dk2', 'lt2', 'accent1', 'accent2', 'accent3', 'accent4', 'accent5', 'accent6', 'hlink', 'folHlink'], true)) {
                continue;
            }

            $color = $this->themeColorSchemeEntry($child);
            if ($color === null) {
                continue;
            }

            $item = ['name' => $name] + $color;
            $items[] = $item;
            if (is_string($item['rgb'] ?? null) && $item['rgb'] !== '') {
                $byName[$name] = $item['rgb'];
                foreach ($this->themeColorAliases($name) as $alias) {
                    $byName[$alias] = $item['rgb'];
                }
            }
        }

        if ($items === []) {
            return [];
        }

        $scheme = [
            'count' => count($items),
            'items' => $items,
            'byName' => $byName,
        ];
        $schemeName = trim($colorScheme->getAttribute('name'));
        if ($schemeName !== '') {
            $scheme = ['schemeName' => $schemeName] + $scheme;
        }

        return $scheme;
    }

    /**
     * @return array{kind:string, value:?string, rgb:?string}|null
     */
    private function themeColorSchemeEntry(\DOMElement $container): ?array
    {
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_A) {
                continue;
            }

            if ($child->localName === 'srgbClr') {
                $rgb = $this->normalizedRgb($child->getAttribute('val'));
                if ($rgb === null) {
                    continue;
                }

                return [
                    'kind' => 'srgb',
                    'value' => $rgb,
                    'rgb' => $rgb,
                ];
            }

            if ($child->localName === 'sysClr') {
                $value = trim($child->getAttribute('val'));

                return [
                    'kind' => 'system',
                    'value' => $value !== '' ? $value : null,
                    'rgb' => $this->normalizedRgb($child->getAttribute('lastClr')),
                ];
            }

            if ($child->localName === 'prstClr') {
                $value = trim($child->getAttribute('val'));
                if ($value === '') {
                    continue;
                }

                return [
                    'kind' => 'preset',
                    'value' => $value,
                    'rgb' => null,
                ];
            }

            if ($child->localName === 'schemeClr') {
                $value = trim($child->getAttribute('val'));
                if ($value === '') {
                    continue;
                }

                return [
                    'kind' => 'scheme',
                    'value' => $value,
                    'rgb' => null,
                ];
            }
        }

        return null;
    }

    private function normalizedRgb(string $value): ?string
    {
        $value = strtoupper(trim($value));

        return preg_match('/^[0-9A-F]{6}$/D', $value) === 1 ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function themeColorAliases(string $name): array
    {
        return match ($name) {
            'dk1' => ['dark1', 'text1'],
            'lt1' => ['light1', 'background1'],
            'dk2' => ['dark2', 'text2'],
            'lt2' => ['light2', 'background2'],
            'hlink' => ['hyperlink'],
            'folHlink' => ['followedHyperlink', 'followed-hyperlink'],
            default => [],
        };
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @return array{summary:array{count:int, ids:list<string>, byId:array<string, array{id:string, sourceType:string, type:string, blockCount:int, text:string}>, items:list<array{id:string, sourceType:string, type:string, blockCount:int, text:string}>}, nodes:array<string, AstNode>}
     */
    private function readNotes(
        string $xml,
        string $partName,
        string $rootName,
        string $itemName,
        string $sourceType,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering
    ): array {
        if ($xml === '') {
            return [
                'summary' => ['count' => 0, 'ids' => [], 'byId' => [], 'items' => []],
                'nodes' => [],
            ];
        }

        $dom = $this->loadXml($xml, $partName);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::NS_W || $root->localName !== $rootName) {
            return [
                'summary' => ['count' => 0, 'ids' => [], 'byId' => [], 'items' => []],
                'nodes' => [],
            ];
        }

        $xpath = $this->xpath($dom);
        $items = [];
        $byId = [];
        $nodes = [];
        foreach ($root->childNodes as $note) {
            if (!$note instanceof \DOMElement || $note->namespaceURI !== self::NS_W || $note->localName !== $itemName) {
                continue;
            }

            $id = $note->getAttributeNS(self::NS_W, 'id');
            $type = strtolower($note->getAttributeNS(self::NS_W, 'type'));
            if ($id === '' || str_starts_with($id, '-') || in_array($type, ['separator', 'continuationseparator', 'continuationnotice'], true)) {
                continue;
            }

            $blocks = $this->readNoteBlocks($note, $xpath, $relationships, $contentTypes, $styles, $numbering);
            $item = [
                'id' => $id,
                'sourceType' => $sourceType,
                'type' => $type === '' ? 'normal' : $type,
                'blockCount' => count($blocks),
                'text' => $this->plainBlockText($blocks),
            ];
            $items[] = $item;
            $byId[$id] = $item;
            $nodes[$id] = new AstNode('note', [
                'id' => $id,
                'sourceType' => $sourceType,
            ], $blocks);
        }

        return [
            'summary' => [
                'count' => count($items),
                'ids' => array_column($items, 'id'),
                'byId' => $byId,
                'items' => $items,
            ],
            'nodes' => $nodes,
        ];
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @return array{summary:array{count:int, ids:list<string>, byId:array<string, array{id:string, sourceType:string, type:string, blockCount:int, text:string, author:?string, initials:?string, date:?string}>, items:list<array{id:string, sourceType:string, type:string, blockCount:int, text:string, author:?string, initials:?string, date:?string}>}, nodes:array<string, AstNode>}
     */
    private function readComments(
        string $xml,
        string $partName,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering
    ): array {
        if ($xml === '') {
            return [
                'summary' => ['count' => 0, 'ids' => [], 'byId' => [], 'items' => []],
                'nodes' => [],
            ];
        }

        $dom = $this->loadXml($xml, $partName);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->namespaceURI !== self::NS_W || $root->localName !== 'comments') {
            return [
                'summary' => ['count' => 0, 'ids' => [], 'byId' => [], 'items' => []],
                'nodes' => [],
            ];
        }

        $xpath = $this->xpath($dom);
        $items = [];
        $byId = [];
        $nodes = [];
        foreach ($root->childNodes as $comment) {
            if (!$comment instanceof \DOMElement || $comment->namespaceURI !== self::NS_W || $comment->localName !== 'comment') {
                continue;
            }

            $id = $comment->getAttributeNS(self::NS_W, 'id');
            if ($id === '') {
                continue;
            }

            $blocks = $this->readNoteBlocks($comment, $xpath, $relationships, $contentTypes, $styles, $numbering);
            $attrs = [
                'id' => $id,
                'sourceType' => 'comment',
            ];
            $author = $this->emptyStringToNull($comment->getAttributeNS(self::NS_W, 'author'));
            $initials = $this->emptyStringToNull($comment->getAttributeNS(self::NS_W, 'initials'));
            $date = $this->emptyStringToNull($comment->getAttributeNS(self::NS_W, 'date'));
            if ($author !== null) {
                $attrs['author'] = $author;
            }
            if ($initials !== null) {
                $attrs['initials'] = $initials;
            }
            if ($date !== null) {
                $attrs['date'] = $date;
            }

            $item = [
                'id' => $id,
                'sourceType' => 'comment',
                'type' => 'normal',
                'blockCount' => count($blocks),
                'text' => $this->plainBlockText($blocks),
                'author' => $author,
                'initials' => $initials,
                'date' => $date,
            ];
            $items[] = $item;
            $byId[$id] = $item;
            $nodes[$id] = new AstNode('note', $attrs, $blocks);
        }

        return [
            'summary' => [
                'count' => count($items),
                'ids' => array_column($items, 'id'),
                'byId' => $byId,
                'items' => $items,
            ],
            'nodes' => $nodes,
        ];
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @return list<AstNode>
     */
    private function readNoteBlocks(
        \DOMElement $note,
        \DOMXPath $xpath,
        array $relationships,
        array $contentTypes,
        array $styles,
        array $numbering
    ): array {
        $blocks = [];
        $currentList = null;
        $emptyReferencedNotes = ['footnote' => [], 'endnote' => [], 'comment' => []];
        foreach ($note->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::NS_W) {
                continue;
            }

            if ($child->localName === 'p') {
                $paragraph = $this->readParagraph($child, $xpath, $relationships, $contentTypes, $styles, $emptyReferencedNotes);
                if ($paragraph === null) {
                    $this->flushCurrentList($currentList, $blocks);
                    continue;
                }

                $list = $this->paragraphListAttrs($child, $numbering);
                if ($list !== null && $paragraph->type === 'paragraph') {
                    $key = $list['type'] . ':' . $list['numId'] . ':' . $list['level'] . ':' . ($list['style'] ?? '') . ':' . ($list['delimiter'] ?? '') . ':' . $list['start'];
                    if (!is_array($currentList) || $currentList['key'] !== $key) {
                        $this->flushCurrentList($currentList, $blocks);
                        $attrs = [
                            'docxNumId' => $list['numId'],
                            'docxLevel' => $list['level'],
                        ];
                        if ($list['type'] === 'ordered_list') {
                            $attrs['start'] = $list['start'];
                            $attrs['style'] = $list['style'];
                            $attrs['delimiter'] = $list['delimiter'];
                        } else {
                            $attrs['bulletChar'] = $list['text'];
                        }
                        $currentList = [
                            'key' => $key,
                            'type' => $list['type'],
                            'attrs' => $attrs,
                            'items' => [],
                        ];
                    }

                    $currentList['items'][] = new AstNode('list_item', [], [$paragraph]);
                    continue;
                }

                $this->flushCurrentList($currentList, $blocks);
                $blocks[] = $paragraph;
                continue;
            }

            if ($child->localName === 'tbl') {
                $this->flushCurrentList($currentList, $blocks);
                $table = $this->readTable($child, $xpath, $relationships, $contentTypes, $styles, $emptyReferencedNotes);
                if ($table !== null) {
                    $blocks[] = $table;
                }
            }
        }
        $this->flushCurrentList($currentList, $blocks);

        return $blocks;
    }

    /**
     * @param array<string, array<string, AstNode>> $referencedNotes
     */
    private function referencedNoteNode(array $referencedNotes, string $sourceType, \DOMElement $reference): ?AstNode
    {
        $id = $reference->getAttributeNS(self::NS_W, 'id');
        if ($id === '') {
            return null;
        }

        $attrs = [
            'id' => $id,
            'sourceType' => $sourceType,
        ];
        $customMarkFollows = strtolower($reference->getAttributeNS(self::NS_W, 'customMarkFollows'));
        if ($customMarkFollows !== '' && !in_array($customMarkFollows, ['0', 'false', 'off'], true)) {
            $attrs['customMarkFollows'] = true;
        }

        $note = $referencedNotes[$sourceType][$id] ?? null;
        if ($note instanceof AstNode) {
            return new AstNode('note', array_replace($note->attrs, $attrs), $note->children);
        }

        return new AstNode('note', ['missing' => true] + $attrs);
    }

    /**
     * @param array<string, string> $parts
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return array<string, array{path:string, contentType:string, size:int, sha1:string}>
     */
    private function mediaMetadata(array $parts, array $contentTypes): array
    {
        $media = [];
        foreach ($parts as $name => $contents) {
            if (!str_starts_with($name, 'word/media/')) {
                continue;
            }
            $media[$name] = [
                'path' => $name,
                'contentType' => $this->contentTypeFor($name, $contentTypes),
                'size' => strlen($contents),
                'sha1' => sha1($contents),
            ];
        }

        return $media;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return list<AstNode>
     */
    private function readDocumentBlocks(string $xml, array $relationships, array $contentTypes, array $styles, array $numbering, array $referencedNotes): array
    {
        $dom = $this->loadXml($xml, 'word/document.xml');
        $xpath = $this->xpath($dom);
        $blocks = [];
        $currentList = null;

        foreach ($this->elements($xpath, '/w:document/w:body/*') as $bodyChild) {
            if ($bodyChild->namespaceURI === self::NS_W && $bodyChild->localName === 'p') {
                $paragraph = $this->readParagraph($bodyChild, $xpath, $relationships, $contentTypes, $styles, $referencedNotes);
                if ($paragraph === null) {
                    $this->flushCurrentList($currentList, $blocks);
                    continue;
                }

                $list = $this->paragraphListAttrs($bodyChild, $numbering);
                if ($list !== null && $paragraph->type === 'paragraph') {
                    $key = $list['type'] . ':' . $list['numId'] . ':' . $list['level'] . ':' . ($list['style'] ?? '') . ':' . ($list['delimiter'] ?? '') . ':' . $list['start'];
                    if (!is_array($currentList) || $currentList['key'] !== $key) {
                        $this->flushCurrentList($currentList, $blocks);
                        $attrs = [
                            'docxNumId' => $list['numId'],
                            'docxLevel' => $list['level'],
                        ];
                        if ($list['type'] === 'ordered_list') {
                            $attrs['start'] = $list['start'];
                            $attrs['style'] = $list['style'];
                            $attrs['delimiter'] = $list['delimiter'];
                        } else {
                            $attrs['bulletChar'] = $list['text'];
                        }
                        $currentList = [
                            'key' => $key,
                            'type' => $list['type'],
                            'attrs' => $attrs,
                            'items' => [],
                        ];
                    }

                    $currentList['items'][] = new AstNode('list_item', [], [$paragraph]);
                    continue;
                }

                $this->flushCurrentList($currentList, $blocks);
                $blocks[] = $paragraph;
                continue;
            }

            if ($bodyChild->namespaceURI === self::NS_W && $bodyChild->localName === 'tbl') {
                $this->flushCurrentList($currentList, $blocks);
                $table = $this->readTable($bodyChild, $xpath, $relationships, $contentTypes, $styles, $referencedNotes);
                if ($table !== null) {
                    $blocks[] = $table;
                }
            }
        }
        $this->flushCurrentList($currentList, $blocks);

        return $blocks;
    }

    /**
     * @param array{key:string, type:string, attrs:array<string, mixed>, items:list<AstNode>}|null $currentList
     * @param list<AstNode> $blocks
     */
    private function flushCurrentList(?array &$currentList, array &$blocks): void
    {
        if (!is_array($currentList)) {
            return;
        }

        $blocks[] = new AstNode($currentList['type'], $currentList['attrs'], $currentList['items']);
        $currentList = null;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array<string, AstNode>> $referencedNotes
     */
    private function readParagraph(\DOMElement $paragraph, \DOMXPath $xpath, array $relationships, array $contentTypes, array $styles, array $referencedNotes): ?AstNode
    {
        $inlines = $this->readParagraphInlines($paragraph, $xpath, $relationships, $contentTypes, $referencedNotes);
        $text = $this->plainInlineText($inlines);
        if ($inlines === [] && $text === '') {
            return null;
        }

        $attrs = ['text' => $text];
        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId !== '') {
            $attrs['docxStyleId'] = $styleId;
            $style = $styles[$styleId] ?? null;
            if (is_array($style)) {
                $attrs['docxStyleName'] = $style['name'];
                if ($style['headingLevel'] !== null) {
                    $attrs['level'] = $style['headingLevel'];
                    $attrs['id'] = $this->identifierFromText($text);

                    return new AstNode('heading', $attrs, $inlines);
                }
            }
        }

        return new AstNode('paragraph', $attrs, $inlines);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return list<AstNode>
     */
    private function readParagraphInlines(\DOMElement $paragraph, \DOMXPath $xpath, array $relationships, array $contentTypes, array $referencedNotes): array
    {
        $inlines = [];
        foreach ($paragraph->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 'r') {
                array_push($inlines, ...$this->readRun($child, $xpath, $relationships, $contentTypes, $referencedNotes));
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 'hyperlink') {
                $linkInlines = [];
                foreach ($this->elements($xpath, 'w:r', $child) as $run) {
                    array_push($linkInlines, ...$this->readRun($run, $xpath, $relationships, $contentTypes, $referencedNotes));
                }
                if ($linkInlines !== []) {
                    $inlines[] = new AstNode('link', $this->hyperlinkAttrs($child, $relationships), $linkInlines);
                }
                continue;
            }

            if (in_array($child->localName, ['ins', 'smartTag', 'sdt'], true)) {
                foreach ($this->elements($xpath, './/w:r', $child) as $run) {
                    array_push($inlines, ...$this->readRun($run, $xpath, $relationships, $contentTypes, $referencedNotes));
                }
            }
        }

        return $this->mergeAdjacentText($inlines);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @return array<string, mixed>
     */
    private function hyperlinkAttrs(\DOMElement $hyperlink, array $relationships): array
    {
        $relationshipId = $hyperlink->getAttributeNS(self::NS_R, 'id');
        $anchor = $hyperlink->getAttributeNS(self::NS_W, 'anchor');
        $attrs = ['url' => ''];
        if ($relationshipId !== '' && isset($relationships[$relationshipId])) {
            $relationship = $relationships[$relationshipId];
            $attrs['url'] = $relationship['targetMode'] === 'External'
                ? $relationship['target']
                : $relationship['resolvedTarget'];
            $attrs['relationshipId'] = $relationshipId;
            $attrs['relationshipType'] = $relationship['type'];
            $attrs['targetMode'] = $relationship['targetMode'];
        } elseif ($anchor !== '') {
            $attrs['url'] = '#' . $anchor;
        }

        if ($anchor !== '' && $relationshipId !== '') {
            $attrs['url'] .= '#' . $anchor;
        }

        return $attrs;
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array<string, AstNode>> $referencedNotes
     * @return list<AstNode>
     */
    private function readRun(\DOMElement $run, \DOMXPath $xpath, array $relationships, array $contentTypes, array $referencedNotes): array
    {
        $inlines = [];
        foreach ($run->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::NS_W && $child->localName === 't') {
                $this->appendText($inlines, $child->textContent);
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'tab') {
                $this->appendText($inlines, "\t");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && in_array($child->localName, ['br', 'cr'], true)) {
                $inlines[] = new AstNode('linebreak');
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'noBreakHyphen') {
                $this->appendText($inlines, "\u{2011}");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'softHyphen') {
                $this->appendText($inlines, "\u{00AD}");
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'sym') {
                $hex = $child->getAttributeNS(self::NS_W, 'char');
                if ($hex !== '' && ctype_xdigit($hex)) {
                    $this->appendText($inlines, html_entity_decode('&#x' . $hex . ';', ENT_QUOTES | ENT_XML1, 'UTF-8'));
                }
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'drawing') {
                array_push($inlines, ...$this->readDrawingImages($child, $xpath, $relationships, $contentTypes));
                continue;
            }
            if ($child->namespaceURI === self::NS_W && in_array($child->localName, ['footnoteRef', 'endnoteRef'], true)) {
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'footnoteReference') {
                $note = $this->referencedNoteNode($referencedNotes, 'footnote', $child);
                if ($note !== null) {
                    $inlines[] = $note;
                }
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'endnoteReference') {
                $note = $this->referencedNoteNode($referencedNotes, 'endnote', $child);
                if ($note !== null) {
                    $inlines[] = $note;
                }
                continue;
            }
            if ($child->namespaceURI === self::NS_W && $child->localName === 'commentReference') {
                $note = $this->referencedNoteNode($referencedNotes, 'comment', $child);
                if ($note !== null) {
                    $inlines[] = $note;
                }
            }
        }

        return $this->wrapRunInlines($run, $inlines);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function wrapRunInlines(\DOMElement $run, array $inlines): array
    {
        if ($inlines === []) {
            return [];
        }

        $rPr = $this->childElement($run, 'rPr');
        if (!$rPr instanceof \DOMElement) {
            return $inlines;
        }

        $wrappers = [];
        $vertical = $this->childAttr($rPr, 'vertAlign', 'val');
        if ($vertical === 'superscript') {
            $wrappers[] = 'superscript';
        } elseif ($vertical === 'subscript') {
            $wrappers[] = 'subscript';
        }
        if ($this->runPropertyEnabled($rPr, 'smallCaps')) {
            $wrappers[] = 'small_caps';
        }
        if ($this->runPropertyEnabled($rPr, 'u')) {
            $wrappers[] = 'underline';
        }
        if ($this->runPropertyEnabled($rPr, 'strike') || $this->runPropertyEnabled($rPr, 'dstrike')) {
            $wrappers[] = 'strikeout';
        }
        if ($this->runPropertyEnabled($rPr, 'i')) {
            $wrappers[] = 'emph';
        }
        if ($this->runPropertyEnabled($rPr, 'b')) {
            $wrappers[] = 'strong';
        }

        foreach ($wrappers as $type) {
            $inlines = [new AstNode($type, [], $inlines)];
        }

        return $inlines;
    }

    private function runPropertyEnabled(\DOMElement $rPr, string $name): bool
    {
        $property = $this->childElement($rPr, $name);
        if (!$property instanceof \DOMElement) {
            return false;
        }

        $value = strtolower($property->getAttributeNS(self::NS_W, 'val'));
        if ($name === 'u' && $value === 'none') {
            return false;
        }

        return !in_array($value, ['0', 'false', 'off'], true);
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @return list<AstNode>
     */
    private function readDrawingImages(\DOMElement $drawing, \DOMXPath $xpath, array $relationships, array $contentTypes): array
    {
        $images = [];
        $docPr = $this->firstElement($xpath, './/wp:docPr', $drawing);
        foreach ($this->elements($xpath, './/a:blip', $drawing) as $blip) {
            $relationshipId = $blip->getAttributeNS(self::NS_R, 'embed');
            if ($relationshipId === '') {
                $relationshipId = $blip->getAttributeNS(self::NS_R, 'link');
            }

            $relationship = $relationships[$relationshipId] ?? null;
            if (!is_array($relationship)) {
                continue;
            }

            $isExternal = $relationship['targetMode'] === 'External';
            $url = $isExternal ? $relationship['target'] : $relationship['resolvedTarget'];
            $alt = $docPr instanceof \DOMElement ? trim($docPr->getAttribute('descr')) : '';
            $title = $docPr instanceof \DOMElement ? trim($docPr->getAttribute('title') ?: $docPr->getAttribute('name')) : '';
            $attrs = [
                'url' => $url,
                'relationshipId' => $relationshipId,
                'relationshipType' => $relationship['type'],
                'targetMode' => $relationship['targetMode'],
            ];
            if (!$isExternal) {
                $attrs['mediaPath'] = $relationship['resolvedTarget'];
                $attrs['contentType'] = $this->contentTypeFor($relationship['resolvedTarget'], $contentTypes);
            }
            if ($alt !== '') {
                $attrs['alt'] = $alt;
            }
            if ($title !== '') {
                $attrs['title'] = $title;
            }

            $children = $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];
            $images[] = new AstNode('image', $attrs, $children);
        }

        return $images;
    }

    /**
     * @param array<string, array{abstractNumId:string, levels:array<int, array{format:string, text:string, start:int}>}> $numbering
     * @return array{type:string, numId:string, level:int, start:int, style:string, delimiter:string, text:string}|null
     */
    private function paragraphListAttrs(\DOMElement $paragraph, array $numbering): ?array
    {
        $pPr = $this->childElement($paragraph, 'pPr');
        $numPr = $pPr instanceof \DOMElement ? $this->childElement($pPr, 'numPr') : null;
        if (!$numPr instanceof \DOMElement) {
            return null;
        }

        $numId = $this->childAttr($numPr, 'numId', 'val');
        if ($numId === '' || !isset($numbering[$numId])) {
            return null;
        }

        $ilvl = (int) ($this->childAttr($numPr, 'ilvl', 'val') ?: '0');
        $level = $numbering[$numId]['levels'][$ilvl] ?? ['format' => 'decimal', 'text' => '%' . ($ilvl + 1) . '.', 'start' => 1];
        $format = $level['format'];
        $type = $format === 'bullet' ? 'bullet_list' : 'ordered_list';

        return [
            'type' => $type,
            'numId' => $numId,
            'level' => $ilvl,
            'start' => $level['start'],
            'style' => $this->listStyleForFormat($format),
            'delimiter' => $this->listDelimiterForText($level['text']),
            'text' => $level['text'],
        ];
    }

    private function paragraphStyleId(\DOMElement $paragraph): string
    {
        $pPr = $this->childElement($paragraph, 'pPr');
        if (!$pPr instanceof \DOMElement) {
            return '';
        }

        return $this->childAttr($pPr, 'pStyle', 'val');
    }

    /**
     * @param array<string, array{id:string, type:string, target:string, targetMode:string, resolvedTarget:string}> $relationships
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     * @param array<string, array{id:string, name:string, headingLevel:int|null}> $styles
     * @param array<string, array<string, AstNode>> $referencedNotes
     */
    private function readTable(\DOMElement $table, \DOMXPath $xpath, array $relationships, array $contentTypes, array $styles, array $referencedNotes): ?AstNode
    {
        $rows = [];
        foreach ($this->elements($xpath, 'w:tr', $table) as $row) {
            $cells = [];
            foreach ($this->elements($xpath, 'w:tc', $row) as $cell) {
                $blocks = [];
                foreach ($this->elements($xpath, 'w:p', $cell) as $paragraph) {
                    $node = $this->readParagraph($paragraph, $xpath, $relationships, $contentTypes, $styles, $referencedNotes);
                    if ($node !== null) {
                        $blocks[] = $node;
                    }
                }
                $gridSpan = $this->firstElement($xpath, 'w:tcPr/w:gridSpan', $cell);
                $attrs = ['text' => $this->plainBlockText($blocks)];
                if ($gridSpan instanceof \DOMElement) {
                    $attrs['colspan'] = max(1, (int) ($gridSpan->getAttributeNS(self::NS_W, 'val') ?: '1'));
                }
                $cells[] = new AstNode('table_cell', $attrs, $blocks);
            }
            if ($cells !== []) {
                $rows[] = new AstNode('table_row', [], $cells);
            }
        }

        if ($rows === []) {
            return null;
        }

        return new AstNode('table', ['caption' => '', 'sourceFormat' => 'docx'], [
            new AstNode('table_body', [], $rows),
        ]);
    }

    private function listStyleForFormat(string $format): string
    {
        return match ($format) {
            'lowerLetter' => 'lower_alpha',
            'upperLetter' => 'upper_alpha',
            'lowerRoman' => 'lower_roman',
            'upperRoman' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function listDelimiterForText(string $text): string
    {
        $trimmed = trim($text);
        if (str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')')) {
            return 'two_parens';
        }
        if (str_ends_with($trimmed, ')')) {
            return 'one_paren';
        }

        return 'period';
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $merged !== [] && end($merged)->type === 'text') {
                $previous = array_pop($merged);
                $merged[] = new AstNode('text', ['text' => (string) $previous->attr('text', '') . (string) $node->attr('text', '')]);
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function appendText(array &$nodes, string $text): void
    {
        if ($text === '') {
            return;
        }

        if ($nodes !== [] && end($nodes)->type === 'text') {
            $previous = array_pop($nodes);
            $nodes[] = new AstNode('text', ['text' => (string) $previous->attr('text', '') . $text]);
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    private function emptyStringToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
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
                'softbreak', 'linebreak' => ' ',
                'image' => (string) $node->attr('alt', ''),
                'note' => '',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $parts[] = $block->attr('text', $this->plainInlineText($block->children));
        }

        return trim(implode(' ', array_map(static fn (mixed $part): string => (string) $part, $parts)));
    }

    private function identifierFromText(string $text): string
    {
        $id = strtolower(trim(preg_replace('/[^\pL\pN]+/u', '-', $text) ?? $text, '-'));

        return $id === '' ? 'section' : $id;
    }

    /**
     * @param array{defaults:array<string, string>, overrides:array<string, string>} $contentTypes
     */
    private function contentTypeFor(string $partName, array $contentTypes): string
    {
        $partName = $this->normalizePartName($partName);
        if (isset($contentTypes['overrides'][$partName])) {
            return $contentTypes['overrides'][$partName];
        }

        $extension = strtolower(pathinfo($partName, PATHINFO_EXTENSION));
        return $contentTypes['defaults'][$extension] ?? '';
    }

    private function relationshipsPartFor(string $partName): string
    {
        $partName = $this->normalizePartName($partName);
        $directory = dirname($partName);
        $baseName = basename($partName);

        return ($directory === '.' ? '' : $directory . '/') . '_rels/' . $baseName . '.rels';
    }

    private function documentSiblingPart(string $documentPart, string $fileName): string
    {
        $directory = dirname($this->normalizePartName($documentPart));

        return ($directory === '.' || $directory === '' ? '' : $directory . '/') . $fileName;
    }

    private function resolveRelationshipTarget(string $relsPartName, string $target, string $targetMode): string
    {
        if ($targetMode === 'External' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $target) === 1) {
            return $target;
        }

        if (str_starts_with($target, '/')) {
            return $this->normalizePartName($target);
        }

        $sourcePart = $this->sourcePartForRelationshipsPart($relsPartName);
        $base = $sourcePart === '' ? '' : dirname($sourcePart);
        $path = ($base === '' || $base === '.' ? '' : $base . '/') . $target;

        return $this->normalizePartName($path);
    }

    private function sourcePartForRelationshipsPart(string $relsPartName): string
    {
        if ($relsPartName === '_rels/.rels') {
            return '';
        }

        if (preg_match('~^(.*)/_rels/([^/]+)\.rels$~', $relsPartName, $match) === 1) {
            return ($match[1] === '' ? '' : $match[1] . '/') . $match[2];
        }

        return '';
    }

    private function normalizePartName(string $partName): string
    {
        $partName = str_replace('\\', '/', $partName);
        $partName = ltrim($partName, '/');
        $segments = [];
        foreach (explode('/', $partName) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function stripQueryAndFragment(string $partName): string
    {
        $partName = preg_replace('/[#?].*$/', '', $partName) ?? $partName;

        return $this->normalizePartName($partName);
    }

    private function loadXml(string $xml, string $partName): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$ok) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
            throw new \RuntimeException("Invalid DOCX XML part {$partName}: {$message}");
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    private function xpath(\DOMDocument $dom): \DOMXPath
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ct', self::NS_CT);
        $xpath->registerNamespace('rel', self::NS_REL);
        $xpath->registerNamespace('w', self::NS_W);
        $xpath->registerNamespace('r', self::NS_R);
        $xpath->registerNamespace('dc', self::NS_DC);
        $xpath->registerNamespace('cp', self::NS_CP);
        $xpath->registerNamespace('dcterms', self::NS_DCTERMS);
        $xpath->registerNamespace('ep', self::NS_EP);
        $xpath->registerNamespace('cust', self::NS_CUSTOM_PROPS);
        $xpath->registerNamespace('vt', self::NS_VT);
        $xpath->registerNamespace('a', self::NS_A);
        $xpath->registerNamespace('wp', self::NS_WP);

        return $xpath;
    }

    /**
     * @return list<\DOMElement>
     */
    private function elements(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array
    {
        $nodes = $context instanceof \DOMNode ? $xpath->query($query, $context) : $xpath->query($query);
        if (!$nodes instanceof \DOMNodeList) {
            return [];
        }

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function firstElement(\DOMXPath $xpath, string $query, \DOMNode $context): ?\DOMElement
    {
        $elements = $this->elements($xpath, $query, $context);

        return $elements[0] ?? null;
    }

    private function childElement(?\DOMElement $parent, string $localName): ?\DOMElement
    {
        if (!$parent instanceof \DOMElement) {
            return null;
        }

        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::NS_W && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function childAttr(\DOMElement $parent, string $childLocalName, string $attrLocalName): string
    {
        $child = $this->childElement($parent, $childLocalName);

        return $child instanceof \DOMElement ? $child->getAttributeNS(self::NS_W, $attrLocalName) : '';
    }

    private function wordBoolean(\DOMElement $element, string $attribute = 'val'): bool
    {
        $value = strtolower($element->getAttributeNS(self::NS_W, $attribute));

        return !in_array($value, ['0', 'false', 'off'], true);
    }

    /**
     * @param list<string> $names
     * @return array<string, string|int|bool>
     */
    private function wordAttributeMap(\DOMElement $element, array $names): array
    {
        $attributes = [];
        foreach ($names as $name) {
            $value = $element->getAttributeNS(self::NS_W, $name);
            if ($value !== '') {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }
}
