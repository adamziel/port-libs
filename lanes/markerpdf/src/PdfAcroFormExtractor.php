<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfAcroFormExtractor
{
    private const COMMON_FIELD_FLAGS = [
        1 => 'read_only',
        2 => 'required',
        3 => 'no_export',
    ];

    private const FIELD_TYPE_FLAGS = [
        'Tx' => [
            13 => 'multiline',
            14 => 'password',
            21 => 'file_select',
            23 => 'do_not_spell_check',
            24 => 'do_not_scroll',
            25 => 'comb',
            26 => 'rich_text',
        ],
        'Btn' => [
            15 => 'no_toggle_to_off',
            16 => 'radio',
            17 => 'push_button',
            26 => 'radios_in_unison',
        ],
        'Ch' => [
            18 => 'combo',
            19 => 'edit',
            20 => 'sort',
            22 => 'multi_select',
            23 => 'do_not_spell_check',
            27 => 'commit_on_sel_change',
        ],
    ];

    /**
     * Native boundary for PDF AcroForm field dictionaries.
     *
     * @return array{need_appearances: bool, permissions: array<string, mixed>, xfa_overrides_page_content: bool, xfa_packets: list<array<string, mixed>>, calculation_order: list<array{object: int, field_name: string|null}>, fields: list<array<string, mixed>>}
     */
    public function extractForm(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($objects);
        $permissions = $catalog === null ? $this->emptyPermissions() : $this->documentPermissions($catalog, $objects);
        $acroForm = $catalog === null ? null : $this->acroFormDictionaryBody($catalog, $objects);
        if ($acroForm === null) {
            return [
                'need_appearances' => false,
                'permissions' => $permissions,
                'xfa_overrides_page_content' => false,
                'xfa_packets' => [],
                'calculation_order' => [],
                'fields' => [],
            ];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageIndexes = array_flip($pageObjectNumbers);
        $pageWidgets = $this->pageWidgetMap($objects, $pageObjectNumbers);
        $formDefaults = $this->acroFormDefaults($acroForm);
        $xfaPackets = $this->xfaPacketsFromAcroForm($acroForm, $objects);
        $fields = [];
        $fieldRefs = $this->fieldReferencesFromAcroForm($acroForm);
        $fieldNamesByObject = $this->fieldNamesByObject($fieldRefs, $objects);
        $calculationOrder = $this->calculationOrderFromAcroForm($acroForm, $fieldNamesByObject);

        foreach ($fieldRefs as $fieldRef) {
            foreach ($this->fieldsFromObject(
                $fieldRef,
                $objects,
                $formDefaults,
                [],
                [],
                $pageIndexes,
                $pageWidgets,
                $fieldNamesByObject
            ) as $field) {
                $fields[] = $field;
            }
        }

        $fields = $this->markCertifyingSignatureFields($fields, $permissions);

        return [
            'need_appearances' => $this->boolValueAfterName($acroForm, 'NeedAppearances') === true,
            'permissions' => $permissions,
            'xfa_overrides_page_content' => $xfaPackets !== [],
            'xfa_packets' => $xfaPackets,
            'calculation_order' => $calculationOrder,
            'fields' => $fields,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function extractFields(string $pdfBytes): array
    {
        return $this->extractForm($pdfBytes)['fields'];
    }

    /**
     * @return list<array<string, mixed>>
     * @param array<int, string> $objects
     */
    private function xfaPacketsFromAcroForm(string $acroForm, array $objects): array
    {
        $xfa = $this->valueAfterName($acroForm, 'XFA');
        if ($xfa === null) {
            return [];
        }

        $xfa = trim($xfa);
        if ($xfa === '') {
            return [];
        }

        if (str_starts_with($xfa, '[')) {
            $body = $this->arrayBodyFromValue($xfa);
            if ($body === null) {
                return [];
            }

            $tokens = $this->xfaArrayTokens($body);
            $packets = [];
            for ($index = 0, $count = count($tokens); $index < $count; $index++) {
                $token = $tokens[$index];
                $packetName = $token['type'] === 'string' ? $token['value'] : null;
                if ($packetName === null) {
                    $packet = $this->xfaPacketFromToken($token, $objects, null, count($packets), 'acroform_xfa_array');
                    if ($packet !== null) {
                        $packets[] = $packet;
                    }
                    continue;
                }

                $valueToken = $tokens[$index + 1] ?? null;
                if ($valueToken === null) {
                    break;
                }

                $packet = $this->xfaPacketFromToken($valueToken, $objects, $packetName, count($packets), 'acroform_xfa_array');
                if ($packet !== null) {
                    $packets[] = $packet;
                }
                $index++;
            }

            return $packets;
        }

        $token = $this->xfaTokenFromValue($xfa);
        $packet = $token === null ? null : $this->xfaPacketFromToken($token, $objects, null, 0, 'acroform_xfa_value');

        return $packet === null ? [] : [$packet];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xfaArrayTokens(string $body): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $token = $this->readXfaTokenAt($body, $offset);
            if ($token === null) {
                $offset++;
                continue;
            }

            $offset = $token['end'];
            unset($token['end']);
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @return array{type: string, value?: string, object?: int, end: int}|null
     */
    private function readXfaTokenAt(string $body, int $offset): ?array
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
            return [
                'type' => 'reference',
                'object' => (int) $match[1],
                'end' => $offset + strlen($match[0]),
            ];
        }

        if ($body[$offset] === '(') {
            $end = $this->skipLiteralString($body, $offset);
            return [
                'type' => 'string',
                'value' => $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $offset + 1, $end - $offset - 2))),
                'end' => $end,
            ];
        }

        if ($body[$offset] === '<' && substr($body, $offset, 2) !== '<<') {
            $end = $this->skipHexString($body, $offset);
            $hex = preg_replace('/\s+/', '', substr($body, $offset + 1, $end - $offset - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = $hex === '' ? '' : hex2bin($hex);
            if ($bytes === false) {
                return null;
            }

            return [
                'type' => 'string',
                'value' => $this->decodePdfStringBytes($bytes),
                'end' => $end,
            ];
        }

        if ($body[$offset] === '/') {
            $end = $this->skipPdfName($body, $offset);
            return [
                'type' => 'string',
                'value' => $this->decodePdfName(substr($body, $offset, $end - $offset)),
                'end' => $end,
            ];
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $dictionary = $this->readPdfDictionaryAt($body, $offset, $endOffset);
            if ($dictionary === null || $endOffset === null) {
                return null;
            }

            return [
                'type' => 'dictionary',
                'value' => $dictionary,
                'end' => $endOffset,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function xfaPacketFromToken(array $token, array $objects, ?string $packetName, int $index, string $source): ?array
    {
        $payload = $this->xfaPayloadFromToken($token, $objects);
        if ($payload === null) {
            return null;
        }

        $xml = trim($payload['xml']);
        if ($xml === '') {
            return null;
        }

        $root = $this->xmlRootName($xml);
        $name = $packetName !== null && $packetName !== '' ? $packetName : ($root ?? 'xfa');

        return [
            'index' => $index,
            'name' => $name,
            'object' => $payload['object'],
            'source' => $source,
            'filters' => $payload['filters'],
            'xml_root' => $root,
            'length_bytes' => strlen($xml),
            'xml_sha256' => hash('sha256', $xml),
            'field_names' => $this->xfaFieldNames($xml),
            'data_node_names' => $this->xfaDataNodeNames($xml),
            'has_template' => $name === 'template' || $this->xmlLocalName($root) === 'template' || stripos($xml, '<template') !== false,
            'has_datasets' => $name === 'datasets' || $this->xmlLocalName($root) === 'datasets' || stripos($xml, 'datasets') !== false,
            'text_preview' => $this->xmlTextPreview($xml),
        ];
    }

    /**
     * @return array{xml: string, object: int|null, filters: list<string>}|null
     * @param array<int, string> $objects
     */
    private function xfaPayloadFromToken(array $token, array $objects): ?array
    {
        if (($token['type'] ?? null) === 'string') {
            return [
                'xml' => (string) ($token['value'] ?? ''),
                'object' => null,
                'filters' => [],
            ];
        }

        if (($token['type'] ?? null) === 'dictionary') {
            return [
                'xml' => (string) ($token['value'] ?? ''),
                'object' => null,
                'filters' => [],
            ];
        }

        if (($token['type'] ?? null) !== 'reference') {
            return null;
        }

        $objectNumber = (int) ($token['object'] ?? 0);
        if ($objectNumber <= 0 || !isset($objects[$objectNumber])) {
            return null;
        }

        $stream = $this->decodeStreamObject($objects[$objectNumber], $objects);
        if ($stream !== null) {
            return [
                'xml' => $stream,
                'object' => $objectNumber,
                'filters' => $this->streamObjectFilters($objects[$objectNumber], $objects),
            ];
        }

        $value = $this->pdfValueToString(trim($objects[$objectNumber]), $objects);

        return [
            'xml' => $value ?? trim($objects[$objectNumber]),
            'object' => $objectNumber,
            'filters' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function xfaTokenFromValue(string $value): ?array
    {
        $token = $this->readXfaTokenAt($value, 0);
        if ($token === null) {
            return null;
        }

        unset($token['end']);
        return $token;
    }

    private function xmlRootName(string $xml): ?string
    {
        return preg_match('/<\s*([A-Za-z_][A-Za-z0-9_.:-]*)\b/s', $xml, $match) === 1
            ? $match[1]
            : null;
    }

    private function xmlLocalName(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $parts = explode(':', $name);
        return end($parts) ?: $name;
    }

    /**
     * @return list<string>
     */
    private function xfaFieldNames(string $xml): array
    {
        if (preg_match_all('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?field\b[^>]*\bname\s*=\s*(["\'])(.*?)\1/si', $xml, $matches) === false) {
            return [];
        }

        $names = [];
        foreach ($matches[2] as $name) {
            $decoded = $this->decodeXmlText($name);
            if ($decoded !== '' && !in_array($decoded, $names, true)) {
                $names[] = $decoded;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function xfaDataNodeNames(string $xml): array
    {
        if (preg_match('/<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?data\b[^>]*>(.*?)<\/(?:[A-Za-z_][A-Za-z0-9_.-]*:)?data>/si', $xml, $match) !== 1) {
            return [];
        }

        if (preg_match_all('/<\s*([A-Za-z_][A-Za-z0-9_.:-]*)\b(?![^>]*\/>)/s', $match[1], $tagMatches) === false) {
            return [];
        }

        $names = [];
        foreach ($tagMatches[1] as $tagName) {
            $localName = $this->xmlLocalName($tagName);
            if ($localName === null || in_array($localName, ['data'], true) || in_array($localName, $names, true)) {
                continue;
            }

            $names[] = $localName;
        }

        return $names;
    }

    private function xmlTextPreview(string $xml): string
    {
        $withoutDeclarations = preg_replace('/<\?(?:.|\R)*?\?>|<!\[CDATA\[|]]>/s', ' ', $xml) ?? $xml;
        $text = strip_tags($withoutDeclarations);
        $text = $this->decodeXmlText($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);

        return strlen($text) > 180 ? substr($text, 0, 177) . '...' : $text;
    }

    private function decodeXmlText(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * @return list<array<string, mixed>>
     * @param array<int, string> $objects
     * @param array<string, array{value: string, source: string, source_object: int|null}> $inherited
     * @param list<string> $nameParts
     * @param array<int, true> $seen
     * @param array<int, int> $pageIndexes
     * @param array<int, array{page_index: int, page_object: int}> $pageWidgets
     * @param array<int, string> $fieldNamesByObject
     */
    private function fieldsFromObject(
        int $objectNumber,
        array $objects,
        array $inherited,
        array $nameParts,
        array $seen,
        array $pageIndexes,
        array $pageWidgets,
        array $fieldNamesByObject
    ): array {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $this->dictionaryObjectBody($objects[$objectNumber]) ?? trim($objects[$objectNumber]);
        $effective = $this->mergeFieldAttributes($body, $inherited, $objectNumber);
        $partialName = $this->pdfStringValueAfterName($body, 'T', $objects);
        $mappingName = $this->pdfStringValueAfterName($body, 'TM', $objects);
        $currentNameParts = $nameParts;
        if ($partialName !== null && $partialName !== '') {
            $currentNameParts[] = $partialName;
        }

        $kidRefs = $this->kidReferences($body);
        $childFieldRefs = [];
        $widgetRefs = [];
        foreach ($kidRefs as $kidRef) {
            if (!isset($objects[$kidRef])) {
                continue;
            }

            $kidBody = $this->dictionaryObjectBody($objects[$kidRef]) ?? trim($objects[$kidRef]);
            if ($this->isPureWidget($kidBody)) {
                $widgetRefs[] = $kidRef;
                continue;
            }

            $childFieldRefs[] = $kidRef;
        }

        if ($childFieldRefs !== []) {
            $fields = [];
            foreach ($childFieldRefs as $childRef) {
                foreach ($this->fieldsFromObject(
                    $childRef,
                    $objects,
                    $effective,
                    $currentNameParts,
                    $seen,
                    $pageIndexes,
                    $pageWidgets,
                    $fieldNamesByObject
                ) as $field) {
                    $fields[] = $field;
                }
            }

            return $fields;
        }

        if ($this->isWidget($body)) {
            array_unshift($widgetRefs, $objectNumber);
        }

        $fieldType = $this->fieldType($effective);
        if ($fieldType === null && $partialName === null && $mappingName === null) {
            return [];
        }

        $flags = $this->integerFromEffective($effective, 'Ff', 0);
        $defaultAppearance = $this->defaultAppearanceFromEffective($effective);
        $password = $fieldType === 'Tx' && $this->hasFlagBit($flags, 14);

        $name = $currentNameParts === [] ? '#' . $objectNumber : implode('.', $currentNameParts);
        $field = [
            'object' => $objectNumber,
            'name' => $name,
            'partial_name' => $partialName,
            'mapping_name' => $mappingName ?? $name,
            'field_type' => $fieldType,
            'field_type_label' => $this->fieldTypeLabel($fieldType),
            'flags' => $flags,
            'flag_names' => $this->flagNames($flags, $fieldType),
            'value' => $password ? null : $this->valueFromEffective($effective, 'V', $objects),
            'value_redacted' => $password,
            'default_value' => $password ? null : $this->valueFromEffective($effective, 'DV', $objects),
            'default_appearance' => $defaultAppearance,
            'actions' => $this->actionsFromDictionary($body, $objects, $fieldNamesByObject, 'field', $objectNumber),
            'widgets' => $this->widgetsForField($widgetRefs, $objects, $defaultAppearance, $pageIndexes, $pageWidgets, $fieldNamesByObject),
        ];

        if ($fieldType === 'Ch') {
            $field['options'] = $this->optionsFromEffective($effective, $objects);
        }
        if ($fieldType === 'Sig') {
            $field['signature'] = isset($effective['V'])
                ? $this->signatureMetadataFromValue($effective['V']['value'], $objects)
                : null;
            $field['certifying_signature'] = false;
        }

        return [$field];
    }

    /**
     * @return array{doc_mdp: array<string, mixed>|null}
     */
    private function emptyPermissions(): array
    {
        return ['doc_mdp' => null];
    }

    /**
     * @param array<int, string> $objects
     * @return array{doc_mdp: array<string, mixed>|null}
     */
    private function documentPermissions(string $catalog, array $objects): array
    {
        $permsValue = $this->valueAfterName($catalog, 'Perms');
        $perms = $permsValue === null ? null : $this->resolvedDictionaryFromValue($permsValue, $objects);
        if ($perms === null) {
            return $this->emptyPermissions();
        }

        $docMdpValue = $this->valueAfterName($perms['body'], 'DocMDP');
        $signature = $docMdpValue === null ? null : $this->signatureMetadataFromValue($docMdpValue, $objects);
        if ($signature === null) {
            return $this->emptyPermissions();
        }

        $docMdpTransform = $this->docMdpTransformFromSignatureMetadata($signature);

        return [
            'doc_mdp' => [
                'signature_object' => $signature['object'],
                'signature_name' => $signature['name'],
                'signed_at' => $signature['signed_at'],
                'permission_level' => $docMdpTransform['permission_level'] ?? null,
                'permission_label' => $docMdpTransform['permission_label'] ?? 'unknown',
                'allowed_changes' => $docMdpTransform['allowed_changes'] ?? [],
                'transform_params_version' => $docMdpTransform['transform_params_version'] ?? null,
                'source' => 'catalog_perms_doc_mdp',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $permissions
     * @return list<array<string, mixed>>
     */
    private function markCertifyingSignatureFields(array $fields, array $permissions): array
    {
        $docMdpObject = is_array($permissions['doc_mdp'] ?? null)
            ? ($permissions['doc_mdp']['signature_object'] ?? null)
            : null;
        if (!is_int($docMdpObject)) {
            return $fields;
        }

        foreach ($fields as $index => $field) {
            $signature = $field['signature'] ?? null;
            if (!is_array($signature) || ($signature['object'] ?? null) !== $docMdpObject) {
                continue;
            }

            $fields[$index]['certifying_signature'] = true;
            $fields[$index]['signature']['certifying_signature'] = true;
        }

        return $fields;
    }

    /**
     * @param array<int, string> $fieldNamesByObject
     * @return list<array{object: int, field_name: string|null}>
     */
    private function calculationOrderFromAcroForm(string $acroForm, array $fieldNamesByObject): array
    {
        $value = $this->valueAfterName($acroForm, 'CO');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $order = [];
        foreach (array_values(array_unique($this->objectReferences($body))) as $objectNumber) {
            $order[] = [
                'object' => $objectNumber,
                'field_name' => $fieldNamesByObject[$objectNumber] ?? null,
            ];
        }

        return $order;
    }

    /**
     * @param array<string, mixed> $signature
     * @return array<string, mixed>|null
     */
    private function docMdpTransformFromSignatureMetadata(array $signature): ?array
    {
        foreach ($signature['reference_transforms'] ?? [] as $transform) {
            if (is_array($transform) && ($transform['transform_method'] ?? null) === 'DocMDP') {
                return $transform;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureMetadataFromValue(string $value, array $objects): ?array
    {
        $signature = $this->resolvedDictionaryFromValue($value, $objects);
        if ($signature === null) {
            return null;
        }

        $body = $signature['body'];
        $contentsValue = $this->valueAfterName($body, 'Contents');
        $metadata = [
            'object' => $signature['object'],
            'filter' => $this->pdfNameValueAfterName($body, 'Filter'),
            'subfilter' => $this->pdfNameValueAfterName($body, 'SubFilter'),
            'name' => $this->pdfStringValueAfterName($body, 'Name', $objects),
            'reason' => $this->pdfStringValueAfterName($body, 'Reason', $objects),
            'location' => $this->pdfStringValueAfterName($body, 'Location', $objects),
            'contact_info' => $this->pdfStringValueAfterName($body, 'ContactInfo', $objects),
            'signed_at' => $this->pdfStringValueAfterName($body, 'M', $objects),
            'byte_range' => $this->integerArrayValueAfterName($body, 'ByteRange'),
            'contents_present' => $contentsValue !== null,
            'contents_length_bytes' => $contentsValue === null ? null : $this->signatureContentsLength($contentsValue, $objects),
            'reference_transforms' => $this->signatureReferenceTransforms($body, $objects),
            'certifying_signature' => false,
        ];

        $docMdp = $this->docMdpTransformFromSignatureMetadata($metadata);
        if ($docMdp !== null) {
            $metadata['doc_mdp'] = $docMdp;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function signatureReferenceTransforms(string $signatureBody, array $objects): array
    {
        $reference = $this->valueAfterName($signatureBody, 'Reference');
        if ($reference === null || !str_starts_with(trim($reference), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($reference);
        if ($body === null) {
            return [];
        }

        $transforms = [];
        foreach ($this->dictionaryValuesFromArrayBody($body, $objects) as $dictionary) {
            $transformBody = $dictionary['body'];
            $method = $this->pdfNameValueAfterName($transformBody, 'TransformMethod');
            if ($method === null) {
                continue;
            }

            $transform = [
                'transform_method' => $method,
                'data_object' => $this->objectReferenceValueAfterName($transformBody, 'Data'),
                'digest_method' => $this->pdfNameValueAfterName($transformBody, 'DigestMethod'),
            ];

            $params = $this->docMdpTransformParams($transformBody, $objects, $method);
            if ($params !== null) {
                $transform += $params;
            }

            $transforms[] = $transform;
        }

        return $transforms;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function docMdpTransformParams(string $referenceBody, array $objects, string $method): ?array
    {
        $paramsValue = $this->valueAfterName($referenceBody, 'TransformParams');
        $params = $paramsValue === null ? null : $this->resolvedDictionaryFromValue($paramsValue, $objects);
        if ($method !== 'DocMDP' && $params === null) {
            return null;
        }

        $paramsBody = $params['body'] ?? '';
        $level = $paramsBody === '' ? null : $this->numberValueAfterName($paramsBody, 'P');
        if ($method === 'DocMDP' && $level === null) {
            $level = 2;
        }

        return [
            'transform_params_object' => $params['object'] ?? null,
            'transform_params_type' => $paramsBody === '' ? null : $this->pdfNameValueAfterName($paramsBody, 'Type'),
            'transform_params_version' => $paramsBody === '' ? null : $this->pdfNameValueAfterName($paramsBody, 'V'),
            'permission_level' => $level,
            'permission_valid' => in_array($level, [1, 2, 3], true),
            'permission_label' => $this->docMdpPermissionLabel($level),
            'allowed_changes' => $this->docMdpAllowedChanges($level),
        ];
    }

    private function docMdpPermissionLabel(?int $level): string
    {
        return match ($level) {
            1 => 'no_changes',
            2 => 'form_fill_templates_signatures',
            3 => 'form_fill_templates_signatures_annotations',
            default => 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    private function docMdpAllowedChanges(?int $level): array
    {
        return match ($level) {
            1 => [],
            2 => ['fill_forms', 'instantiate_page_templates', 'sign'],
            3 => ['fill_forms', 'instantiate_page_templates', 'sign', 'create_modify_delete_annotations'],
            default => [],
        };
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $inherited
     * @return array<string, array{value: string, source: string, source_object: int|null}>
     */
    private function mergeFieldAttributes(string $body, array $inherited, int $objectNumber): array
    {
        $effective = $inherited;
        foreach (['FT', 'Ff', 'V', 'DV', 'DA', 'Q', 'Opt'] as $name) {
            $value = $this->valueAfterName($body, $name);
            if ($value === null) {
                continue;
            }

            $effective[$name] = [
                'value' => $value,
                'source' => 'field',
                'source_object' => $objectNumber,
            ];
        }

        return $effective;
    }

    /**
     * @return array<string, array{value: string, source: string, source_object: int|null}>
     */
    private function acroFormDefaults(string $acroForm): array
    {
        $defaults = [];
        foreach (['DA', 'Q'] as $name) {
            $value = $this->valueAfterName($acroForm, $name);
            if ($value !== null) {
                $defaults[$name] = [
                    'value' => $value,
                    'source' => 'acroform',
                    'source_object' => null,
                ];
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function fieldType(array $effective): ?string
    {
        if (!isset($effective['FT'])) {
            return null;
        }

        $value = trim($effective['FT']['value']);
        if (!str_starts_with($value, '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    private function fieldTypeLabel(?string $fieldType): string
    {
        return match ($fieldType) {
            'Tx' => 'text',
            'Ch' => 'choice',
            'Btn' => 'button',
            'Sig' => 'signature',
            default => 'unknown',
        };
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function integerFromEffective(array $effective, string $name, int $default): int
    {
        if (!isset($effective[$name])) {
            return $default;
        }

        $value = trim($effective[$name]['value']);
        return preg_match('/^[+-]?\d+/', $value, $match) === 1 ? (int) $match[0] : $default;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function valueFromEffective(array $effective, string $name, array $objects): mixed
    {
        if (!isset($effective[$name])) {
            return null;
        }

        return $this->pdfValueToPhpValue($effective[$name]['value'], $objects);
    }

    /**
     * @return list<string>
     */
    private function flagNames(int $flags, ?string $fieldType): array
    {
        $names = [];
        foreach (self::COMMON_FIELD_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        foreach (self::FIELD_TYPE_FLAGS[$fieldType ?? ''] ?? [] as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function hasFlagBit(int $flags, int $oneBasedBit): bool
    {
        return ($flags & (1 << ($oneBasedBit - 1))) !== 0;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return array<string, mixed>|null
     */
    private function defaultAppearanceFromEffective(array $effective): ?array
    {
        if (!isset($effective['DA'])) {
            return null;
        }

        $raw = $this->pdfValueToString($effective['DA']['value'], []);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $appearance = $this->parseDefaultAppearance($raw);
        $appearance['raw'] = $raw;
        $appearance['source'] = $effective['DA']['source'];
        $appearance['source_object'] = $effective['DA']['source_object'];

        return $appearance;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDefaultAppearance(string $appearance): array
    {
        $tokens = $this->appearanceTokens($appearance);
        $parsed = [
            'font_resource' => null,
            'font_size' => null,
            'text_color' => null,
        ];

        foreach ($tokens as $index => $token) {
            if ($token === 'Tf' && isset($tokens[$index - 2], $tokens[$index - 1])) {
                $font = $tokens[$index - 2];
                $size = $tokens[$index - 1];
                if (is_string($font) && str_starts_with($font, '/') && is_numeric($size)) {
                    $parsed['font_resource'] = $this->decodePdfName($font);
                    $parsed['font_size'] = (float) $size;
                }
                continue;
            }

            if ($token === 'g' && isset($tokens[$index - 1]) && is_numeric($tokens[$index - 1])) {
                $parsed['text_color'] = [
                    'space' => 'DeviceGray',
                    'components' => [(float) $tokens[$index - 1]],
                ];
                continue;
            }

            if ($token === 'rg' && isset($tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1])) {
                $components = [$tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1]];
                if ($this->allNumeric($components)) {
                    $parsed['text_color'] = [
                        'space' => 'DeviceRGB',
                        'components' => array_map('floatval', $components),
                    ];
                }
                continue;
            }

            if ($token === 'k' && isset($tokens[$index - 4], $tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1])) {
                $components = [$tokens[$index - 4], $tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1]];
                if ($this->allNumeric($components)) {
                    $parsed['text_color'] = [
                        'space' => 'DeviceCMYK',
                        'components' => array_map('floatval', $components),
                    ];
                }
            }
        }

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function appearanceTokens(string $appearance): array
    {
        if (preg_match_all('/\/(?:#[0-9A-Fa-f]{2}|[^\s\[\]\(\)<>{}\/%])+|[+-]?(?:\d+(?:\.\d*)?|\.\d+)|[A-Za-z][A-Za-z0-9*]*/', $appearance, $matches) === false) {
            return [];
        }

        return $matches[0];
    }

    /**
     * @param list<mixed> $values
     */
    private function allNumeric(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $widgetRefs
     * @param array<int, string> $objects
     * @param array<string, mixed>|null $fieldDefaultAppearance
     * @param array<int, int> $pageIndexes
     * @param array<int, array{page_index: int, page_object: int}> $pageWidgets
     * @param array<int, string> $fieldNamesByObject
     * @return list<array<string, mixed>>
     */
    private function widgetsForField(
        array $widgetRefs,
        array $objects,
        ?array $fieldDefaultAppearance,
        array $pageIndexes,
        array $pageWidgets,
        array $fieldNamesByObject
    ): array {
        $widgets = [];
        $seen = [];
        foreach ($widgetRefs as $widgetRef) {
            if (isset($seen[$widgetRef]) || !isset($objects[$widgetRef])) {
                continue;
            }
            $seen[$widgetRef] = true;

            $body = $this->dictionaryObjectBody($objects[$widgetRef]) ?? trim($objects[$widgetRef]);
            $pageObject = $this->objectReferenceValueAfterName($body, 'P') ?? ($pageWidgets[$widgetRef]['page_object'] ?? null);
            $pageIndex = $pageObject !== null && isset($pageIndexes[$pageObject])
                ? $pageIndexes[$pageObject]
                : ($pageWidgets[$widgetRef]['page_index'] ?? null);
            $annotationFlags = $this->numberValueAfterName($body, 'F');
            $widgetAppearance = $this->widgetDefaultAppearance($body, $fieldDefaultAppearance);

            $widgets[] = [
                'object' => $widgetRef,
                'page_index' => $pageIndex,
                'page_object' => $pageObject,
                'rect' => $this->rectFromAnnotation($body),
                'annotation_flags' => $annotationFlags,
                'hidden' => $annotationFlags !== null && (($annotationFlags & 3) !== 0 || ($annotationFlags & 32) !== 0),
                'appearance_state' => $this->pdfNameValueAfterName($body, 'AS'),
                'appearance_states' => $this->normalAppearanceStates($body),
                'default_appearance' => $widgetAppearance,
                'actions' => $this->actionsFromDictionary($body, $objects, $fieldNamesByObject, 'widget', $widgetRef),
            ];
        }

        return $widgets;
    }

    /**
     * @param list<int> $fieldRefs
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function fieldNamesByObject(array $fieldRefs, array $objects): array
    {
        $names = [];
        foreach ($fieldRefs as $fieldRef) {
            $this->collectFieldNamesByObject($fieldRef, $objects, [], $names, []);
        }

        return $names;
    }

    /**
     * @param array<int, string> $objects
     * @param list<string> $nameParts
     * @param array<int, string> $names
     * @param array<int, true> $seen
     */
    private function collectFieldNamesByObject(
        int $objectNumber,
        array $objects,
        array $nameParts,
        array &$names,
        array $seen
    ): void {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return;
        }

        $seen[$objectNumber] = true;
        $body = $this->dictionaryObjectBody($objects[$objectNumber]) ?? trim($objects[$objectNumber]);
        $partialName = $this->pdfStringValueAfterName($body, 'T', $objects);
        $currentNameParts = $nameParts;
        if ($partialName !== null && $partialName !== '') {
            $currentNameParts[] = $partialName;
        }

        $fieldName = $currentNameParts === [] ? '#' . $objectNumber : implode('.', $currentNameParts);
        if (
            $partialName !== null
            || $this->pdfStringValueAfterName($body, 'TM', $objects) !== null
            || $this->valueAfterName($body, 'FT') !== null
            || $this->valueAfterName($body, 'Kids') !== null
            || $this->isWidget($body)
        ) {
            $names[$objectNumber] = $fieldName;
        }

        foreach ($this->kidReferences($body) as $kidRef) {
            if (!isset($objects[$kidRef])) {
                continue;
            }

            $kidBody = $this->dictionaryObjectBody($objects[$kidRef]) ?? trim($objects[$kidRef]);
            if ($this->isPureWidget($kidBody)) {
                $names[$kidRef] = $fieldName;
                continue;
            }

            $this->collectFieldNamesByObject($kidRef, $objects, $currentNameParts, $names, $seen);
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return list<array<string, mixed>>
     */
    private function actionsFromDictionary(
        string $body,
        array $objects,
        array $fieldNamesByObject,
        string $source,
        int $sourceObject
    ): array {
        $actions = [];
        $activation = $this->valueAfterName($body, 'A');
        if ($activation !== null) {
            foreach ($this->actionMetadataFromValue($activation, $objects, $fieldNamesByObject, 'activation', $source, $sourceObject) as $action) {
                $actions[] = $action;
            }
        }

        $additionalActions = $this->valueAfterName($body, 'AA');
        $additionalActionsDictionary = $additionalActions === null ? null : $this->resolvedDictionaryFromValue($additionalActions, $objects);
        if ($additionalActionsDictionary === null) {
            return $actions;
        }

        foreach (['E', 'X', 'D', 'U', 'Fo', 'Bl', 'K', 'F', 'V', 'C'] as $trigger) {
            $value = $this->valueAfterName($additionalActionsDictionary['body'], $trigger);
            if ($value === null) {
                continue;
            }

            foreach ($this->actionMetadataFromValue($value, $objects, $fieldNamesByObject, $trigger, $source, $sourceObject) as $action) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @param array<int, true> $seenActionObjects
     * @return list<array<string, mixed>>
     */
    private function actionMetadataFromValue(
        string $value,
        array $objects,
        array $fieldNamesByObject,
        string $trigger,
        string $source,
        int $sourceObject,
        array $seenActionObjects = []
    ): array {
        $action = $this->resolvedDictionaryFromValue($value, $objects);
        if ($action === null) {
            return [];
        }

        $actionObject = $action['object'];
        if ($actionObject !== null) {
            if (isset($seenActionObjects[$actionObject])) {
                return [];
            }
            $seenActionObjects[$actionObject] = true;
        }

        $actions = [];
        $metadata = $this->actionMetadataFromBody(
            $action['body'],
            $objects,
            $fieldNamesByObject,
            $trigger,
            $source,
            $sourceObject,
            $actionObject
        );
        if ($metadata !== null) {
            $actions[] = $metadata;
        }

        $next = $this->valueAfterName($action['body'], 'Next');
        if ($next === null) {
            return $actions;
        }

        $nextValue = trim($next);
        if (str_starts_with($nextValue, '[')) {
            $arrayBody = $this->arrayBodyFromValue($nextValue);
            if ($arrayBody === null) {
                return $actions;
            }

            foreach ($this->dictionaryValuesFromArrayBody($arrayBody, $objects) as $nextDictionary) {
                $nextMetadata = $this->actionMetadataFromBody(
                    $nextDictionary['body'],
                    $objects,
                    $fieldNamesByObject,
                    $trigger,
                    $source,
                    $sourceObject,
                    $nextDictionary['object']
                );
                if ($nextMetadata !== null) {
                    $nextMetadata['chained'] = true;
                    $actions[] = $nextMetadata;
                }
            }

            return $actions;
        }

        foreach ($this->actionMetadataFromValue($nextValue, $objects, $fieldNamesByObject, $trigger, $source, $sourceObject, $seenActionObjects) as $nextAction) {
            $nextAction['chained'] = true;
            $actions[] = $nextAction;
        }

        return $actions;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array<string, mixed>|null
     */
    private function actionMetadataFromBody(
        string $actionBody,
        array $objects,
        array $fieldNamesByObject,
        string $trigger,
        string $source,
        int $sourceObject,
        ?int $actionObject
    ): ?array {
        $actionType = $this->pdfNameValueAfterName($actionBody, 'S');
        if ($actionType === 'JavaScript') {
            return $this->javaScriptActionMetadataFromBody(
                $actionBody,
                $objects,
                $trigger,
                $source,
                $sourceObject,
                $actionObject
            );
        }

        if (!in_array($actionType, ['SubmitForm', 'ResetForm'], true)) {
            return null;
        }

        $flags = $this->numberValueAfterName($actionBody, 'Flags') ?? 0;
        $selection = $this->fieldSelectionFromAction($actionBody, $objects, $fieldNamesByObject, $actionType, $flags);
        $metadata = [
            'action_type' => $actionType,
            'trigger' => $trigger,
            'trigger_label' => $this->actionTriggerLabel($trigger),
            'source' => $source,
            'source_object' => $sourceObject,
            'action_object' => $actionObject,
            'flags' => $flags,
            'flag_names' => $this->actionFlagNames($actionType, $flags),
            'fields_mode' => $selection['fields_mode'],
            'field_objects' => $selection['field_objects'],
            'field_names' => $selection['field_names'],
            'unresolved_field_objects' => $selection['unresolved_field_objects'],
            'executes_action' => false,
        ];

        if ($actionType === 'SubmitForm') {
            $targetValue = $this->valueAfterName($actionBody, 'F');
            $target = $targetValue === null ? null : $this->fileSpecificationFromValue($targetValue, $objects);
            $metadata += [
                'target' => $target,
                'target_scheme' => $target === null ? null : $this->uriScheme($target),
                'submit_format' => $this->hasFlagBit($flags, 3) ? 'html' : 'fdf',
                'include_no_value_fields' => $this->hasFlagBit($flags, 2),
                'default_excludes_no_export' => true,
            ];
        } else {
            $metadata['reset_to_default'] = true;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function javaScriptActionMetadataFromBody(
        string $actionBody,
        array $objects,
        string $trigger,
        string $source,
        int $sourceObject,
        ?int $actionObject
    ): array {
        $metadata = [
            'action_type' => 'JavaScript',
            'trigger' => $trigger,
            'trigger_label' => $this->actionTriggerLabel($trigger),
            'source' => $source,
            'source_object' => $sourceObject,
            'action_object' => $actionObject,
            'executes_action' => false,
            'executes_javascript' => false,
        ];

        $script = $this->javaScriptPayloadFromAction($actionBody, $objects, 160);
        if ($script === null) {
            $metadata['script_missing'] = true;
            return $metadata;
        }

        return $metadata + $script;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function javaScriptPayloadFromAction(string $actionBody, array $objects, int $previewBytes): ?array
    {
        $value = $this->valueAfterName($actionBody, 'JS');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $scriptObject = preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 ? (int) $match[1] : null;
        $filters = [];
        if ($scriptObject !== null && isset($objects[$scriptObject])) {
            $stream = $this->decodeStreamObject($objects[$scriptObject], $objects);
            if ($stream !== null) {
                $script = $this->decodePdfStringBytes($stream);
                $filters = $this->streamObjectFilters($objects[$scriptObject], $objects);
            } else {
                $script = $this->pdfValueToString(trim($objects[$scriptObject]), $objects);
            }
        } else {
            $script = $this->pdfValueToString($value, $objects);
        }

        if ($script === null) {
            return null;
        }

        $preview = $this->scriptPreview($script, $previewBytes);
        $payload = [
            'script_preview' => $preview['preview'],
            'script_sha256' => hash('sha256', $script),
            'script_bytes' => strlen($script),
            'script_truncated' => $preview['truncated'],
        ];

        if ($scriptObject !== null) {
            $payload['script_object'] = $scriptObject;
        }
        if ($filters !== []) {
            $payload['script_filters'] = $filters;
        }

        return $payload;
    }

    /**
     * @return array{preview: string, truncated: bool}
     */
    private function scriptPreview(string $script, int $previewBytes): array
    {
        $normalized = trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $script) ?? $script);
        $limit = max(16, $previewBytes);
        if (strlen($normalized) <= $limit) {
            return ['preview' => $normalized, 'truncated' => false];
        }

        return ['preview' => substr($normalized, 0, $limit) . '...', 'truncated' => true];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array{fields_mode: string, field_objects: list<int>, field_names: list<string>, unresolved_field_objects: list<int>}
     */
    private function fieldSelectionFromAction(
        string $actionBody,
        array $objects,
        array $fieldNamesByObject,
        string $actionType,
        int $flags
    ): array {
        $fields = $this->valueAfterName($actionBody, 'Fields');
        if ($fields === null || !str_starts_with(trim($fields), '[')) {
            return [
                'fields_mode' => $actionType === 'SubmitForm' ? 'all_exportable' : 'all',
                'field_objects' => [],
                'field_names' => [],
                'unresolved_field_objects' => [],
            ];
        }

        $arrayBody = $this->arrayBodyFromValue($fields);
        if ($arrayBody === null) {
            return [
                'fields_mode' => $this->hasFlagBit($flags, 1) ? 'exclude' : 'include',
                'field_objects' => [],
                'field_names' => [],
                'unresolved_field_objects' => [],
            ];
        }

        $fieldObjects = array_values(array_unique($this->objectReferences($arrayBody)));
        $fieldNames = [];
        $unresolved = [];
        foreach ($fieldObjects as $fieldObject) {
            if (isset($fieldNamesByObject[$fieldObject])) {
                $fieldNames[] = $fieldNamesByObject[$fieldObject];
                continue;
            }

            $unresolved[] = $fieldObject;
        }

        foreach ($this->scalarValuesFromArrayBody($arrayBody, $objects) as $fieldName) {
            if (!in_array($fieldName, $fieldNames, true)) {
                $fieldNames[] = $fieldName;
            }
        }

        return [
            'fields_mode' => $this->hasFlagBit($flags, 1) ? 'exclude' : 'include',
            'field_objects' => $fieldObjects,
            'field_names' => $fieldNames,
            'unresolved_field_objects' => $unresolved,
        ];
    }

    /**
     * @return list<string>
     */
    private function actionFlagNames(string $actionType, int $flags): array
    {
        $names = [];
        if ($this->hasFlagBit($flags, 1)) {
            $names[] = 'exclude_list';
        }

        if ($actionType === 'SubmitForm') {
            if ($this->hasFlagBit($flags, 2)) {
                $names[] = 'include_no_value_fields';
            }
            if ($this->hasFlagBit($flags, 3)) {
                $names[] = 'html_format';
            }
        }

        return $names;
    }

    private function actionTriggerLabel(string $trigger): string
    {
        return match ($trigger) {
            'activation' => 'activation',
            'E' => 'cursor_enter',
            'X' => 'cursor_exit',
            'D' => 'mouse_down',
            'U' => 'mouse_up',
            'Fo' => 'focus',
            'Bl' => 'blur',
            'K' => 'keystroke',
            'F' => 'format',
            'V' => 'validate',
            'C' => 'calculate',
            default => 'unknown',
        };
    }

    private function fileSpecificationFromValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $dictionary = $this->resolvedDictionaryFromValue($value, $objects);
        if ($dictionary !== null) {
            foreach (['UF', 'F', 'DOS', 'Mac', 'Unix'] as $name) {
                $file = $this->pdfStringValueAfterName($dictionary['body'], $name, $objects);
                if ($file !== null && $file !== '') {
                    return $file;
                }
            }

            return null;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            return $this->pdfValueToString(trim($objects[(int) $match[1]]), $objects);
        }

        return $this->pdfValueToString($value, $objects);
    }

    private function uriScheme(string $target): ?string
    {
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $target, $match) !== 1) {
            return null;
        }

        return strtolower($match[1]);
    }

    /**
     * @param array<string, mixed>|null $fieldDefaultAppearance
     * @return array<string, mixed>|null
     */
    private function widgetDefaultAppearance(string $widgetBody, ?array $fieldDefaultAppearance): ?array
    {
        $raw = $this->pdfStringValueAfterName($widgetBody, 'DA', []);
        if ($raw === null || $raw === '') {
            return $fieldDefaultAppearance;
        }

        $appearance = $this->parseDefaultAppearance($raw);
        $appearance['raw'] = $raw;
        $appearance['source'] = 'widget';
        $appearance['source_object'] = null;

        return $appearance;
    }

    /**
     * @return list<array{export: string, label: string}>
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function optionsFromEffective(array $effective, array $objects): array
    {
        if (!isset($effective['Opt'])) {
            return [];
        }

        $value = trim($effective['Opt']['value']);
        if (!str_starts_with($value, '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $options = [];
        $offset = 0;
        while ($offset < strlen($body)) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= strlen($body)) {
                break;
            }

            if ($body[$offset] === '[') {
                $endOffset = null;
                $nested = $this->readPdfArrayAt($body, $offset, $endOffset);
                if ($nested === null || $endOffset === null) {
                    break;
                }
                $parts = $this->scalarValuesFromArrayBody($nested, $objects);
                if (count($parts) >= 2) {
                    $options[] = ['export' => $parts[0], 'label' => $parts[1]];
                }
                $offset = $endOffset;
                continue;
            }

            $item = $this->readScalarAt($body, $offset, $objects);
            if ($item !== null) {
                $options[] = ['export' => $item['value'], 'label' => $item['value']];
                $offset = $item['end'];
                continue;
            }

            $offset++;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function scalarValuesFromArrayBody(string $body, array $objects): array
    {
        $values = [];
        $offset = 0;
        while ($offset < strlen($body)) {
            $this->skipWhitespace($body, $offset);
            $item = $this->readScalarAt($body, $offset, $objects);
            if ($item === null) {
                $offset++;
                continue;
            }
            $values[] = $item['value'];
            $offset = $item['end'];
        }

        return $values;
    }

    /**
     * @return array{value: string, end: int}|null
     */
    private function readScalarAt(string $body, int $offset, array $objects): ?array
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '(') {
            $end = $this->skipLiteralString($body, $offset);
            $value = $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $offset + 1, $end - $offset - 2)));
            return ['value' => $value, 'end' => $end];
        }

        if ($body[$offset] === '<' && substr($body, $offset, 2) !== '<<') {
            $end = $this->skipHexString($body, $offset);
            $hex = preg_replace('/\s+/', '', substr($body, $offset + 1, $end - $offset - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            if ($bytes === false) {
                return null;
            }

            return ['value' => $this->decodePdfStringBytes($bytes), 'end' => $end];
        }

        if ($body[$offset] === '/') {
            $end = $this->skipPdfName($body, $offset);
            return ['value' => $this->decodePdfName(substr($body, $offset, $end - $offset)), 'end' => $end];
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
            $ref = (int) $match[1];
            if (!isset($objects[$ref])) {
                return null;
            }
            $resolved = $this->pdfValueToString(trim($objects[$ref]), $objects);
            return $resolved === null ? null : ['value' => $resolved, 'end' => $offset + strlen($match[0])];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function normalAppearanceStates(string $widgetBody): array
    {
        $ap = $this->valueAfterName($widgetBody, 'AP');
        if ($ap === null || !str_starts_with(trim($ap), '<<')) {
            return [];
        }

        $normal = $this->valueAfterName($ap, 'N');
        if ($normal === null || !str_starts_with(trim($normal), '<<')) {
            return [];
        }

        $states = [];
        if (preg_match_all('/\/((?:#[0-9A-Fa-f]{2}|[^\s\[\]\(\)<>{}\/%])+)\b/', $normal, $matches)) {
            foreach ($matches[1] as $name) {
                $decoded = $this->decodePdfName('/' . $name);
                if (!in_array($decoded, $states, true)) {
                    $states[] = $decoded;
                }
            }
        }

        return $states;
    }

    /**
     * @return list<int>
     */
    private function fieldReferencesFromAcroForm(string $acroForm): array
    {
        $fields = $this->valueAfterName($acroForm, 'Fields');
        if ($fields === null || !str_starts_with(trim($fields), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($fields);
        return $body === null ? [] : $this->objectReferences($body);
    }

    /**
     * @return list<int>
     */
    private function kidReferences(string $body): array
    {
        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($kids);
        return $body === null ? [] : $this->objectReferences($body);
    }

    private function isPureWidget(string $body): bool
    {
        if (!$this->isWidget($body)) {
            return false;
        }

        return $this->valueAfterName($body, 'T') === null
            && $this->valueAfterName($body, 'TM') === null
            && $this->valueAfterName($body, 'FT') === null
            && $this->valueAfterName($body, 'Kids') === null;
    }

    private function isWidget(string $body): bool
    {
        return preg_match('/\/Subtype\s*\/Widget\b/', $body) === 1;
    }

    /**
     * @return array<int, array{page_index: int, page_object: int}>
     * @param array<int, string> $objects
     * @param list<int> $pageObjectNumbers
     */
    private function pageWidgetMap(array $objects, array $pageObjectNumbers): array
    {
        $widgets = [];
        foreach ($pageObjectNumbers as $pageIndex => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $annots = $this->valueAfterName($objects[$pageObjectNumber], 'Annots');
            if ($annots === null) {
                continue;
            }

            $annotationRefs = $this->annotationObjectReferences($annots, $objects);
            foreach ($annotationRefs as $annotationRef) {
                $annotationBody = $this->dictionaryObjectBody($objects[$annotationRef] ?? '') ?? '';
                if ($annotationBody === '' || !$this->isWidget($annotationBody)) {
                    continue;
                }

                $widgets[$annotationRef] = [
                    'page_index' => $pageIndex,
                    'page_object' => $pageObjectNumber,
                ];
            }
        }

        return $widgets;
    }

    /**
     * @return list<int>
     */
    private function annotationObjectReferences(string $annots, array $objects): array
    {
        $annots = trim($annots);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $annots, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $objectBody = trim($objects[$objectNumber]);
            if (str_starts_with($objectBody, '[')) {
                $arrayBody = $this->arrayBodyFromValue($objectBody);
                return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
            }

            return [$objectNumber];
        }

        if (!str_starts_with($annots, '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($annots);
        return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
    }

    private function acroFormDictionaryBody(string $catalog, array $objects): ?string
    {
        $value = $this->valueAfterName($catalog, 'AcroForm');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            return $this->dictionaryObjectBody($objects[(int) $match[1]] ?? '');
        }

        return null;
    }

    /**
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 4) {
            return null;
        }

        return $this->normalizeRect(array_slice($numbers, 0, 4));
    }

    /**
     * @param list<float> $rect
     * @return list<float>
     */
    private function normalizeRect(array $rect): array
    {
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    private function pdfValueToPhpValue(string $value, array $objects): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            $body = $this->arrayBodyFromValue($value);
            return $body === null ? [] : $this->scalarValuesFromArrayBody($body, $objects);
        }

        return $this->pdfValueToString($value, $objects);
    }

    private function pdfValueToString(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return $this->decodePdfStringBytes($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if ($value[0] === '/') {
            return $this->decodePdfName($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            return $this->pdfValueToString(trim($objects[(int) $match[1]]), $objects);
        }

        if ($value === 'null') {
            return null;
        }

        if ($value === 'true' || $value === 'false' || is_numeric($value)) {
            return $value;
        }

        return null;
    }

    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->pdfValueToString($value, $objects);
    }

    /**
     * @return list<int>|null
     */
    private function integerArrayValueAfterName(string $body, string $name): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null || preg_match_all('/[+-]?\d+/', $arrayBody, $matches) === false) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    private function pdfNameValueAfterName(string $body, string $name): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    private function numberValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^[+-]?\d+/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function boolValueAfterName(string $body, string $name): ?bool
    {
        $value = $this->valueAfterName($body, $name);
        return match (trim((string) $value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            return $ref[0];
        }

        if ($body[$offset] === '[') {
            $endOffset = null;
            $this->readPdfArrayAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $endOffset = $this->skipPdfName($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return substr($body, $offset, max(0, $end - $offset));
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function resolvedDictionaryFromValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<<')) {
            $body = $this->readPdfDictionaryAt($value, 0);
            return $body === null ? null : ['body' => $body, 'object' => null];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        $body = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
        return $body === null ? null : ['body' => $body, 'object' => $objectNumber];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function dictionaryValuesFromArrayBody(string $body, array $objects): array
    {
        $dictionaries = [];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if (substr($body, $offset, 2) === '<<') {
                $endOffset = null;
                $dictionaryBody = $this->readPdfDictionaryAt($body, $offset, $endOffset);
                if ($dictionaryBody === null || $endOffset === null) {
                    $offset++;
                    continue;
                }

                $dictionaries[] = ['body' => $dictionaryBody, 'object' => null];
                $offset = $endOffset;
                continue;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                $dictionaryBody = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
                if ($dictionaryBody !== null) {
                    $dictionaries[] = ['body' => $dictionaryBody, 'object' => $objectNumber];
                }
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $dictionaries;
    }

    private function signatureContentsLength(string $value, array $objects): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            return intdiv(strlen($hex) + 1, 2);
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return strlen($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            return $this->signatureContentsLength(trim($objects[(int) $match[1]]), $objects);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?string
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        return $this->decodeStream($match[1], $match[2], $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStream(string $dict, string $stream, array $objects): ?string
    {
        foreach ($this->streamFilters($dict, $objects) as $filter) {
            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream),
                default => $stream,
            };
            if ($decoded === null) {
                return null;
            }
            $stream = $decoded;
        }

        return $stream;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamObjectFilters(string $objectBody, array $objects): array
    {
        if (!preg_match('/<<(.*?)>>\s*stream/s', $objectBody, $match)) {
            return [];
        }

        return $this->streamFilters($match[1], $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function streamFilters(string $dict, array $objects): array
    {
        $filter = $this->valueAfterName($dict, 'Filter');
        if ($filter === null) {
            return [];
        }

        return $this->filterNamesFromValue($filter, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function filterNamesFromValue(string $value, array $objects): array
    {
        preg_match_all('/\/([^\s\[\]()<>{}\/%]+)|(\d+)\s+\d+\s+R\b/', $value, $matches, PREG_SET_ORDER);
        $filters = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $filters[] = $this->decodePdfName($match[1]);
                continue;
            }

            $objectNumber = isset($match[2]) ? (int) $match[2] : 0;
            if ($objectNumber > 0 && isset($objects[$objectNumber])) {
                foreach ($this->filterNamesFromValue($objects[$objectNumber], $objects) as $filter) {
                    $filters[] = $filter;
                }
            }
        }

        return $filters;
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $body = strstr($stream, '>', true);
        if ($body === false) {
            $body = $stream;
        }

        $hex = preg_replace('/\s+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);
        return $decoded === false ? null : $decoded;
    }

    private function decodeFlateStream(string $stream): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        return $inflated === false ? null : $inflated;
    }

    private function skipWhitespace(string $body, int &$offset): void
    {
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }
    }

    private function skipPdfName(string $body, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(array $objects): ?string
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                return $this->dictionaryObjectBody($body);
            }
        }

        return null;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1 || preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match) !== 1) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [$objectNumber];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferences($arrayBody) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $value, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    private function arrayBodyFromValue(string $value): ?string
    {
        $offset = strpos($value, '[');
        return $offset === false ? null : $this->readPdfArrayAt($value, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($value, $index, $endDictionary);
                if ($endDictionary !== null) {
                    $index = $endDictionary - 1;
                    continue;
                }
            }
            if ($char === '<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }
            if ($char === '[') {
                $depth++;
                continue;
            }
            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 1;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipLiteralString(string $value, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($value);
    }

    private function skipHexString(string $value, int $offset): int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? strlen($value) : $end + 1;
    }

    /**
     * @return list<float>
     */
    private function numbersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $matches)) {
            return [];
        }

        return array_map('floatval', $matches[0]);
    }

    private function decodePdfName(string $name): string
    {
        $name = ltrim($name, '/');

        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
            };
        }, $value) ?? $value;
    }
}
