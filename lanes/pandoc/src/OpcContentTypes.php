<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpcContentTypes
{
    public const NAMESPACE_URI = 'http://schemas.openxmlformats.org/package/2006/content-types';

    /** @var array<string, array{extension:string, contentType:string}> */
    private array $defaults = [];

    /** @var array<string, string> */
    private array $overrides = [];

    /** @var array<string, string> */
    private array $overridePartNamesByEquivalenceKey = [];

    public static function fromXml(string $xml): self
    {
        $dom = self::loadXml($xml);
        $root = XmlHtmlDom::rootElement($dom, 'Types', self::NAMESPACE_URI);
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('OPC content-types XML must use the package content-types namespace');
        }

        $ignorableNamespaces = OpcMarkupCompatibility::ignorableNamespacesForElement($root, self::NAMESPACE_URI, 'OPC content-types XML root');
        $processContentElements = OpcMarkupCompatibility::processContentElementsForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
        OpcMarkupCompatibility::preserveElementsForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
        OpcMarkupCompatibility::preserveAttributesForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
        self::assertRootShape($root, $ignorableNamespaces);

        $types = new self();
        foreach (OpcMarkupCompatibility::packageChildElements(
            $root,
            self::NAMESPACE_URI,
            $ignorableNamespaces,
            $processContentElements,
            'OPC content-types children must use the package namespace',
            'OPC content-types XML root may not contain text content'
        ) as $child) {
            if ($child->localName === 'Default') {
                self::assertRecordShape($child, ['Extension', 'ContentType'], 'OPC Default content-type record', $ignorableNamespaces);
                self::assertXmlDefaultExtension($child->getAttribute('Extension'));
                $types->addDefault($child->getAttribute('Extension'), $child->getAttribute('ContentType'));
                continue;
            }

            if ($child->localName === 'Override') {
                self::assertRecordShape($child, ['PartName', 'ContentType'], 'OPC Override content-type record', $ignorableNamespaces);
                self::assertXmlOverridePartName($child->getAttribute('PartName'));
                $types->addOverride($child->getAttribute('PartName'), $child->getAttribute('ContentType'));
                continue;
            }

            throw new \InvalidArgumentException('Unsupported OPC content-types element: ' . $child->localName);
        }

        return $types;
    }

    /**
     * @return array{valid:bool, parseError:?string, recordCount:int, defaultCount:int, overrideCount:int, invalidCount:int, duplicateDefaultExtensionCount:int, duplicateOverridePartNameCount:int, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, duplicateDefaultExtensionGroups:array<string, list<string>>, duplicateOverridePartNameGroups:array<string, list<string>>, issueCounts:array<string, int>, issues:list<string>, records:list<array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}>}
     */
    public static function preflightXml(string $xml): array
    {
        $summary = self::contentTypesPreflightSkeleton();

        try {
            $dom = self::loadXml($xml);
            $root = XmlHtmlDom::rootElement($dom, 'Types', self::NAMESPACE_URI);
            if (!$root instanceof \DOMElement) {
                throw new \InvalidArgumentException('OPC content-types XML must use the package content-types namespace');
            }

            $ignorableNamespaces = OpcMarkupCompatibility::ignorableNamespacesForElement($root, self::NAMESPACE_URI, 'OPC content-types XML root');
            $processContentElements = OpcMarkupCompatibility::processContentElementsForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
            OpcMarkupCompatibility::preserveElementsForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
            OpcMarkupCompatibility::preserveAttributesForElement($root, $ignorableNamespaces, 'OPC content-types XML root');
            self::assertRootShape($root, $ignorableNamespaces);
            $children = OpcMarkupCompatibility::packageChildElements(
                $root,
                self::NAMESPACE_URI,
                $ignorableNamespaces,
                $processContentElements,
                'OPC content-types children must use the package namespace',
                'OPC content-types XML root may not contain text content'
            );
        } catch (\Throwable $exception) {
            $summary['valid'] = false;
            $summary['parseError'] = $exception->getMessage();
            self::appendPreflightIssue($summary, 'content-types-xml-parse-error');

            return $summary;
        }

        foreach ($children as $child) {
            $summary['records'][] = self::preflightRecord($child, count($summary['records']), $ignorableNamespaces);
        }

        self::markDuplicatePreflightRecords($summary);
        self::refreshPreflightSummary($summary);

        return $summary;
    }

    public function addDefault(string $extension, string $contentType): void
    {
        $extension = self::normalizeExtension($extension);
        self::assertContentType($contentType);

        if (isset($this->defaults[strtolower($extension)])) {
            throw new \InvalidArgumentException('Duplicate OPC default content type for extension: ' . $extension);
        }

        $this->defaults[strtolower($extension)] = [
            'extension' => $extension,
            'contentType' => $contentType,
        ];
    }

    public function addOverride(string $partName, string $contentType): void
    {
        $partName = OpcPackagePath::canonicalPartNameFromUri($partName);
        self::assertContentType($contentType);
        $equivalenceKey = self::partNameEquivalenceKey($partName);

        if (isset($this->overridePartNamesByEquivalenceKey[$equivalenceKey])) {
            throw new \InvalidArgumentException('Duplicate OPC override content type for part: ' . $partName);
        }

        $this->overrides[$partName] = $contentType;
        $this->overridePartNamesByEquivalenceKey[$equivalenceKey] = $partName;
    }

    public function contentTypeForPart(string $partName): ?string
    {
        return $this->contentTypeResolutionForPart($partName)['contentType'];
    }

    /**
     * @return array{uriReference:string, partName:string, uriReferenceSuffix:string, uriReferenceQuery:?string, uriReferenceFragment:?string, hasUriReferenceSuffix:bool, contentType:?string, contentTypeBase:?string, contentTypeHasParameters:bool, contentTypeParameterCount:int, contentTypeParameters:list<array{name:string, value:string, raw:string}>, contentTypeParameterMap:array<string, string>, contentTypeSource:string, defaultExtension:?string, overridePartName:?string, overridePartNameExactMatch:bool, overridePartNameEquivalentMatch:bool}
     */
    public function contentTypeResolutionForPart(string $partName): array
    {
        $uriReference = $partName;
        $suffix = self::uriReferenceSuffix($partName);
        $partName = OpcPackagePath::canonicalPartNameFromUri(OpcPackagePath::stripQueryAndFragment($partName));
        $base = self::resolutionBase($uriReference, $partName, $suffix);
        if (isset($this->overrides[$partName])) {
            $contentType = $this->overrides[$partName];

            return [
                ...$base,
                'partName' => $partName,
                ...self::contentTypeReport($contentType),
                'contentTypeSource' => 'override',
                'defaultExtension' => null,
                'overridePartName' => $partName,
                'overridePartNameExactMatch' => true,
                'overridePartNameEquivalentMatch' => false,
            ];
        }

        $overridePartName = $this->overridePartNamesByEquivalenceKey[self::partNameEquivalenceKey($partName)] ?? null;
        if ($overridePartName !== null) {
            $contentType = $this->overrides[$overridePartName];

            return [
                ...$base,
                'partName' => $partName,
                ...self::contentTypeReport($contentType),
                'contentTypeSource' => 'override',
                'defaultExtension' => null,
                'overridePartName' => $overridePartName,
                'overridePartNameExactMatch' => false,
                'overridePartNameEquivalentMatch' => true,
            ];
        }

        $basename = basename($partName);
        $dot = strrpos($basename, '.');
        if ($dot === false || $dot === strlen($basename) - 1) {
            return self::missingResolution($partName, $uriReference, $suffix);
        }

        $extension = strtolower(substr($basename, $dot + 1));
        $default = $this->defaults[$extension] ?? null;
        if ($default !== null) {
            return [
                ...$base,
                'partName' => $partName,
                ...self::contentTypeReport($default['contentType']),
                'contentTypeSource' => 'default',
                'defaultExtension' => $default['extension'],
                'overridePartName' => null,
                'overridePartNameExactMatch' => false,
                'overridePartNameEquivalentMatch' => false,
            ];
        }

        return self::missingResolution($partName, $uriReference, $suffix);
    }

    /**
     * @return array<string, string>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->defaults as $entry) {
            $defaults[$entry['extension']] = $entry['contentType'];
        }

        return $defaults;
    }

    /**
     * @return array<string, string>
     */
    public function overrides(): array
    {
        return $this->overrides;
    }

    public function toXml(): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS(self::NAMESPACE_URI, 'Types');
        $dom->appendChild($root);

        foreach ($this->defaults as $entry) {
            $default = $dom->createElementNS(self::NAMESPACE_URI, 'Default');
            $default->setAttribute('Extension', $entry['extension']);
            $default->setAttribute('ContentType', $entry['contentType']);
            $root->appendChild($default);
        }

        foreach ($this->overrides as $partName => $contentType) {
            $override = $dom->createElementNS(self::NAMESPACE_URI, 'Override');
            $override->setAttribute('PartName', OpcPackagePath::partNameToUri($partName));
            $override->setAttribute('ContentType', $contentType);
            $root->appendChild($override);
        }

        $xml = $dom->saveXML($root);
        if ($xml === false) {
            throw new \RuntimeException('Failed to serialize OPC content-types XML');
        }

        return $xml;
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     */
    private static function assertRootShape(\DOMElement $root, array $ignorableNamespaces): void
    {
        foreach ($root->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (
                OpcMarkupCompatibility::isNamespaceDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableDeclaration($attribute)
                || OpcMarkupCompatibility::isProcessContentDeclaration($attribute)
                || OpcMarkupCompatibility::isPreserveElementsDeclaration($attribute)
                || OpcMarkupCompatibility::isPreserveAttributesDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableExtensionAttribute($attribute, $ignorableNamespaces)
            ) {
                continue;
            }

            throw new \InvalidArgumentException('OPC content-types XML root contains unsupported attribute: ' . $attribute->name);
        }

        foreach ($root->childNodes as $child) {
            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException('OPC content-types XML root may not contain text content');
            }
        }
    }

    private static function normalizeExtension(string $extension): string
    {
        $extension = ltrim($extension, '.');
        if (
            $extension === ''
            || preg_match('/[\x00-\x20\x7F]/', $extension) === 1
            || str_contains($extension, '/')
            || str_contains($extension, '\\')
            || str_contains($extension, '?')
            || str_contains($extension, '#')
        ) {
            throw new \InvalidArgumentException('OPC content-type extension must be a simple extension name');
        }

        return $extension;
    }

    private static function assertXmlDefaultExtension(string $extension): void
    {
        if (str_starts_with($extension, '.')) {
            throw new \InvalidArgumentException('OPC Default extension must not include a leading dot');
        }
    }

    private static function assertXmlOverridePartName(string $partName): void
    {
        if (!str_starts_with($partName, '/')) {
            throw new \InvalidArgumentException('OPC Override part name must be an absolute package URI');
        }

        if (preg_match('/[\x00-\x20\x7F]/', $partName) === 1) {
            throw new \InvalidArgumentException('OPC Override part name must not contain raw whitespace or control characters');
        }

        $segments = explode('/', $partName);
        array_shift($segments);
        foreach ($segments as $segment) {
            $decoded = rawurldecode($segment);
            if ($decoded === '' || $decoded === '.' || $decoded === '..') {
                throw new \InvalidArgumentException('OPC Override part name must not contain empty or dot path segments');
            }

            if (str_ends_with($decoded, '.')) {
                throw new \InvalidArgumentException('OPC Override part name segments must not end with a dot');
            }
        }
    }

    private static function partNameEquivalenceKey(string $partName): string
    {
        return strtolower($partName);
    }

    /**
     * @param array{suffix:string, query:?string, fragment:?string} $suffix
     * @return array{uriReference:string, partName:string, uriReferenceSuffix:string, uriReferenceQuery:?string, uriReferenceFragment:?string, hasUriReferenceSuffix:bool, contentType:null, contentTypeBase:null, contentTypeHasParameters:bool, contentTypeParameterCount:int, contentTypeParameters:list<array{name:string, value:string, raw:string}>, contentTypeParameterMap:array<string, string>, contentTypeSource:string, defaultExtension:null, overridePartName:null, overridePartNameExactMatch:bool, overridePartNameEquivalentMatch:bool}
     */
    private static function missingResolution(string $partName, string $uriReference, array $suffix): array
    {
        return [
            ...self::resolutionBase($uriReference, $partName, $suffix),
            'partName' => $partName,
            'contentType' => null,
            'contentTypeBase' => null,
            'contentTypeHasParameters' => false,
            'contentTypeParameterCount' => 0,
            'contentTypeParameters' => [],
            'contentTypeParameterMap' => [],
            'contentTypeSource' => 'missing',
            'defaultExtension' => null,
            'overridePartName' => null,
            'overridePartNameExactMatch' => false,
            'overridePartNameEquivalentMatch' => false,
        ];
    }

    /**
     * @param array{suffix:string, query:?string, fragment:?string} $suffix
     * @return array{uriReference:string, partName:string, uriReferenceSuffix:string, uriReferenceQuery:?string, uriReferenceFragment:?string, hasUriReferenceSuffix:bool}
     */
    private static function resolutionBase(string $uriReference, string $partName, array $suffix): array
    {
        return [
            'uriReference' => $uriReference,
            'partName' => $partName,
            'uriReferenceSuffix' => $suffix['suffix'],
            'uriReferenceQuery' => $suffix['query'],
            'uriReferenceFragment' => $suffix['fragment'],
            'hasUriReferenceSuffix' => $suffix['suffix'] !== '',
        ];
    }

    /**
     * @return array{suffix:string, query:?string, fragment:?string}
     */
    private static function uriReferenceSuffix(string $uriReference): array
    {
        OpcPackagePath::assertSafeUriReferenceSuffix($uriReference, 'OPC content type URI reference');
        $suffixOffset = strcspn($uriReference, '?#');
        $suffix = substr($uriReference, $suffixOffset);
        if ($suffix === '') {
            return ['suffix' => '', 'query' => null, 'fragment' => null];
        }

        $query = null;
        $fragment = null;
        if ($suffix[0] === '?') {
            $fragmentOffset = strpos($suffix, '#');
            if ($fragmentOffset === false) {
                $query = substr($suffix, 1);
            } else {
                $query = substr($suffix, 1, $fragmentOffset - 1);
                $fragment = substr($suffix, $fragmentOffset + 1);
            }
        } elseif ($suffix[0] === '#') {
            $fragment = substr($suffix, 1);
        }

        return ['suffix' => $suffix, 'query' => $query, 'fragment' => $fragment];
    }

    public static function isValidContentType(string $contentType): bool
    {
        if ($contentType === '' || preg_match('/[\x00-\x1F\x7F]/', $contentType) === 1) {
            return false;
        }

        $token = '[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+';
        if (preg_match('/\A' . $token . '\/' . $token . '/', $contentType, $matches) !== 1 || $matches[0] === '') {
            return false;
        }

        $rest = substr($contentType, strlen($matches[0]));
        while ($rest !== '') {
            if (preg_match('/\A\s*;\s*' . $token . '\s*=\s*(?:' . $token . '|"(?:[^"\\\\\x00-\x1F\x7F]|\\\\[\x20-\x7E])*")/', $rest, $parameter) !== 1) {
                return false;
            }

            $rest = substr($rest, strlen($parameter[0]));
        }

        return true;
    }

    /**
     * @return array{contentType:string, contentTypeBase:string, contentTypeHasParameters:bool, contentTypeParameterCount:int, contentTypeParameters:list<array{name:string, value:string, raw:string}>, contentTypeParameterMap:array<string, string>}
     */
    public static function contentTypeReport(string $contentType): array
    {
        self::assertContentType($contentType);

        $token = '[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+';
        if (preg_match('/\A' . $token . '\/' . $token . '/', $contentType, $baseMatch) !== 1) {
            throw new \LogicException('Validated OPC content type was missing a media type');
        }
        $base = strtolower($baseMatch[0]);
        $rest = substr($contentType, strlen($baseMatch[0]));
        $parameters = [];
        $parameterMap = [];

        while ($rest !== '') {
            if (preg_match(
                '/\A\s*;\s*(' . $token . ')\s*=\s*(' . $token . '|"(?:[^"\\\\\x00-\x1F\x7F]|\\\\[\x20-\x7E])*")/',
                $rest,
                $parameter
            ) !== 1) {
                throw new \LogicException('Validated OPC content type parameter could not be parsed');
            }
            $raw = ltrim($parameter[0]);
            if (str_starts_with($raw, ';')) {
                $raw = ltrim(substr($raw, 1));
            }
            $name = strtolower($parameter[1]);
            $value = $parameter[2];
            if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
                $value = preg_replace('/\\\\([\x20-\x7E])/', '$1', $value) ?? $value;
            }

            $parameters[] = [
                'name' => $name,
                'value' => $value,
                'raw' => $raw,
            ];
            $parameterMap[$name] = $value;
            $rest = substr($rest, strlen($parameter[0]));
        }

        return [
            'contentType' => $contentType,
            'contentTypeBase' => $base,
            'contentTypeHasParameters' => $parameters !== [],
            'contentTypeParameterCount' => count($parameters),
            'contentTypeParameters' => $parameters,
            'contentTypeParameterMap' => $parameterMap,
        ];
    }

    private static function assertContentType(string $contentType): void
    {
        if (!self::isValidContentType($contentType)) {
            throw new \InvalidArgumentException('OPC content type must be a non-empty MIME type');
        }
    }

    /**
     * @param list<string> $allowedAttributes
     * @param array<string, true> $ignorableNamespaces
     */
    private static function assertRecordShape(\DOMElement $element, array $allowedAttributes, string $label, array $ignorableNamespaces): void
    {
        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            if (
                OpcMarkupCompatibility::isNamespaceDeclaration($attribute)
                || OpcMarkupCompatibility::isIgnorableExtensionAttribute($attribute, $ignorableNamespaces)
            ) {
                continue;
            }

            if (($attribute->namespaceURI ?? '') !== '' || !in_array($attribute->name, $allowedAttributes, true)) {
                throw new \InvalidArgumentException($label . ' contains unsupported attribute: ' . $attribute->name);
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                if (OpcMarkupCompatibility::isIgnorableExtensionElement($child, $ignorableNamespaces)) {
                    continue;
                }

                throw new \InvalidArgumentException($label . ' must be an empty element');
            }

            if (($child instanceof \DOMText || $child instanceof \DOMCdataSection) && trim($child->nodeValue ?? '') !== '') {
                throw new \InvalidArgumentException($label . ' must be an empty element');
            }
        }
    }

    private static function loadXml(string $xml): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, 'OPC content-types XML');
    }

    /**
     * @return array{valid:bool, parseError:?string, recordCount:int, defaultCount:int, overrideCount:int, invalidCount:int, duplicateDefaultExtensionCount:int, duplicateOverridePartNameCount:int, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, duplicateDefaultExtensionGroups:array<string, list<string>>, duplicateOverridePartNameGroups:array<string, list<string>>, issueCounts:array<string, int>, issues:list<string>, records:list<array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}>}
     */
    private static function contentTypesPreflightSkeleton(): array
    {
        return [
            'valid' => true,
            'parseError' => null,
            'recordCount' => 0,
            'defaultCount' => 0,
            'overrideCount' => 0,
            'invalidCount' => 0,
            'duplicateDefaultExtensionCount' => 0,
            'duplicateOverridePartNameCount' => 0,
            'duplicateDefaultExtensions' => [],
            'duplicateOverridePartNames' => [],
            'duplicateDefaultExtensionGroups' => [],
            'duplicateOverridePartNameGroups' => [],
            'issueCounts' => [],
            'issues' => [],
            'records' => [],
        ];
    }

    /**
     * @param array<string, true> $ignorableNamespaces
     * @return array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}
     */
    private static function preflightRecord(\DOMElement $element, int $recordIndex, array $ignorableNamespaces): array
    {
        $record = [
            'recordIndex' => $recordIndex,
            'kind' => $element->localName,
            'extension' => null,
            'normalizedExtension' => null,
            'partName' => null,
            'normalizedPartName' => null,
            'contentType' => null,
            'equivalenceKey' => null,
            'valid' => true,
            'issues' => [],
        ];

        if ($element->localName === 'Default') {
            self::preflightDefaultRecord($element, $record, $ignorableNamespaces);
        } elseif ($element->localName === 'Override') {
            self::preflightOverrideRecord($element, $record, $ignorableNamespaces);
        } else {
            self::appendRecordIssue($record, 'unsupported-content-type-record');
        }

        $record['valid'] = $record['issues'] === [];

        return $record;
    }

    /**
     * @param array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>} $record
     * @param array<string, true> $ignorableNamespaces
     */
    private static function preflightDefaultRecord(\DOMElement $element, array &$record, array $ignorableNamespaces): void
    {
        try {
            self::assertRecordShape($element, ['Extension', 'ContentType'], 'OPC Default content-type record', $ignorableNamespaces);
        } catch (\InvalidArgumentException) {
            self::appendRecordIssue($record, 'content-type-record-shape-error');
        }

        $extension = $element->hasAttribute('Extension') ? $element->getAttribute('Extension') : null;
        $contentType = $element->hasAttribute('ContentType') ? $element->getAttribute('ContentType') : null;
        $record['extension'] = $extension;
        $record['contentType'] = $contentType;

        if ($extension === null || $extension === '') {
            self::appendRecordIssue($record, 'missing-default-extension');
        } else {
            try {
                self::assertXmlDefaultExtension($extension);
                $normalizedExtension = self::normalizeExtension($extension);
                $record['normalizedExtension'] = $normalizedExtension;
                $record['equivalenceKey'] = strtolower($normalizedExtension);
            } catch (\InvalidArgumentException) {
                self::appendRecordIssue($record, 'invalid-default-extension');
            }
        }

        if ($contentType === null || $contentType === '') {
            self::appendRecordIssue($record, 'missing-content-type');
        } elseif (!self::isValidContentType($contentType)) {
            self::appendRecordIssue($record, 'invalid-content-type');
        }
    }

    /**
     * @param array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>} $record
     * @param array<string, true> $ignorableNamespaces
     */
    private static function preflightOverrideRecord(\DOMElement $element, array &$record, array $ignorableNamespaces): void
    {
        try {
            self::assertRecordShape($element, ['PartName', 'ContentType'], 'OPC Override content-type record', $ignorableNamespaces);
        } catch (\InvalidArgumentException) {
            self::appendRecordIssue($record, 'content-type-record-shape-error');
        }

        $partName = $element->hasAttribute('PartName') ? $element->getAttribute('PartName') : null;
        $contentType = $element->hasAttribute('ContentType') ? $element->getAttribute('ContentType') : null;
        $record['partName'] = $partName;
        $record['contentType'] = $contentType;

        if ($partName === null || $partName === '') {
            self::appendRecordIssue($record, 'missing-override-part-name');
        } else {
            try {
                self::assertXmlOverridePartName($partName);
                $normalizedPartName = OpcPackagePath::canonicalPartNameFromUri($partName);
                $record['normalizedPartName'] = $normalizedPartName;
                $record['equivalenceKey'] = self::partNameEquivalenceKey($normalizedPartName);
            } catch (\InvalidArgumentException) {
                self::appendRecordIssue($record, 'invalid-override-part-name');
            }
        }

        if ($contentType === null || $contentType === '') {
            self::appendRecordIssue($record, 'missing-content-type');
        } elseif (!self::isValidContentType($contentType)) {
            self::appendRecordIssue($record, 'invalid-content-type');
        }
    }

    /**
     * @param array{records:list<array{recordIndex:int, kind:string, extension:?string, normalizedExtension:?string, partName:?string, normalizedPartName:?string, contentType:?string, equivalenceKey:?string, valid:bool, issues:list<string>}>, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, duplicateDefaultExtensionGroups:array<string, list<string>>, duplicateOverridePartNameGroups:array<string, list<string>>} $summary
     */
    private static function markDuplicatePreflightRecords(array &$summary): void
    {
        $defaultIndexesByKey = [];
        $overrideIndexesByKey = [];
        foreach ($summary['records'] as $index => $record) {
            if ($record['equivalenceKey'] === null) {
                continue;
            }

            if ($record['kind'] === 'Default') {
                $defaultIndexesByKey[$record['equivalenceKey']][] = $index;
            } elseif ($record['kind'] === 'Override') {
                $overrideIndexesByKey[$record['equivalenceKey']][] = $index;
            }
        }

        foreach ($defaultIndexesByKey as $key => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $extensions = [];
            foreach ($indexes as $index) {
                $extension = $summary['records'][$index]['normalizedExtension'];
                if ($extension !== null) {
                    self::appendPreflightString($extensions, $extension);
                }
                self::appendRecordIssue($summary['records'][$index], 'duplicate-default-extension');
            }
            sort($extensions, SORT_STRING);
            self::appendPreflightString($summary['duplicateDefaultExtensions'], $key);
            $summary['duplicateDefaultExtensionGroups'][$key] = $extensions;
        }

        foreach ($overrideIndexesByKey as $key => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $partNames = [];
            foreach ($indexes as $index) {
                $partName = $summary['records'][$index]['normalizedPartName'];
                if ($partName !== null) {
                    self::appendPreflightString($partNames, $partName);
                }
                self::appendRecordIssue($summary['records'][$index], 'duplicate-override-part-name');
            }
            sort($partNames, SORT_STRING);
            self::appendPreflightString($summary['duplicateOverridePartNames'], $key);
            $summary['duplicateOverridePartNameGroups'][$key] = $partNames;
        }

        sort($summary['duplicateDefaultExtensions'], SORT_STRING);
        sort($summary['duplicateOverridePartNames'], SORT_STRING);
        ksort($summary['duplicateDefaultExtensionGroups'], SORT_STRING);
        ksort($summary['duplicateOverridePartNameGroups'], SORT_STRING);
    }

    /**
     * @param array{valid:bool, recordCount:int, defaultCount:int, overrideCount:int, invalidCount:int, duplicateDefaultExtensionCount:int, duplicateOverridePartNameCount:int, duplicateDefaultExtensions:list<string>, duplicateOverridePartNames:list<string>, issueCounts:array<string, int>, issues:list<string>, records:list<array{recordIndex:int, kind:string, valid:bool, issues:list<string>}>} $summary
     */
    private static function refreshPreflightSummary(array &$summary): void
    {
        $summary['recordCount'] = count($summary['records']);
        $summary['defaultCount'] = 0;
        $summary['overrideCount'] = 0;
        $summary['invalidCount'] = 0;
        $summary['issueCounts'] = [];
        $summary['issues'] = [];

        foreach ($summary['records'] as &$record) {
            $record['issues'] = array_values(array_unique($record['issues']));
            $record['valid'] = $record['issues'] === [];

            if ($record['kind'] === 'Default') {
                $summary['defaultCount']++;
            } elseif ($record['kind'] === 'Override') {
                $summary['overrideCount']++;
            }

            if (!$record['valid']) {
                $summary['invalidCount']++;
            }

            foreach ($record['issues'] as $issue) {
                self::appendPreflightIssue($summary, $issue);
            }
        }
        unset($record);

        ksort($summary['issueCounts'], SORT_STRING);
        sort($summary['issues'], SORT_STRING);
        $summary['duplicateDefaultExtensionCount'] = count($summary['duplicateDefaultExtensions']);
        $summary['duplicateOverridePartNameCount'] = count($summary['duplicateOverridePartNames']);
        $summary['valid'] = $summary['invalidCount'] === 0;
    }

    /**
     * @param array{issueCounts:array<string, int>, issues:list<string>} $summary
     */
    private static function appendPreflightIssue(array &$summary, string $issue): void
    {
        $summary['issueCounts'][$issue] = ($summary['issueCounts'][$issue] ?? 0) + 1;
        self::appendPreflightString($summary['issues'], $issue);
    }

    /**
     * @param array{issues:list<string>} $record
     */
    private static function appendRecordIssue(array &$record, string $issue): void
    {
        self::appendPreflightString($record['issues'], $issue);
    }

    /**
     * @param list<string> $values
     */
    private static function appendPreflightString(array &$values, string $value): void
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }
}
