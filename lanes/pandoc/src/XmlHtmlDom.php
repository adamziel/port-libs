<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlHtmlDom
{
    private const XML_NAMESPACE_REVIEW_MAX_ITEMS = 25;
    private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';

    public static function loadXmlDocument(string $xml, string $label = 'XML document', bool $preserveWhiteSpace = true): \DOMDocument
    {
        self::assertSafeXmlSource($xml, $label);

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = $preserveWhiteSpace;
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $errors !== []) {
            throw new \InvalidArgumentException(self::parseErrorMessage('Unable to parse ' . $label, $errors));
        }

        self::assertNoProcessingInstructions($dom, $label);

        return $dom;
    }

    /**
     * @return array<string, mixed>
     */
    public static function summarizeXmlNamespaceUsage(\DOMDocument $dom): array
    {
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('XML namespace review packet requires a document element');
        }

        $elements = self::xmlElementsIncludingRoot($root);
        $elementUses = [];
        $attributeUses = [];
        $namespaceUris = [];
        $namespaceDeclarations = [];
        $declaredNamespacePrefixes = [];
        $prefixFrequencies = [];
        $uriFrequencies = [];
        $defaultNamespaceUris = [];
        $defaultNamespaceTransitions = [];
        $attributeCount = 0;
        $namespaceDeclarationCount = 0;
        $namespacedElementUseCount = 0;
        $unnamespacedElementUseCount = 0;
        $namespacedAttributeUseCount = 0;
        $unnamespacedAttributeUseCount = 0;
        $defaultNamespaceUseCount = 0;

        foreach ($elements as $element) {
            $elementNamespace = self::xmlNamespaceUri($element->namespaceURI);
            $elementPrefix = self::xmlUsagePrefix($element->prefix, $elementNamespace, true);
            $qualifiedName = $element->tagName;

            if ($elementNamespace !== '') {
                $namespaceUris[$elementNamespace] = true;
                ++$namespacedElementUseCount;
            } else {
                ++$unnamespacedElementUseCount;
            }

            self::rememberNamespaceUse($elementUses, $element->localName, $elementNamespace, $qualifiedName);
            self::rememberNamespaceFrequency($prefixFrequencies, $uriFrequencies, $elementPrefix, $elementNamespace, $qualifiedName, true);

            if (($element->prefix ?? '') === '' && $elementNamespace !== '') {
                ++$defaultNamespaceUseCount;
                $defaultNamespaceUris[$elementNamespace] = true;
            }

            foreach (self::namespaceDeclarationsForElement($element) as $declaration) {
                ++$namespaceDeclarationCount;
                $declaredNamespacePrefixes[$declaration['prefix']] = true;
                $namespaceDeclarations[] = $declaration;
                if ($declaration['namespaceUri'] !== '') {
                    $namespaceUris[$declaration['namespaceUri']] = true;
                }
            }

            $parentDefaultNamespace = self::parentDefaultNamespace($element);
            $currentDefaultNamespace = ($element->prefix ?? '') === '' ? ($elementNamespace === '' ? null : $elementNamespace) : $parentDefaultNamespace;
            if ($currentDefaultNamespace !== $parentDefaultNamespace) {
                $defaultNamespaceTransitions[] = [
                    'path' => self::xmlElementUsagePath($element),
                    'element' => $element->tagName,
                    'fromNamespaceUri' => $parentDefaultNamespace,
                    'toNamespaceUri' => $currentDefaultNamespace,
                ];
            }

            if ($element->hasAttributes()) {
                foreach ($element->attributes as $attribute) {
                    if (!$attribute instanceof \DOMAttr) {
                        continue;
                    }

                    if (self::isNamespaceDeclaration($attribute)) {
                        continue;
                    }

                    ++$attributeCount;
                    $attributeNamespace = self::xmlNamespaceUri($attribute->namespaceURI);
                    $attributePrefix = self::xmlUsagePrefix($attribute->prefix, $attributeNamespace, false);
                    $attributeQualifiedName = $attribute->nodeName;

                    if ($attributeNamespace !== '') {
                        $namespaceUris[$attributeNamespace] = true;
                        ++$namespacedAttributeUseCount;
                    } else {
                        ++$unnamespacedAttributeUseCount;
                    }

                    self::rememberNamespaceUse($attributeUses, $attribute->localName, $attributeNamespace, $attributeQualifiedName);
                    self::rememberNamespaceFrequency($prefixFrequencies, $uriFrequencies, $attributePrefix, $attributeNamespace, $attributeQualifiedName, false);
                }
            }
        }

        $namespaceUris = array_keys($namespaceUris);
        sort($namespaceUris, SORT_STRING);
        $declaredNamespacePrefixes = array_keys($declaredNamespacePrefixes);
        sort($declaredNamespacePrefixes, SORT_STRING);
        $defaultNamespaceUris = array_keys($defaultNamespaceUris);
        sort($defaultNamespaceUris, SORT_STRING);
        usort(
            $namespaceDeclarations,
            static fn (array $left, array $right): int => strcmp($left['path'] . ':' . $left['prefix'], $right['path'] . ':' . $right['prefix'])
        );

        $namespacePrefixFrequencies = self::namespacePrefixFrequencyRows($prefixFrequencies);
        $namespaceUriFrequencies = self::namespaceUriFrequencyRows($uriFrequencies);
        $elementNamespaceCollisions = self::xmlNamespaceCollisionSummaries($elementUses);
        $attributeNamespaceCollisions = self::xmlNamespaceCollisionSummaries($attributeUses);
        $sameUriMultiplePrefixes = self::xmlNamespaceUriAliasSummaries($namespaceUriFrequencies);
        $samePrefixMultipleUris = self::xmlNamespacePrefixAliasSummaries($namespacePrefixFrequencies);
        $diagnostics = self::xmlNamespaceDirectReaderDiagnostics(
            count($elementNamespaceCollisions),
            count($attributeNamespaceCollisions),
            count($defaultNamespaceTransitions),
            $defaultNamespaceUseCount,
            count($sameUriMultiplePrefixes),
            count($samePrefixMultipleUris)
        );

        return [
            'formatFamily' => 'xml-html5-generic-dom',
            'namespaceReview' => 'xml-namespace-usage',
            'namespaceScopeReview' => 'xml-namespace-collision-and-default-scope',
            'reviewPolicy' => 'xml-namespace-usage-diagnostics-review-only',
            'directReaderParity' => false,
            'directReaderDiagnosticCodes' => array_column($diagnostics, 'code'),
            'directReaderDiagnosticCount' => count($diagnostics),
            'directReaderDiagnostics' => $diagnostics,
            'rootName' => $root->localName,
            'rootQualifiedName' => $root->tagName,
            'rootNamespaceUri' => self::xmlNamespaceUri($root->namespaceURI),
            'elementCount' => count($elements),
            'attributeCount' => $attributeCount,
            'namespacedElementUseCount' => $namespacedElementUseCount,
            'unnamespacedElementUseCount' => $unnamespacedElementUseCount,
            'namespacedAttributeUseCount' => $namespacedAttributeUseCount,
            'unnamespacedAttributeUseCount' => $unnamespacedAttributeUseCount,
            'namespaceUris' => $namespaceUris,
            'namespaceUriCount' => count($namespaceUris),
            'namespaceDeclarations' => array_slice($namespaceDeclarations, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
            'namespaceDeclarationCount' => $namespaceDeclarationCount,
            'declaredNamespacePrefixes' => $declaredNamespacePrefixes,
            'namespacePrefixFrequencies' => $namespacePrefixFrequencies,
            'namespacePrefixFrequencyCount' => count($namespacePrefixFrequencies),
            'namespaceUriFrequencies' => $namespaceUriFrequencies,
            'namespaceUriFrequencyCount' => count($namespaceUriFrequencies),
            'elementNamespaceCollisionCount' => count($elementNamespaceCollisions),
            'elementNamespaceCollisions' => $elementNamespaceCollisions,
            'attributeNamespaceCollisionCount' => count($attributeNamespaceCollisions),
            'attributeNamespaceCollisions' => $attributeNamespaceCollisions,
            'defaultNamespaceUseCount' => $defaultNamespaceUseCount,
            'defaultNamespaceUris' => $defaultNamespaceUris,
            'defaultNamespaceUriCount' => count($defaultNamespaceUris),
            'defaultNamespaceTransitionCount' => count($defaultNamespaceTransitions),
            'defaultNamespaceTransitions' => array_slice($defaultNamespaceTransitions, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
            'sameUriMultiplePrefixCount' => count($sameUriMultiplePrefixes),
            'sameUriMultiplePrefixes' => $sameUriMultiplePrefixes,
            'samePrefixMultipleUriCount' => count($samePrefixMultipleUris),
            'samePrefixMultipleUris' => $samePrefixMultipleUris,
        ];
    }

    private static function assertSafeXmlSource(string $xml, string $label): void
    {
        if (preg_match('/<!\s*DOCTYPE\b/i', $xml) === 1 || preg_match('/<!\s*ENTITY\b/i', $xml) === 1) {
            throw new \InvalidArgumentException($label . ' must not contain a doctype or entity declaration');
        }
    }

    private static function assertNoProcessingInstructions(\DOMDocument $dom, string $label): void
    {
        $walker = static function (\DOMNode $node) use (&$walker, $label): void {
            if ($node->nodeType === XML_PI_NODE) {
                throw new \InvalidArgumentException($label . ' must not contain XML processing instructions');
            }

            foreach ($node->childNodes as $child) {
                $walker($child);
            }
        };

        $walker($dom);
    }

    /**
     * @param list<\LibXMLError> $errors
     */
    private static function parseErrorMessage(string $prefix, array $errors): string
    {
        if ($errors === []) {
            return $prefix;
        }

        $messages = [];
        foreach (array_slice($errors, 0, 3) as $error) {
            $message = trim($error->message);
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return $prefix . ': ' . implode('; ', $messages);
    }

    /**
     * @return list<\DOMElement>
     */
    private static function xmlElementsIncludingRoot(\DOMElement $root): array
    {
        $elements = [];
        $stack = [$root];

        while ($stack !== []) {
            $element = array_pop($stack);
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $elements[] = $element;
            for ($index = $element->childNodes->length - 1; $index >= 0; --$index) {
                $child = $element->childNodes->item($index);
                if ($child instanceof \DOMElement) {
                    $stack[] = $child;
                }
            }
        }

        return $elements;
    }

    private static function xmlNamespaceUri(?string $namespaceUri): string
    {
        return $namespaceUri ?? '';
    }

    private static function xmlUsagePrefix(?string $prefix, string $namespaceUri, bool $element): string
    {
        if ($prefix !== null && $prefix !== '') {
            return $prefix;
        }

        if ($element && $namespaceUri !== '') {
            return 'default';
        }

        return 'none';
    }

    private static function parentDefaultNamespace(\DOMElement $element): ?string
    {
        $parent = $element->parentNode;
        if (!$parent instanceof \DOMElement) {
            return null;
        }

        if (($parent->prefix ?? '') !== '') {
            return self::parentDefaultNamespace($parent);
        }

        $namespace = self::xmlNamespaceUri($parent->namespaceURI);

        return $namespace === '' ? null : $namespace;
    }

    private static function isNamespaceDeclaration(\DOMAttr $attribute): bool
    {
        return $attribute->namespaceURI === self::XMLNS_NAMESPACE
            || $attribute->nodeName === 'xmlns'
            || ($attribute->prefix ?? '') === 'xmlns';
    }

    /**
     * @return list<array{path:string, prefix:string, namespaceUri:string}>
     */
    private static function namespaceDeclarationsForElement(\DOMElement $element): array
    {
        $currentScope = self::namespaceScope($element);
        $parent = $element->parentNode;
        $parentScope = $parent instanceof \DOMElement ? self::namespaceScope($parent) : [];
        $declarations = [];

        foreach ($currentScope as $prefix => $namespaceUri) {
            if (array_key_exists($prefix, $parentScope) && $parentScope[$prefix] === $namespaceUri) {
                continue;
            }

            $declarations[] = [
                'path' => self::xmlElementUsagePath($element),
                'prefix' => $prefix,
                'namespaceUri' => $namespaceUri,
            ];
        }

        return $declarations;
    }

    /**
     * @return array<string, string>
     */
    private static function namespaceScope(\DOMElement $element): array
    {
        $document = $element->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('namespace::*', $element);
        $scope = [];
        if ($nodes === false) {
            return $scope;
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMNameSpaceNode) {
                continue;
            }

            $prefix = $node->localName === 'xmlns' ? 'default' : $node->localName;
            if ($prefix === 'xml') {
                continue;
            }

            $scope[$prefix] = (string) ($node->nodeValue ?? '');
        }

        ksort($scope, SORT_STRING);

        return $scope;
    }

    /**
     * @param array<string, array<string, array{namespaceUri:string, count:int, qualifiedNames:array<string, true>}>> $uses
     */
    private static function rememberNamespaceUse(array &$uses, string $localName, string $namespaceUri, string $qualifiedName): void
    {
        $uses[$localName] ??= [];
        $uses[$localName][$namespaceUri] ??= [
            'namespaceUri' => $namespaceUri,
            'count' => 0,
            'qualifiedNames' => [],
        ];
        ++$uses[$localName][$namespaceUri]['count'];
        $uses[$localName][$namespaceUri]['qualifiedNames'][$qualifiedName] = true;
    }

    /**
     * @param array<string, array{prefix:string, namespaceUris:array<string, true>, useCount:int, elementUseCount:int, attributeUseCount:int, qualifiedNames:array<string, true>}> $prefixFrequencies
     * @param array<string, array{namespaceUri:string, prefixes:array<string, true>, useCount:int, elementUseCount:int, attributeUseCount:int, qualifiedNames:array<string, true>}> $uriFrequencies
     */
    private static function rememberNamespaceFrequency(array &$prefixFrequencies, array &$uriFrequencies, string $prefix, string $namespaceUri, string $qualifiedName, bool $element): void
    {
        $prefixFrequencies[$prefix] ??= [
            'prefix' => $prefix,
            'namespaceUris' => [],
            'useCount' => 0,
            'elementUseCount' => 0,
            'attributeUseCount' => 0,
            'qualifiedNames' => [],
        ];
        $prefixFrequencies[$prefix]['namespaceUris'][$namespaceUri] = true;
        ++$prefixFrequencies[$prefix]['useCount'];
        if ($element) {
            ++$prefixFrequencies[$prefix]['elementUseCount'];
        } else {
            ++$prefixFrequencies[$prefix]['attributeUseCount'];
        }
        $prefixFrequencies[$prefix]['qualifiedNames'][$qualifiedName] = true;

        $uriFrequencies[$namespaceUri] ??= [
            'namespaceUri' => $namespaceUri,
            'prefixes' => [],
            'useCount' => 0,
            'elementUseCount' => 0,
            'attributeUseCount' => 0,
            'qualifiedNames' => [],
        ];
        $uriFrequencies[$namespaceUri]['prefixes'][$prefix] = true;
        ++$uriFrequencies[$namespaceUri]['useCount'];
        if ($element) {
            ++$uriFrequencies[$namespaceUri]['elementUseCount'];
        } else {
            ++$uriFrequencies[$namespaceUri]['attributeUseCount'];
        }
        $uriFrequencies[$namespaceUri]['qualifiedNames'][$qualifiedName] = true;
    }

    /**
     * @param array<string, array{prefix:string, namespaceUris:array<string, true>, useCount:int, elementUseCount:int, attributeUseCount:int, qualifiedNames:array<string, true>}> $prefixFrequencies
     * @return list<array<string, mixed>>
     */
    private static function namespacePrefixFrequencyRows(array $prefixFrequencies): array
    {
        ksort($prefixFrequencies, SORT_STRING);
        $rows = [];
        foreach ($prefixFrequencies as $frequency) {
            $namespaceUris = array_keys($frequency['namespaceUris']);
            $qualifiedNames = array_keys($frequency['qualifiedNames']);
            sort($namespaceUris, SORT_STRING);
            sort($qualifiedNames, SORT_STRING);
            $rows[] = [
                'prefix' => $frequency['prefix'],
                'namespaceUris' => array_slice($namespaceUris, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
                'namespaceUriCount' => count($namespaceUris),
                'useCount' => $frequency['useCount'],
                'elementUseCount' => $frequency['elementUseCount'],
                'attributeUseCount' => $frequency['attributeUseCount'],
                'qualifiedNames' => array_slice($qualifiedNames, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, array{namespaceUri:string, prefixes:array<string, true>, useCount:int, elementUseCount:int, attributeUseCount:int, qualifiedNames:array<string, true>}> $uriFrequencies
     * @return list<array<string, mixed>>
     */
    private static function namespaceUriFrequencyRows(array $uriFrequencies): array
    {
        ksort($uriFrequencies, SORT_STRING);
        $rows = [];
        foreach ($uriFrequencies as $frequency) {
            $prefixes = array_keys($frequency['prefixes']);
            $qualifiedNames = array_keys($frequency['qualifiedNames']);
            sort($prefixes, SORT_STRING);
            sort($qualifiedNames, SORT_STRING);
            $rows[] = [
                'namespaceUri' => $frequency['namespaceUri'],
                'prefixes' => array_slice($prefixes, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
                'prefixCount' => count($prefixes),
                'useCount' => $frequency['useCount'],
                'elementUseCount' => $frequency['elementUseCount'],
                'attributeUseCount' => $frequency['attributeUseCount'],
                'qualifiedNames' => array_slice($qualifiedNames, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, array<string, array{namespaceUri:string, count:int, qualifiedNames:array<string, true>}>> $uses
     * @return list<array<string, mixed>>
     */
    private static function xmlNamespaceCollisionSummaries(array $uses): array
    {
        ksort($uses, SORT_STRING);
        $summaries = [];

        foreach ($uses as $localName => $namespaceUses) {
            if (count($namespaceUses) < 2) {
                continue;
            }

            ksort($namespaceUses, SORT_STRING);
            $namespaceUris = [];
            $qualifiedNames = [];
            $namespaceRows = [];
            $useCount = 0;

            foreach ($namespaceUses as $namespaceUse) {
                $namespaceQualifiedNames = array_keys($namespaceUse['qualifiedNames']);
                sort($namespaceQualifiedNames, SORT_STRING);
                $namespaceUris[] = $namespaceUse['namespaceUri'];
                $useCount += $namespaceUse['count'];
                array_push($qualifiedNames, ...$namespaceQualifiedNames);
                $namespaceRows[] = [
                    'namespaceUri' => $namespaceUse['namespaceUri'],
                    'useCount' => $namespaceUse['count'],
                    'qualifiedNames' => array_slice($namespaceQualifiedNames, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
                ];
            }

            $qualifiedNames = array_values(array_unique($qualifiedNames));
            sort($qualifiedNames, SORT_STRING);

            $summaries[] = [
                'localName' => $localName,
                'namespaceUris' => $namespaceUris,
                'namespaceCount' => count($namespaceUris),
                'useCount' => $useCount,
                'qualifiedNames' => array_slice($qualifiedNames, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
                'namespaceUses' => array_slice($namespaceRows, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS),
            ];
        }

        return array_slice($summaries, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS);
    }

    /**
     * @param list<array<string, mixed>> $uriFrequencies
     * @return list<array<string, mixed>>
     */
    private static function xmlNamespaceUriAliasSummaries(array $uriFrequencies): array
    {
        $aliases = [];
        foreach ($uriFrequencies as $frequency) {
            if (($frequency['namespaceUri'] ?? '') === '' || ($frequency['prefixCount'] ?? 0) < 2) {
                continue;
            }
            $aliases[] = $frequency;
        }

        return array_slice($aliases, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS);
    }

    /**
     * @param list<array<string, mixed>> $prefixFrequencies
     * @return list<array<string, mixed>>
     */
    private static function xmlNamespacePrefixAliasSummaries(array $prefixFrequencies): array
    {
        $aliases = [];
        foreach ($prefixFrequencies as $frequency) {
            if (($frequency['namespaceUriCount'] ?? 0) < 2) {
                continue;
            }
            $aliases[] = $frequency;
        }

        return array_slice($aliases, 0, self::XML_NAMESPACE_REVIEW_MAX_ITEMS);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function xmlNamespaceDirectReaderDiagnostics(int $elementCollisionCount, int $attributeCollisionCount, int $defaultNamespaceTransitionCount, int $defaultNamespaceUseCount, int $uriAliasCount, int $prefixAliasCount): array
    {
        $diagnostics = [[
            'code' => 'direct-reader-unsupported',
            'message' => 'No full native PHP Pandoc XML direct reader is registered.',
            'coveredByPacket' => true,
            'directReaderParity' => false,
            'details' => ['directReaderParity' => false],
        ]];

        if ($elementCollisionCount > 0) {
            $diagnostics[] = [
                'code' => 'element-local-name-namespace-collisions',
                'message' => 'Element local names appear under multiple namespace URIs.',
                'coveredByPacket' => true,
                'details' => ['collisionCount' => $elementCollisionCount],
            ];
        }
        if ($attributeCollisionCount > 0) {
            $diagnostics[] = [
                'code' => 'attribute-local-name-namespace-collisions',
                'message' => 'Attribute local names appear under multiple namespace URIs.',
                'coveredByPacket' => true,
                'details' => ['collisionCount' => $attributeCollisionCount],
            ];
        }
        if ($defaultNamespaceTransitionCount > 0) {
            $diagnostics[] = [
                'code' => 'default-namespace-transitions',
                'message' => 'The document changes the default element namespace while traversing the tree.',
                'coveredByPacket' => true,
                'details' => ['transitionCount' => $defaultNamespaceTransitionCount],
            ];
        }
        if ($defaultNamespaceUseCount > 0) {
            $diagnostics[] = [
                'code' => 'default-namespace-usage',
                'message' => 'Default namespaces are used by unprefixed elements.',
                'coveredByPacket' => true,
                'details' => ['useCount' => $defaultNamespaceUseCount],
            ];
        }
        if ($uriAliasCount > 0) {
            $diagnostics[] = [
                'code' => 'namespace-uri-multiple-prefixes',
                'message' => 'A namespace URI is used through multiple prefixes.',
                'coveredByPacket' => true,
                'details' => ['aliasCount' => $uriAliasCount],
            ];
        }
        if ($prefixAliasCount > 0) {
            $diagnostics[] = [
                'code' => 'namespace-prefix-multiple-uris',
                'message' => 'A namespace prefix is rebound to multiple namespace URIs.',
                'coveredByPacket' => true,
                'details' => ['aliasCount' => $prefixAliasCount],
            ];
        }

        return $diagnostics;
    }

    private static function xmlElementUsagePath(\DOMElement $element): string
    {
        $segments = [];
        $current = $element;

        while ($current instanceof \DOMElement) {
            $index = 1;
            $sibling = $current->previousSibling;
            while ($sibling !== null) {
                if ($sibling instanceof \DOMElement && $sibling->tagName === $current->tagName) {
                    ++$index;
                }
                $sibling = $sibling->previousSibling;
            }

            array_unshift($segments, $current->tagName . '[' . $index . ']');
            $parent = $current->parentNode;
            $current = $parent instanceof \DOMElement ? $parent : null;
        }

        return '/' . implode('/', $segments);
    }
}
