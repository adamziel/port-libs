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

    private const SIGNATURE_SEED_REQUIRED_FLAGS = [
        1 => 'filter',
        2 => 'subfilter',
        4 => 'seed_value_parser_version',
        8 => 'reason',
        16 => 'legal_attestation',
        32 => 'add_revision_info',
        64 => 'digest_method',
    ];

    private const ANNOTATION_FLAGS = [
        1 => 'invisible',
        2 => 'hidden',
        3 => 'print',
        4 => 'no_zoom',
        5 => 'no_rotate',
        6 => 'no_view',
        7 => 'read_only',
        8 => 'locked',
        9 => 'toggle_no_view',
        10 => 'locked_contents',
    ];

    /**
     * Native boundary for PDF AcroForm field dictionaries.
     *
     * @return array{need_appearances: bool, default_resources: array<string, mixed>, permissions: array<string, mixed>, signature_flags: array<string, mixed>, xfa_overrides_page_content: bool, xfa_packets: list<array<string, mixed>>, calculation_order: list<array{object: int, field_name: string|null}>, fields: list<array<string, mixed>>}
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
                'default_resources' => $this->emptyDefaultResources(),
                'permissions' => $permissions,
                'signature_flags' => $this->emptySignatureFlags(),
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
        $defaultResources = $this->defaultResourcesFromEffective($formDefaults, $objects);
        $xfaPackets = $this->xfaPacketsFromAcroForm($acroForm, $objects);
        $fields = [];
        $fieldRefs = $this->fieldReferencesFromAcroForm($acroForm);
        $fieldNamesByObject = $this->fieldNamesByObject($fieldRefs, $objects);
        $calculationOrder = $this->calculationOrderFromAcroForm($acroForm, $fieldNamesByObject);
        $signatureFlags = $this->acroFormSignatureFlags($acroForm);

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
        $fields = $this->annotateCalculationAndSignatureState($fields, $calculationOrder, $signatureFlags);

        return [
            'need_appearances' => $this->boolValueAfterName($acroForm, 'NeedAppearances') === true,
            'default_resources' => $defaultResources,
            'permissions' => $permissions,
            'signature_flags' => $signatureFlags,
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
     * @return array<string, mixed>
     */
    private function emptySignatureFlags(): array
    {
        return [
            'source' => null,
            'flags' => 0,
            'flag_names' => [],
            'signatures_exist' => false,
            'append_only' => false,
            'append_only_required' => false,
            'executes_signature_validation' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function acroFormSignatureFlags(string $acroForm): array
    {
        $rawFlags = $this->valueAfterName($acroForm, 'SigFlags');
        $flags = $this->numberValueAfterName($acroForm, 'SigFlags') ?? 0;

        return [
            'source' => $rawFlags === null ? null : 'acroform_sigflags',
            'flags' => $flags,
            'flag_names' => $this->signatureFlagNames($flags),
            'signatures_exist' => ($flags & 1) !== 0,
            'append_only' => ($flags & 2) !== 0,
            'append_only_required' => ($flags & 2) !== 0,
            'executes_signature_validation' => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function signatureFlagNames(int $flags): array
    {
        $names = [];
        if (($flags & 1) !== 0) {
            $names[] = 'signatures_exist';
        }
        if (($flags & 2) !== 0) {
            $names[] = 'append_only';
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param list<array{object: int, field_name: string|null}> $calculationOrder
     * @param array<string, mixed> $signatureFlags
     * @return list<array<string, mixed>>
     */
    private function annotateCalculationAndSignatureState(array $fields, array $calculationOrder, array $signatureFlags): array
    {
        $calculationIndexesByObject = [];
        $calculationIndexesByName = [];
        foreach ($calculationOrder as $index => $entry) {
            $calculationIndexesByObject[(int) $entry['object']] = $index;
            if (is_string($entry['field_name']) && $entry['field_name'] !== '') {
                $calculationIndexesByName[$entry['field_name']] = $index;
            }
        }

        foreach ($fields as $index => $field) {
            $fields[$index]['calculation_state'] = $this->fieldCalculationState($field, $calculationOrder, $calculationIndexesByObject, $calculationIndexesByName);
            if (($field['field_type'] ?? null) === 'Sig') {
                $fields[$index]['signature_state'] = $this->fieldSignatureState($field, $signatureFlags);
            }
        }

        $signedLocks = $this->signedSignatureLockEntries($fields);
        foreach ($fields as $index => $field) {
            $fields[$index]['signature_lock_state'] = $this->fieldSignatureLockState($field, $signedLocks);
        }

        return $fields;
    }

    /**
     * @param list<array{object: int, field_name: string|null}> $calculationOrder
     * @param array<int, int> $calculationIndexesByObject
     * @param array<string, int> $calculationIndexesByName
     * @return array<string, mixed>
     */
    private function fieldCalculationState(
        array $field,
        array $calculationOrder,
        array $calculationIndexesByObject,
        array $calculationIndexesByName
    ): array {
        $objectNumber = $field['object'] ?? null;
        $name = $field['name'] ?? null;
        $orderIndex = is_int($objectNumber) && array_key_exists($objectNumber, $calculationIndexesByObject)
            ? $calculationIndexesByObject[$objectNumber]
            : (is_string($name) && array_key_exists($name, $calculationIndexesByName) ? $calculationIndexesByName[$name] : null);
        $orderEntry = $orderIndex === null ? null : ($calculationOrder[$orderIndex] ?? null);
        $calculateActions = $this->calculateActionSources($field);

        return [
            'source' => 'acroform_calculation_state_boundary',
            'in_calculation_order' => $orderIndex !== null,
            'calculation_order_index' => $orderIndex,
            'calculation_order_object' => is_array($orderEntry) ? $orderEntry['object'] : null,
            'calculation_order_field_name' => is_array($orderEntry) ? $orderEntry['field_name'] : null,
            'has_calculate_action' => $calculateActions !== [],
            'calculate_actions' => $calculateActions,
            'value_is_static_review' => true,
            'executes_javascript' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function calculateActionSources(array $field): array
    {
        $actions = [];
        foreach ($field['actions'] ?? [] as $action) {
            if (is_array($action) && ($action['trigger'] ?? null) === 'C') {
                $actions[] = $this->calculateActionSource($action);
            }
        }

        foreach ($field['widgets'] ?? [] as $widget) {
            if (!is_array($widget)) {
                continue;
            }

            foreach ($widget['actions'] ?? [] as $action) {
                if (is_array($action) && ($action['trigger'] ?? null) === 'C') {
                    $actions[] = $this->calculateActionSource($action);
                }
            }
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateActionSource(array $action): array
    {
        $source = [
            'source' => $action['source'] ?? null,
            'source_object' => $action['source_object'] ?? null,
            'action_object' => $action['action_object'] ?? null,
            'script_sha256' => $action['script_sha256'] ?? null,
            'script_bytes' => $action['script_bytes'] ?? null,
            'executes_javascript' => false,
            'executes_action' => false,
        ];

        if (isset($action['script_object'])) {
            $source['script_object'] = $action['script_object'];
        }
        if (isset($action['script_filters'])) {
            $source['script_filters'] = $action['script_filters'];
        }

        return $source;
    }

    /**
     * @param array<string, mixed> $signatureFlags
     * @return array<string, mixed>
     */
    private function fieldSignatureState(array $field, array $signatureFlags): array
    {
        $signature = $field['signature'] ?? null;
        $hasSignature = is_array($signature);
        $byteRange = $hasSignature ? ($signature['byte_range'] ?? null) : null;

        return [
            'source' => 'acroform_signature_state_boundary',
            'signatures_exist_hint' => (bool) ($signatureFlags['signatures_exist'] ?? false),
            'append_only' => (bool) ($signatureFlags['append_only'] ?? false),
            'append_only_required' => (bool) ($signatureFlags['append_only_required'] ?? false),
            'has_signature_dictionary' => $hasSignature,
            'signed' => $hasSignature && $this->signatureMetadataIndicatesSigned($signature),
            'signature_object' => $hasSignature ? ($signature['object'] ?? null) : null,
            'signed_at' => $hasSignature ? ($signature['signed_at'] ?? null) : null,
            'byte_range' => is_array($byteRange) ? $byteRange : null,
            'byte_range_segment_count' => is_array($byteRange) ? intdiv(count($byteRange), 2) : 0,
            'contents_present' => $hasSignature && ($signature['contents_present'] ?? false) === true,
            'contents_length_bytes' => $hasSignature ? ($signature['contents_length_bytes'] ?? null) : null,
            'certifying_signature' => (bool) ($field['certifying_signature'] ?? false),
            'value_state_source' => 'signature_dictionary_not_field_value',
            'executes_signature_validation' => false,
            'executes_signing' => false,
            'executes_action' => false,
        ];
    }

    private function signatureMetadataIndicatesSigned(?array $signature): bool
    {
        if ($signature === null) {
            return false;
        }

        return ($signature['contents_present'] ?? false) === true
            && ($signature['contents_length_bytes'] ?? 0) > 0
            && is_array($signature['byte_range'] ?? null)
            && count($signature['byte_range']) >= 4;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function signedSignatureLockEntries(array $fields): array
    {
        $locks = [];
        foreach ($fields as $field) {
            if (($field['field_type'] ?? null) !== 'Sig') {
                continue;
            }

            $signature = $field['signature'] ?? null;
            $lock = $field['signature_lock'] ?? null;
            if (!is_array($signature) || !is_array($lock) || !$this->signatureMetadataIndicatesSigned($signature)) {
                continue;
            }

            $locks[] = [
                'signature_field' => $field['name'] ?? null,
                'signature_field_object' => $field['object'] ?? null,
                'signature_object' => $signature['object'] ?? null,
                'action' => $lock['action'] ?? null,
                'action_label' => $lock['action_label'] ?? 'unknown',
                'field_names' => $lock['field_names'] ?? [],
                'permission_level' => $lock['permission_level'] ?? null,
                'permission_label' => $lock['permission_label'] ?? null,
                'allowed_changes' => $lock['allowed_changes'] ?? [],
            ];
        }

        return $locks;
    }

    /**
     * @param list<array<string, mixed>> $signedLocks
     * @return array<string, mixed>
     */
    private function fieldSignatureLockState(array $field, array $signedLocks): array
    {
        $fieldName = $field['name'] ?? null;
        $lockedBy = [];
        foreach ($signedLocks as $lock) {
            if (!is_string($fieldName) || !$this->signatureLockAppliesToField($lock, $fieldName)) {
                continue;
            }

            $lockedBy[] = [
                'signature_field' => $lock['signature_field'],
                'signature_object' => $lock['signature_object'],
                'action' => $lock['action'],
                'action_label' => $lock['action_label'],
                'permission_level' => $lock['permission_level'],
                'permission_label' => $lock['permission_label'],
            ];
        }

        return [
            'source' => 'acroform_signature_lock_state_boundary',
            'lock_state_source' => $lockedBy === [] ? null : 'signed_signature_lock',
            'effective_locked' => $lockedBy !== [],
            'locked_by_signature_count' => count($lockedBy),
            'locked_by_signatures' => array_values(array_filter(array_map(
                static fn (array $lock): ?string => is_string($lock['signature_field'] ?? null) ? $lock['signature_field'] : null,
                $lockedBy
            ))),
            'locks' => $lockedBy,
            'permission_labels' => $this->uniqueScalarValues(array_map(
                static fn (array $lock): mixed => $lock['permission_label'] ?? null,
                $lockedBy
            )),
            'executes_action' => false,
        ];
    }

    /**
     * @param array<string, mixed> $lock
     */
    private function signatureLockAppliesToField(array $lock, string $fieldName): bool
    {
        $action = $lock['action'] ?? null;
        $fieldNames = array_values(array_filter(
            $lock['field_names'] ?? [],
            static fn (mixed $name): bool => is_string($name) && $name !== ''
        ));

        return match ($action) {
            'All' => true,
            'Include' => in_array($fieldName, $fieldNames, true),
            'Exclude' => !in_array($fieldName, $fieldNames, true),
            default => false,
        };
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    private function uniqueScalarValues(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            if (!is_scalar($value) || in_array($value, $unique, true)) {
                continue;
            }

            $unique[] = $value;
        }

        return $unique;
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

        $normalized = $this->normalizeXfaXmlPayload($payload['xml']);
        $xml = trim($normalized['xml']);
        if ($xml === '') {
            return null;
        }

        $root = $this->xmlRootName($xml);
        $name = $packetName !== null && $packetName !== '' ? $packetName : ($root ?? 'xfa');
        $xdpPacketNames = $this->xdpPacketNames($xml);

        return [
            'index' => $index,
            'name' => $name,
            'object' => $payload['object'],
            'source' => $source,
            'filters' => $payload['filters'],
            'xml_root' => $root,
            'xml_encoding' => $normalized['encoding'],
            'decoded_to_utf8' => $normalized['decoded_to_utf8'],
            'length_bytes' => strlen($xml),
            'xml_sha256' => hash('sha256', $xml),
            'is_xdp_package' => $this->xmlLocalName($root) === 'xdp',
            'xdp_packet_names' => $xdpPacketNames,
            'field_names' => $this->xfaFieldNames($xml),
            'data_node_names' => $this->xfaDataNodeNames($xml),
            'has_template' => $this->xfaPayloadHasRole($name, $root, $xdpPacketNames, $xml, 'template'),
            'has_datasets' => $this->xfaPayloadHasRole($name, $root, $xdpPacketNames, $xml, 'datasets'),
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
     * @return array{xml: string, encoding: string, decoded_to_utf8: bool}
     */
    private function normalizeXfaXmlPayload(string $xml): array
    {
        if (str_starts_with($xml, "\xEF\xBB\xBF")) {
            return [
                'xml' => substr($xml, 3),
                'encoding' => 'UTF-8-BOM',
                'decoded_to_utf8' => false,
            ];
        }

        if (str_starts_with($xml, "\xFE\xFF")) {
            $decoded = function_exists('iconv') ? @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($xml, 2)) : false;
            if (is_string($decoded)) {
                return [
                    'xml' => $decoded,
                    'encoding' => 'UTF-16BE',
                    'decoded_to_utf8' => true,
                ];
            }
        }

        if (str_starts_with($xml, "\xFF\xFE")) {
            $decoded = function_exists('iconv') ? @iconv('UTF-16LE', 'UTF-8//IGNORE', substr($xml, 2)) : false;
            if (is_string($decoded)) {
                return [
                    'xml' => $decoded,
                    'encoding' => 'UTF-16LE',
                    'decoded_to_utf8' => true,
                ];
            }
        }

        return [
            'xml' => $xml,
            'encoding' => 'UTF-8',
            'decoded_to_utf8' => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function xdpPacketNames(string $xml): array
    {
        if ($this->xmlLocalName($this->xmlRootName($xml)) !== 'xdp') {
            return [];
        }

        if (preg_match('/<\s*([A-Za-z_][A-Za-z0-9_.:-]*)\b[^>]*>/s', $xml, $rootMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $body = substr($xml, $rootMatch[0][1] + strlen($rootMatch[0][0]));
        $names = [];
        $seen = [];
        $depth = 0;
        $offset = 0;
        while (preg_match('/<\s*(\/?)([A-Za-z_][A-Za-z0-9_.:-]*)\b[^>]*(\/?)>/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $closing = $match[1][0] === '/';
            $tagName = $match[2][0];
            $localName = $this->xmlLocalName($tagName);
            if ($closing) {
                if ($depth === 0 && $localName === 'xdp') {
                    break;
                }

                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth === 0 && $localName !== null && $localName !== 'xdp' && !isset($seen[$localName])) {
                $seen[$localName] = true;
                $names[] = $localName;
            }

            if (!str_ends_with(rtrim($match[0][0]), '/>')) {
                $depth++;
            }
        }

        return $names;
    }

    /**
     * @param list<string> $xdpPacketNames
     */
    private function xfaPayloadHasRole(string $name, ?string $root, array $xdpPacketNames, string $xml, string $role): bool
    {
        return $this->xmlLocalName($name) === $role
            || $this->xmlLocalName($root) === $role
            || in_array($role, $xdpPacketNames, true)
            || $this->xmlContainsElement($xml, $role);
    }

    private function xmlContainsElement(string $xml, string $localName): bool
    {
        return preg_match('/<\s*(?:[A-Za-z_][A-Za-z0-9_.-]*:)?' . preg_quote($localName, '/') . '\b/si', $xml) === 1;
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
     * @param array<int, array{page_index: int, page_object: int, annotation_index: int}> $pageWidgets
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
        $defaultAppearance = $this->defaultAppearanceFromEffective($effective, $objects);
        $password = $fieldType === 'Tx' && $this->hasFlagBit($flags, 14);

        $name = $currentNameParts === [] ? '#' . $objectNumber : implode('.', $currentNameParts);
        $value = $password ? null : $this->valueFromEffective($effective, 'V', $objects);
        $defaultValue = $password ? null : $this->valueFromEffective($effective, 'DV', $objects);
        $options = $fieldType === 'Ch' ? $this->optionsFromEffective($effective, $objects) : [];
        $widgets = $this->widgetsForField($widgetRefs, $objects, $defaultAppearance, $effective, $pageIndexes, $pageWidgets, $fieldNamesByObject);
        $widgets = $this->widgetsWithCurrentValueState($widgets, $fieldType, $flags, $value);

        $field = [
            'object' => $objectNumber,
            'name' => $name,
            'partial_name' => $partialName,
            'mapping_name' => $mappingName ?? $name,
            'field_type' => $fieldType,
            'field_type_label' => $this->fieldTypeLabel($fieldType),
            'flags' => $flags,
            'flag_names' => $this->flagNames($flags, $fieldType),
            'value' => $value,
            'value_redacted' => $password,
            'default_value' => $defaultValue,
            'value_state' => $this->fieldValueState($fieldType, $flags, $effective, $password, $value, $defaultValue, $options, $widgets),
            'default_appearance' => $defaultAppearance,
            'actions' => $this->actionsFromDictionary($body, $objects, $fieldNamesByObject, 'field', $objectNumber),
            'widgets' => $widgets,
        ];

        if ($fieldType === 'Ch') {
            $field['options'] = $options;
        }
        if ($fieldType === 'Sig') {
            $field['signature'] = isset($effective['V'])
                ? $this->signatureMetadataFromValue($effective['V']['value'], $objects)
                : null;
            $field['signature_seed_value'] = $this->signatureSeedValueFromField($body, $objects);
            $field['signature_lock'] = $this->signatureLockFromField($body, $objects, $fieldNamesByObject);
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
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureSeedValueFromField(string $fieldBody, array $objects): ?array
    {
        $seedValue = $this->valueAfterName($fieldBody, 'SV');
        $seed = $seedValue === null ? null : $this->resolvedDictionaryFromValue($seedValue, $objects);
        if ($seed === null) {
            return null;
        }

        $body = $seed['body'];
        $flags = $this->numberValueAfterName($body, 'Ff') ?? 0;

        return [
            'object' => $seed['object'],
            'type' => $this->pdfNameValueAfterName($body, 'Type'),
            'source' => 'signature_field_seed_value_dictionary',
            'flags' => $flags,
            'required_constraints' => $this->signatureSeedRequiredConstraints($flags),
            'filter' => $this->pdfNameValueAfterName($body, 'Filter'),
            'filter_required' => $this->signatureSeedConstraintRequired($flags, 1),
            'subfilters' => $this->scalarListValueAfterName($body, 'SubFilter', $objects),
            'subfilter_required' => $this->signatureSeedConstraintRequired($flags, 2),
            'parser_version' => $this->realValueAfterName($body, 'V'),
            'parser_version_required' => $this->signatureSeedConstraintRequired($flags, 4),
            'reasons' => $this->scalarListValueAfterName($body, 'Reasons', $objects),
            'reason_required' => $this->signatureSeedConstraintRequired($flags, 8),
            'legal_attestations' => $this->scalarListValueAfterName($body, 'LegalAttestation', $objects),
            'legal_attestation_required' => $this->signatureSeedConstraintRequired($flags, 16),
            'add_revision_info' => $this->boolValueAfterName($body, 'AddRevInfo'),
            'add_revision_info_required' => $this->signatureSeedConstraintRequired($flags, 32),
            'digest_methods' => $this->scalarListValueAfterName($body, 'DigestMethod', $objects),
            'digest_method_required' => $this->signatureSeedConstraintRequired($flags, 64),
            'mdp' => $this->signatureSeedMdp($body, $objects),
            'timestamp' => $this->signatureSeedTimestamp($body, $objects),
            'executes_signing' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @return list<string>
     */
    private function signatureSeedRequiredConstraints(int $flags): array
    {
        $required = [];
        foreach (self::SIGNATURE_SEED_REQUIRED_FLAGS as $bit => $name) {
            if ($this->signatureSeedConstraintRequired($flags, $bit)) {
                $required[] = $name;
            }
        }

        return $required;
    }

    private function signatureSeedConstraintRequired(int $flags, int $bit): bool
    {
        return ($flags & $bit) !== 0;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureSeedMdp(string $seedBody, array $objects): ?array
    {
        $value = $this->valueAfterName($seedBody, 'MDP');
        $mdp = $value === null ? null : $this->resolvedDictionaryFromValue($value, $objects);
        if ($mdp === null) {
            return null;
        }

        $level = $this->numberValueAfterName($mdp['body'], 'P');

        return [
            'object' => $mdp['object'],
            'permission_level' => $level,
            'permission_valid' => in_array($level, [0, 1, 2, 3], true),
            'signature_type' => match ($level) {
                0 => 'ordinary_signature',
                1, 2, 3 => 'certifying_signature',
                default => 'unknown',
            },
            'permission_label' => $level === 0 ? 'ordinary_signature' : $this->docMdpPermissionLabel($level),
            'allowed_changes' => in_array($level, [1, 2, 3], true) ? $this->docMdpAllowedChanges($level) : [],
            'source' => 'signature_seed_value_mdp',
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureSeedTimestamp(string $seedBody, array $objects): ?array
    {
        $value = $this->valueAfterName($seedBody, 'TimeStamp');
        $timestamp = $value === null ? null : $this->resolvedDictionaryFromValue($value, $objects);
        if ($timestamp === null) {
            return null;
        }

        $flags = $this->numberValueAfterName($timestamp['body'], 'Ff') ?? 0;

        return [
            'object' => $timestamp['object'],
            'url' => $this->pdfStringValueAfterName($timestamp['body'], 'URL', $objects),
            'flags' => $flags,
            'required' => ($flags & 1) !== 0,
            'source' => 'signature_seed_value_timestamp',
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return array<string, mixed>|null
     */
    private function signatureLockFromField(string $fieldBody, array $objects, array $fieldNamesByObject): ?array
    {
        $lockValue = $this->valueAfterName($fieldBody, 'Lock');
        $lock = $lockValue === null ? null : $this->resolvedDictionaryFromValue($lockValue, $objects);
        if ($lock === null) {
            return null;
        }

        $body = $lock['body'];
        $action = $this->pdfNameValueAfterName($body, 'Action');
        $fields = $this->signatureLockFieldNames($body, $objects, $fieldNamesByObject);
        $permissionLevel = $this->numberValueAfterName($body, 'P');

        return [
            'object' => $lock['object'],
            'type' => $this->pdfNameValueAfterName($body, 'Type'),
            'source' => 'signature_field_lock_dictionary',
            'action' => $action,
            'action_valid' => in_array($action, ['All', 'Include', 'Exclude'], true),
            'action_label' => $this->signatureLockActionLabel($action),
            'field_names' => $fields,
            'field_count' => count($fields),
            'locks_all_fields' => $action === 'All',
            'included_fields' => $action === 'Include' ? $fields : [],
            'excluded_fields' => $action === 'Exclude' ? $fields : [],
            'permission_level' => $permissionLevel,
            'permission_valid' => $permissionLevel === null || in_array($permissionLevel, [1, 2, 3], true),
            'permission_label' => $permissionLevel === null ? null : $this->docMdpPermissionLabel($permissionLevel),
            'allowed_changes' => $permissionLevel === null ? [] : $this->docMdpAllowedChanges($permissionLevel),
            'executes_action' => false,
        ];
    }

    private function signatureLockActionLabel(?string $action): string
    {
        return match ($action) {
            'All' => 'lock_all_fields',
            'Include' => 'lock_included_fields',
            'Exclude' => 'lock_all_except_excluded_fields',
            default => 'unknown',
        };
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, string> $fieldNamesByObject
     * @return list<string>
     */
    private function signatureLockFieldNames(string $lockBody, array $objects, array $fieldNamesByObject): array
    {
        $value = $this->valueAfterName($lockBody, 'Fields');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $names = [];
        $offset = 0;
        while ($offset < strlen($body)) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= strlen($body)) {
                break;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                if (isset($fieldNamesByObject[$objectNumber]) && !in_array($fieldNamesByObject[$objectNumber], $names, true)) {
                    $names[] = $fieldNamesByObject[$objectNumber];
                }
                $offset += strlen($match[0]);
                continue;
            }

            $scalar = $this->readScalarAt($body, $offset, $objects);
            if ($scalar !== null) {
                if ($scalar['value'] !== '' && !in_array($scalar['value'], $names, true)) {
                    $names[] = $scalar['value'];
                }
                $offset = $scalar['end'];
                continue;
            }

            $offset++;
        }

        return $names;
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
        foreach (['FT', 'Ff', 'V', 'DV', 'DA', 'DR', 'Q', 'Opt', 'I'] as $name) {
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
        foreach (['DA', 'DR', 'Q'] as $name) {
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
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param list<array{export: string, label: string}> $options
     * @param list<array<string, mixed>> $widgets
     * @return array<string, mixed>
     */
    private function fieldValueState(
        ?string $fieldType,
        int $flags,
        array $effective,
        bool $password,
        mixed $value,
        mixed $defaultValue,
        array $options,
        array $widgets
    ): array {
        $hasCurrent = isset($effective['V']);
        $hasDefault = isset($effective['DV']);
        $state = [
            'source' => 'acroform_current_value_state',
            'field_type' => $fieldType,
            'value_redacted' => $password,
            'has_current_value' => $hasCurrent,
            'has_default_value' => $hasDefault,
            'current' => $password ? null : $value,
            'default' => $password ? null : $defaultValue,
            'display_value' => $password ? '[redacted]' : $this->displayValue($value),
            'current_source' => $effective['V']['source'] ?? null,
            'current_source_object' => $effective['V']['source_object'] ?? null,
            'default_source' => $effective['DV']['source'] ?? null,
            'default_source_object' => $effective['DV']['source_object'] ?? null,
            'changed_from_default' => $password || !$hasDefault ? null : !$this->valuesMatch($value, $defaultValue),
        ];

        if ($fieldType === 'Ch') {
            $explicitIndices = $this->integerArrayFromEffective($effective, 'I');
            $selectedIndices = $this->selectedChoiceIndices($value, $options, $explicitIndices);
            $state += [
                'choice_values' => $password ? [] : $this->valueList($value),
                'default_choice_values' => $password ? [] : $this->valueList($defaultValue),
                'selected_indices' => $selectedIndices,
                'selected_indices_source' => $explicitIndices === [] ? ($selectedIndices === [] ? null : 'inferred_from_value') : 'field',
                'selected_options' => $this->selectedChoiceOptions($value, $options, $selectedIndices),
                'unmatched_values' => $this->unmatchedChoiceValues($value, $options),
            ];
        }

        if ($fieldType === 'Btn') {
            $checkedWidgets = array_values(array_filter(
                $widgets,
                static fn (array $widget): bool => ($widget['checked'] ?? false) === true
            ));
            $appearanceValues = [];
            foreach ($checkedWidgets as $widget) {
                $exportValue = $widget['export_value'] ?? $widget['appearance_state'] ?? null;
                if (is_string($exportValue) && $exportValue !== '' && !in_array($exportValue, $appearanceValues, true)) {
                    $appearanceValues[] = $exportValue;
                }
            }

            $effectiveCurrent = $value;
            $stateSource = $hasCurrent ? 'field_value' : 'missing_or_off';
            if (!$hasCurrent && $appearanceValues !== []) {
                $effectiveCurrent = count($appearanceValues) === 1 ? $appearanceValues[0] : $appearanceValues;
                $stateSource = 'widget_appearance_state';
            }

            $state += [
                'button_kind' => $this->buttonKind($flags),
                'current_state' => $password ? null : $value,
                'default_state' => $password ? null : $defaultValue,
                'effective_current_state' => $password ? null : $effectiveCurrent,
                'state_source' => $stateSource,
                'on_values' => $this->buttonOnValues($widgets),
                'checked_widget_count' => count($checkedWidgets),
                'widget_state_consistent' => $this->widgetsConsistentWithFieldValue($widgets),
            ];
            $state['display_value'] = $password ? '[redacted]' : $this->displayValue($effectiveCurrent);
            $state['changed_from_default'] = $password || !$hasDefault
                ? null
                : !$this->buttonValuesMatch($effectiveCurrent, $defaultValue);
        }

        if ($password) {
            $state['state_source'] = 'redacted_password';
        }

        return $state;
    }

    private function buttonKind(int $flags): string
    {
        if ($this->hasFlagBit($flags, 17)) {
            return 'push_button';
        }

        return $this->hasFlagBit($flags, 16) ? 'radio' : 'checkbox';
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<string>
     */
    private function buttonOnValues(array $widgets): array
    {
        $values = [];
        foreach ($widgets as $widget) {
            foreach ($widget['appearance_states'] ?? [] as $state) {
                if (!is_string($state) || $state === 'Off' || in_array($state, $values, true)) {
                    continue;
                }

                $values[] = $state;
            }
        }

        return $values;
    }

    /**
     * @param list<array<string, mixed>> $widgets
     */
    private function widgetsConsistentWithFieldValue(array $widgets): ?bool
    {
        $sawComparison = false;
        foreach ($widgets as $widget) {
            $matches = $widget['state_matches_field_value'] ?? null;
            if ($matches === null) {
                continue;
            }

            $sawComparison = true;
            if ($matches !== true) {
                return false;
            }
        }

        return $sawComparison ? true : null;
    }

    /**
     * @param list<array<string, mixed>> $widgets
     * @return list<array<string, mixed>>
     */
    private function widgetsWithCurrentValueState(array $widgets, ?string $fieldType, int $flags, mixed $fieldValue): array
    {
        if ($fieldType !== 'Btn' || $this->hasFlagBit($flags, 17)) {
            return $widgets;
        }

        $fieldValues = $this->valueList($fieldValue);
        foreach ($widgets as $index => $widget) {
            $appearanceState = $widget['appearance_state'] ?? null;
            $checked = is_string($appearanceState) && $appearanceState !== '' && $appearanceState !== 'Off';
            $exportValue = $this->widgetExportValue($widget);
            $selectedByField = $exportValue !== null && in_array($exportValue, $fieldValues, true);
            $widgets[$index]['checked'] = $checked;
            $widgets[$index]['export_value'] = $exportValue;
            $widgets[$index]['selected_by_field_value'] = $selectedByField;
            $widgets[$index]['state_matches_field_value'] = $fieldValue === null || $exportValue === null
                ? null
                : ($checked ? $selectedByField : !$selectedByField);
        }

        return $widgets;
    }

    /**
     * @param array<string, mixed> $widget
     */
    private function widgetExportValue(array $widget): ?string
    {
        $appearanceState = $widget['appearance_state'] ?? null;
        $states = array_values(array_filter(
            $widget['appearance_states'] ?? [],
            static fn (mixed $state): bool => is_string($state) && $state !== 'Off'
        ));

        if (is_string($appearanceState) && $appearanceState !== 'Off' && in_array($appearanceState, $states, true)) {
            return $appearanceState;
        }

        if ($states !== []) {
            return $states[0];
        }

        return is_string($appearanceState) && $appearanceState !== 'Off' ? $appearanceState : null;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return list<int>
     */
    private function integerArrayFromEffective(array $effective, string $name): array
    {
        if (!isset($effective[$name])) {
            return [];
        }

        $value = trim($effective[$name]['value']);
        if (!str_starts_with($value, '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null || preg_match_all('/[+-]?\d+/', $body, $matches) === false) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    /**
     * @param list<array{export: string, label: string}> $options
     * @param list<int> $explicitIndices
     * @return list<int>
     */
    private function selectedChoiceIndices(mixed $value, array $options, array $explicitIndices): array
    {
        if ($explicitIndices !== []) {
            return array_values(array_filter($explicitIndices, static fn (int $index): bool => $index >= 0));
        }

        $indices = [];
        foreach ($this->valueList($value) as $selectedValue) {
            foreach ($options as $index => $option) {
                if (($option['export'] === $selectedValue || $option['label'] === $selectedValue) && !in_array($index, $indices, true)) {
                    $indices[] = $index;
                    break;
                }
            }
        }

        return $indices;
    }

    /**
     * @param list<array{export: string, label: string}> $options
     * @param list<int> $selectedIndices
     * @return list<array{index: int, export: string, label: string}>
     */
    private function selectedChoiceOptions(mixed $value, array $options, array $selectedIndices): array
    {
        $selected = [];
        $seen = [];
        foreach ($this->valueList($value) as $selectedValue) {
            foreach ($options as $index => $option) {
                if ($option['export'] !== $selectedValue && $option['label'] !== $selectedValue) {
                    continue;
                }

                $this->appendSelectedChoiceOption($selected, $seen, $index, $option);
                break;
            }
        }

        foreach ($selectedIndices as $index) {
            if (isset($options[$index])) {
                $this->appendSelectedChoiceOption($selected, $seen, $index, $options[$index]);
            }
        }

        return $selected;
    }

    /**
     * @param list<array{index: int, export: string, label: string}> $selected
     * @param array<string, true> $seen
     * @param array{export: string, label: string} $option
     */
    private function appendSelectedChoiceOption(array &$selected, array &$seen, int $index, array $option): void
    {
        $key = (string) $index . "\0" . $option['export'] . "\0" . $option['label'];
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $selected[] = [
            'index' => $index,
            'export' => $option['export'],
            'label' => $option['label'],
        ];
    }

    /**
     * @param list<array{export: string, label: string}> $options
     * @return list<string>
     */
    private function unmatchedChoiceValues(mixed $value, array $options): array
    {
        $unmatched = [];
        foreach ($this->valueList($value) as $selectedValue) {
            $matched = false;
            foreach ($options as $option) {
                if ($option['export'] === $selectedValue || $option['label'] === $selectedValue) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $unmatched[] = $selectedValue;
            }
        }

        return $unmatched;
    }

    /**
     * @return list<string>
     */
    private function valueList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            return [(string) $value];
        }

        $values = [];
        foreach ($value as $item) {
            if ($item === null) {
                continue;
            }

            $values[] = (string) $item;
        }

        return $values;
    }

    private function displayValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_array($value) ? implode(', ', $this->valueList($value)) : (string) $value;
    }

    private function valuesMatch(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return $this->valueList($left) === $this->valueList($right);
        }

        return $left === $right;
    }

    private function buttonValuesMatch(mixed $left, mixed $right): bool
    {
        return $this->normalizedButtonValueList($left) === $this->normalizedButtonValueList($right);
    }

    /**
     * @return list<string>
     */
    private function normalizedButtonValueList(mixed $value): array
    {
        return array_values(array_filter(
            $this->valueList($value),
            static fn (string $state): bool => $state !== 'Off'
        ));
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
    private function defaultAppearanceFromEffective(array $effective, array $objects): ?array
    {
        if (!isset($effective['DA'])) {
            return null;
        }

        $raw = $this->pdfValueToString($effective['DA']['value'], []);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $appearance = $this->parseDefaultAppearance($raw);
        $appearance = $this->defaultAppearanceWithResourceReview($appearance, $effective, $objects);
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
            'font_resource_resolved' => false,
            'font_resource_object' => null,
            'font_resource_base_font' => null,
            'font_resource_subtype' => null,
            'font_resource_encoding' => null,
            'font_descriptor_object' => null,
            'font_descriptor_name' => null,
            'font_descriptor_flags' => null,
            'font_weight' => null,
            'default_resource_source' => null,
            'default_resource_source_object' => null,
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
     * @param array<string, mixed> $appearance
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function defaultAppearanceWithResourceReview(array $appearance, array $effective, array $objects): array
    {
        $fontResource = $appearance['font_resource'] ?? null;
        if (!is_string($fontResource) || $fontResource === '') {
            return $appearance;
        }

        $resources = $this->defaultResourcesFromEffective($effective, $objects);
        $fonts = is_array($resources['fonts'] ?? null) ? $resources['fonts'] : [];
        $font = $fonts[$fontResource] ?? null;
        $appearance['default_resource_source'] = $resources['source'] ?? null;
        $appearance['default_resource_source_object'] = $resources['object'] ?? null;

        if (!is_array($font)) {
            return $appearance;
        }

        $descriptor = is_array($font['font_descriptor'] ?? null) ? $font['font_descriptor'] : [];
        $appearance['font_resource_resolved'] = true;
        $appearance['font_resource_object'] = $font['object'] ?? null;
        $appearance['font_resource_base_font'] = $font['base_font'] ?? null;
        $appearance['font_resource_subtype'] = $font['subtype'] ?? null;
        $appearance['font_resource_encoding'] = $font['encoding'] ?? null;
        $appearance['font_descriptor_object'] = $descriptor['object'] ?? null;
        $appearance['font_descriptor_name'] = $descriptor['font_name'] ?? null;
        $appearance['font_descriptor_flags'] = $descriptor['flags'] ?? null;
        $appearance['font_weight'] = $descriptor['font_weight'] ?? null;

        return $appearance;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDefaultResources(): array
    {
        return [
            'source' => null,
            'object' => null,
            'font_count' => 0,
            'fonts' => [],
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function defaultResourcesFromEffective(array $effective, array $objects): array
    {
        if (!isset($effective['DR'])) {
            return $this->emptyDefaultResources();
        }

        $resources = $this->resolvedDictionaryFromValue($effective['DR']['value'], $objects);
        if ($resources === null) {
            return $this->emptyDefaultResources();
        }

        $fonts = $this->fontResourcesFromDefaultResourceDictionary($resources['body'], $objects);

        return [
            'source' => $effective['DR']['source'],
            'object' => $resources['object'],
            'font_count' => count($fonts),
            'fonts' => $fonts,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, array<string, mixed>>
     */
    private function fontResourcesFromDefaultResourceDictionary(string $resourceDictionary, array $objects): array
    {
        $fontValue = $this->valueAfterName($resourceDictionary, 'Font');
        if ($fontValue === null) {
            return [];
        }

        $fontDictionary = $this->resolvedDictionaryFromValue($fontValue, $objects);
        if ($fontDictionary === null) {
            return [];
        }

        $fonts = [];
        $body = $fontDictionary['body'];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($body[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($body, $offset);
            $resourceName = $this->decodePdfName(substr($body, $offset + 1, $nameEnd - $offset - 1));
            $offset = $nameEnd;
            $this->skipWhitespace($body, $offset);

            $fontObject = null;
            $fontBody = null;
            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
                $fontObject = (int) $match[1];
                $fontBody = $this->dictionaryObjectBody($objects[$fontObject] ?? '');
                $offset += strlen($match[0]);
            } elseif (substr($body, $offset, 2) === '<<') {
                $endOffset = null;
                $fontBody = $this->readPdfDictionaryAt($body, $offset, $endOffset);
                $offset = $endOffset ?? ($offset + 2);
            } else {
                continue;
            }

            if ($fontBody === null) {
                continue;
            }

            $fonts[$resourceName] = $this->fontResourceReview($resourceName, $fontObject, $fontBody, $objects);
        }

        return $fonts;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function fontResourceReview(string $resourceName, ?int $objectNumber, string $fontBody, array $objects): array
    {
        return [
            'resource_name' => $resourceName,
            'object' => $objectNumber,
            'type' => $this->pdfNameValueAfterName($fontBody, 'Type'),
            'subtype' => $this->pdfNameValueAfterName($fontBody, 'Subtype'),
            'base_font' => $this->pdfNameValueAfterName($fontBody, 'BaseFont'),
            'encoding' => $this->fontEncodingName($fontBody, $objects),
            'font_descriptor' => $this->fontDescriptorReview($fontBody, $objects),
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontEncodingName(string $fontBody, array $objects): ?string
    {
        $value = $this->valueAfterName($fontBody, 'Encoding');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '/')) {
            return $this->decodePdfName($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            $object = trim($objects[(int) $match[1]]);
            if (str_starts_with($object, '/')) {
                return $this->decodePdfName($object);
            }

            $dictionary = $this->dictionaryObjectBody($object) ?? (str_starts_with($object, '<<') ? $this->readPdfDictionaryAt($object, 0) : null);
            return $dictionary === null ? null : $this->pdfNameValueAfterName($dictionary, 'BaseEncoding');
        }

        $dictionary = str_starts_with($value, '<<') ? $this->readPdfDictionaryAt($value, 0) : null;
        return $dictionary === null ? null : $this->pdfNameValueAfterName($dictionary, 'BaseEncoding');
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function fontDescriptorReview(string $fontBody, array $objects): ?array
    {
        $descriptorObject = $this->objectReferenceValueAfterName($fontBody, 'FontDescriptor');
        if ($descriptorObject !== null) {
            $descriptorBody = $this->dictionaryObjectBody($objects[$descriptorObject] ?? '');
        } else {
            $descriptorValue = $this->valueAfterName($fontBody, 'FontDescriptor');
            $descriptorBody = $descriptorValue !== null && str_starts_with(trim($descriptorValue), '<<')
                ? $this->readPdfDictionaryAt($descriptorValue, 0)
                : null;
        }

        if ($descriptorBody === null) {
            return null;
        }

        return [
            'object' => $descriptorObject,
            'font_name' => $this->pdfNameValueAfterName($descriptorBody, 'FontName'),
            'flags' => $this->numberValueAfterName($descriptorBody, 'Flags'),
            'font_weight' => $this->numberValueAfterName($descriptorBody, 'FontWeight'),
        ];
    }

    /**
     * @param list<int> $widgetRefs
     * @param array<int, string> $objects
     * @param array<string, mixed>|null $fieldDefaultAppearance
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @param array<int, int> $pageIndexes
     * @param array<int, array{page_index: int, page_object: int}> $pageWidgets
     * @param array<int, string> $fieldNamesByObject
     * @return list<array<string, mixed>>
     */
    private function widgetsForField(
        array $widgetRefs,
        array $objects,
        ?array $fieldDefaultAppearance,
        array $effective,
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
            $widgetAppearance = $this->widgetDefaultAppearance($body, $fieldDefaultAppearance, $effective, $objects);
            $referencedFromPageAnnots = isset($pageWidgets[$widgetRef]);
            $appearanceState = $this->pdfNameValueAfterName($body, 'AS');
            $normalAppearance = $this->normalAppearanceReview($body, $objects, $appearanceState);

            $widgets[] = [
                'object' => $widgetRef,
                'page_index' => $pageIndex,
                'page_object' => $pageObject,
                'page_annotation_index' => $pageWidgets[$widgetRef]['annotation_index'] ?? null,
                'referenced_from_page_annots' => $referencedFromPageAnnots,
                'rect' => $this->rectFromAnnotation($body),
                'annotation_flags' => $annotationFlags,
                'annotation_flag_names' => $this->annotationFlagNames($annotationFlags ?? 0),
                'annotation_visibility' => $this->annotationVisibility($annotationFlags ?? 0),
                'hidden' => $this->annotationFlagsHideWidget($annotationFlags ?? 0),
                'visible' => !$this->annotationFlagsHideWidget($annotationFlags ?? 0),
                'printable' => $this->hasFlagBit($annotationFlags ?? 0, 3),
                'no_view' => $this->hasFlagBit($annotationFlags ?? 0, 6),
                'appearance_state' => $appearanceState,
                'appearance_states' => is_array($normalAppearance) ? $normalAppearance['available_states'] : [],
                'normal_appearance' => $normalAppearance,
                'default_appearance' => $widgetAppearance,
                'actions' => $this->actionsFromDictionary($body, $objects, $fieldNamesByObject, 'widget', $widgetRef),
            ];
        }

        return $widgets;
    }

    /**
     * @return list<string>
     */
    private function annotationFlagNames(int $flags): array
    {
        $names = [];
        foreach (self::ANNOTATION_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function annotationFlagsHideWidget(int $flags): bool
    {
        return $this->hasFlagBit($flags, 1)
            || $this->hasFlagBit($flags, 2)
            || $this->hasFlagBit($flags, 6);
    }

    private function annotationVisibility(int $flags): string
    {
        if ($this->hasFlagBit($flags, 2)) {
            return 'hidden';
        }

        if ($this->hasFlagBit($flags, 1)) {
            return 'invisible';
        }

        if ($this->hasFlagBit($flags, 6)) {
            return 'no_view';
        }

        return 'visible';
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
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return array<string, mixed>|null
     */
    private function widgetDefaultAppearance(string $widgetBody, ?array $fieldDefaultAppearance, array $effective, array $objects): ?array
    {
        $raw = $this->pdfStringValueAfterName($widgetBody, 'DA', []);
        if ($raw === null || $raw === '') {
            return $fieldDefaultAppearance;
        }

        $appearance = $this->parseDefaultAppearance($raw);
        $appearance = $this->defaultAppearanceWithResourceReview($appearance, $effective, $objects);
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
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     */
    private function normalAppearanceReview(string $widgetBody, array $objects, ?string $appearanceState): ?array
    {
        $apValue = $this->valueAfterName($widgetBody, 'AP');
        if ($apValue === null) {
            return null;
        }

        $ap = $this->resolvedDictionaryFromValue($apValue, $objects);
        if ($ap === null) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => null,
                'normal_appearance_type' => 'unresolved',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => null,
                'state_matches_appearance' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        $normalValue = $this->valueAfterName($ap['body'], 'N');
        if ($normalValue === null) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => $ap['object'],
                'normal_appearance_type' => 'missing',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => null,
                'state_matches_appearance' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        if ($this->valueReferencesStreamObject($normalValue, $objects)) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => $ap['object'],
                'normal_appearance_type' => 'direct_stream',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => $this->appearanceStreamReviewFromValue($normalValue, $objects, null, 'normal_direct'),
                'state_matches_appearance' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        $normalDictionary = $this->resolvedDictionaryFromValue($normalValue, $objects);
        if ($normalDictionary === null) {
            return [
                'source' => 'widget_appearance_dictionary',
                'appearance_dictionary_object' => $ap['object'],
                'normal_appearance_type' => 'unresolved',
                'appearance_state' => $appearanceState,
                'available_states' => [],
                'selected_state' => null,
                'selected_appearance' => null,
                'state_matches_appearance' => null,
                'appearance_value_used_for_import' => false,
                'payload_text_exposed' => false,
                'executes_appearance_streams' => false,
                'renders_appearances' => false,
                'executes_action' => false,
            ];
        }

        $entries = $this->dictionaryNameValueMap($normalDictionary['body']);
        $availableStates = array_keys($entries);
        $selectedValue = null;
        if ($appearanceState !== null && array_key_exists($appearanceState, $entries)) {
            $selectedValue = $entries[$appearanceState];
        }

        return [
            'source' => 'widget_appearance_dictionary',
            'appearance_dictionary_object' => $ap['object'],
            'normal_appearance_object' => $normalDictionary['object'],
            'normal_appearance_type' => 'state_dictionary',
            'appearance_state' => $appearanceState,
            'available_states' => $availableStates,
            'selected_state' => $selectedValue === null ? null : $appearanceState,
            'selected_appearance' => $selectedValue === null
                ? null
                : $this->appearanceStreamReviewFromValue($selectedValue, $objects, $appearanceState, 'normal_state'),
            'state_matches_appearance' => $appearanceState === null ? null : $selectedValue !== null,
            'stale_appearance_state' => $appearanceState !== null && $selectedValue === null,
            'appearance_value_used_for_import' => false,
            'payload_text_exposed' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     * @param array<int, string> $objects
     */
    private function appearanceStreamReviewFromValue(string $value, array $objects, ?string $state, string $source): ?array
    {
        $value = trim($value);
        $objectNumber = null;
        $objectBody = null;
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            $objectBody = $objects[$objectNumber] ?? null;
        } elseif (str_starts_with($value, '<<')) {
            $objectBody = $value;
        }

        if ($objectBody === null) {
            return null;
        }

        $dictionaryBody = $this->dictionaryObjectBody($objectBody) ?? (str_starts_with(trim($objectBody), '<<') ? $this->readPdfDictionaryAt($objectBody, 0) : null);
        if ($dictionaryBody === null) {
            return null;
        }

        $decodedStream = $this->decodeStreamObject($objectBody, $objects);
        $resources = $this->appearanceResourceReview($dictionaryBody, $objects);

        return [
            'source' => $source,
            'state' => $state,
            'object' => $objectNumber,
            'type' => $this->pdfNameValueAfterName($dictionaryBody, 'Type'),
            'subtype' => $this->pdfNameValueAfterName($dictionaryBody, 'Subtype'),
            'form_xobject' => $this->pdfNameValueAfterName($dictionaryBody, 'Type') === 'XObject'
                && $this->pdfNameValueAfterName($dictionaryBody, 'Subtype') === 'Form',
            'bbox' => $this->numericArrayValueAfterName($dictionaryBody, 'BBox'),
            'matrix' => $this->numericArrayValueAfterName($dictionaryBody, 'Matrix'),
            'declared_length_bytes' => $this->numberValueAfterName($dictionaryBody, 'Length'),
            'filters' => $this->streamObjectFilters($objectBody, $objects),
            'decoded_stream_available' => $decodedStream !== null,
            'decoded_length_bytes' => $decodedStream === null ? null : strlen($decodedStream),
            'decoded_sha256' => $decodedStream === null ? null : hash('sha256', $decodedStream),
            'resource_object' => $resources['object'],
            'resource_font_names' => $resources['font_names'],
            'resource_xobject_names' => $resources['xobject_names'],
            'payload_text_exposed' => false,
            'imports_visible_text' => false,
            'executes_appearance_streams' => false,
            'renders_appearances' => false,
            'executes_action' => false,
        ];
    }

    /**
     * @return array{object: int|null, font_names: list<string>, xobject_names: list<string>}
     * @param array<int, string> $objects
     */
    private function appearanceResourceReview(string $appearanceDictionaryBody, array $objects): array
    {
        $resourcesValue = $this->valueAfterName($appearanceDictionaryBody, 'Resources');
        $resources = $resourcesValue === null ? null : $this->resolvedDictionaryFromValue($resourcesValue, $objects);
        if ($resources === null) {
            return [
                'object' => null,
                'font_names' => [],
                'xobject_names' => [],
            ];
        }

        return [
            'object' => $resources['object'],
            'font_names' => array_keys($this->fontResourcesFromDefaultResourceDictionary($resources['body'], $objects)),
            'xobject_names' => $this->xobjectResourceNamesFromResourceDictionary($resources['body'], $objects),
        ];
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function xobjectResourceNamesFromResourceDictionary(string $resourceDictionary, array $objects): array
    {
        $xobjectValue = $this->valueAfterName($resourceDictionary, 'XObject');
        $xobjects = $xobjectValue === null ? null : $this->resolvedDictionaryFromValue($xobjectValue, $objects);
        if ($xobjects === null) {
            return [];
        }

        return array_keys($this->dictionaryNameValueMap($xobjects['body']));
    }

    /**
     * @return array<string, string>
     */
    private function dictionaryNameValueMap(string $dictionaryBody): array
    {
        $entries = [];
        $offset = 0;
        $length = strlen($dictionaryBody);
        while ($offset < $length) {
            $this->skipWhitespace($dictionaryBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if ($dictionaryBody[$offset] !== '/') {
                $offset++;
                continue;
            }

            $nameEnd = $this->skipPdfName($dictionaryBody, $offset);
            $name = $this->decodePdfName(substr($dictionaryBody, $offset, $nameEnd - $offset));
            $offset = $nameEnd;
            $endOffset = null;
            $value = $this->readPdfValueAt($dictionaryBody, $offset, $endOffset);
            if ($value === null || $endOffset === null) {
                continue;
            }

            $entries[$name] = $value;
            $offset = $endOffset;
        }

        return $entries;
    }

    /**
     * @return list<float>|null
     */
    private function numericArrayValueAfterName(string $body, string $name): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return [];
        }

        return $this->numbersFromPdfArray($arrayBody);
    }

    /**
     * @param array<int, string> $objects
     */
    private function valueReferencesStreamObject(string $value, array $objects): bool
    {
        return preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) === 1
            && str_contains($objects[(int) $match[1]] ?? '', 'stream');
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
     * @return array<int, array{page_index: int, page_object: int, annotation_index: int}>
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
            foreach ($annotationRefs as $annotationIndex => $annotationRef) {
                $annotationBody = $this->dictionaryObjectBody($objects[$annotationRef] ?? '') ?? '';
                if ($annotationBody === '' || !$this->isWidget($annotationBody)) {
                    continue;
                }

                $widgets[$annotationRef] = [
                    'page_index' => $pageIndex,
                    'page_object' => $pageObjectNumber,
                    'annotation_index' => $annotationIndex,
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
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function scalarListValueAfterName(string $body, string $name, array $objects): array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return [];
        }

        $value = trim($value);
        if (str_starts_with($value, '[')) {
            $arrayBody = $this->arrayBodyFromValue($value);
            return $arrayBody === null ? [] : $this->scalarValuesFromArrayBody($arrayBody, $objects);
        }

        $scalar = $this->pdfValueToString($value, $objects);
        return $scalar === null ? [] : [$scalar];
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

    private function realValueAfterName(string $body, string $name): ?float
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', trim($value), $match) !== 1) {
            return null;
        }

        return (float) $match[0];
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

    private function readPdfValueAt(string $body, int $offset, ?int &$endOffset = null): ?string
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            $endOffset = $offset + strlen($ref[0]);
            return $ref[0];
        }

        if ($body[$offset] === '[') {
            $end = null;
            $this->readPdfArrayAt($body, $offset, $end);
            if ($end === null) {
                return null;
            }
            $endOffset = $end;
            return substr($body, $offset, $end - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $end = null;
            $this->readPdfDictionaryAt($body, $offset, $end);
            if ($end === null) {
                return null;
            }
            $endOffset = $end;
            return substr($body, $offset, $end - $offset);
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

        if ($end === $offset) {
            return null;
        }

        $endOffset = $end;
        return substr($body, $offset, $end - $offset);
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
