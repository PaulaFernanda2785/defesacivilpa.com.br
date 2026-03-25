<?php

class InmetService
{
    private const BASE_RSS = 'https://apiprevmet3.inmet.gov.br/avisos/rss/';
    private const USER_AGENT = 'DefesaCivilPA/1.0';
    private const SSL_FALLBACK_HOSTS = [
        'alert-as.inmet.gov.br',
        'alertas2.inmet.gov.br',
        'apiprevmet3.inmet.gov.br',
        'avisos.inmet.gov.br',
        'inmet.gov.br',
        'portal.inmet.gov.br',
    ];

    public static function importarPorUrl(string $url): array
    {
        $url = trim($url);
        $id = self::extrairIdDaUrl($url);

        if (!$id) {
            throw new Exception('URL do alerta do INMET invalida.');
        }

        $xml = self::carregarXmlPorId($url, $id);

        if (!$xml) {
            throw new Exception('Nao foi possivel carregar o XML oficial do INMET. Verifique a URL e tente novamente.');
        }

        $dados = self::parseRss($xml);

        return array_merge($dados, [
            'fonte' => 'INMET',
            'inmet_id' => $id,
            'inmet_url' => $url,
        ]);
    }

    private static function extrairIdDaUrl(string $url): ?string
    {
        $parsed = parse_url($url);

        if (!$parsed || empty($parsed['host']) || !self::hostConfiavel((string) $parsed['host'])) {
            return null;
        }

        $path = trim((string) ($parsed['path'] ?? ''), '/');

        if ($path === '') {
            return null;
        }

        $partes = explode('/', $path);
        $ultimo = end($partes);

        if (!is_string($ultimo) || !preg_match('/^\d+$/', $ultimo)) {
            return null;
        }

        return $ultimo;
    }

    private static function hostConfiavel(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return false;
        }

        if (in_array($host, self::SSL_FALLBACK_HOSTS, true)) {
            return true;
        }

        return str_ends_with($host, '.inmet.gov.br');
    }

    private static function carregarXmlPorId(string $sourceUrl, string $id): ?SimpleXMLElement
    {
        foreach (self::urlsCandidatas($sourceUrl, $id) as $url) {
            $xmlString = self::baixarXml($url);

            if (!$xmlString) {
                continue;
            }

            $xml = self::criarSimpleXml($xmlString);

            if ($xml instanceof SimpleXMLElement && $xml->getName() === 'alert') {
                return $xml;
            }
        }

        return null;
    }

    private static function urlsCandidatas(string $sourceUrl, string $id): array
    {
        $urls = [];
        $sourceUrl = rtrim(trim($sourceUrl), '/');

        if (str_contains($sourceUrl, '/avisos/rss/')) {
            $urls[] = $sourceUrl;
        }

        $urls[] = self::BASE_RSS . rawurlencode($id);

        return array_values(array_unique($urls));
    }

    private static function baixarXml(string $url): ?string
    {
        $resposta = self::executarRequisicao($url, true);

        if (self::respostaContemXml($resposta)) {
            return $resposta['body'];
        }

        if (!self::deveTentarSslFlexivel($url, $resposta)) {
            return null;
        }

        $fallback = self::executarRequisicao($url, false);

        if (self::respostaContemXml($fallback)) {
            return $fallback['body'];
        }

        return null;
    }

    private static function executarRequisicao(string $url, bool $strictSsl): array
    {
        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . self::USER_AGENT,
                'Accept: application/cap+xml, application/xml, text/xml;q=0.9,*/*;q=0.8',
            ],
        ];

        if ($strictSsl) {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
        } else {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $response = [
            'body' => is_string($body) ? $body : '',
            'http' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'errno' => (int) curl_errno($ch),
            'error' => (string) curl_error($ch),
        ];

        curl_close($ch);

        return $response;
    }

    private static function respostaContemXml(array $resposta): bool
    {
        if (($resposta['http'] ?? 0) !== 200) {
            return false;
        }

        $body = trim((string) ($resposta['body'] ?? ''));

        if ($body === '') {
            return false;
        }

        if (!str_starts_with($body, '<?xml') && !str_starts_with($body, '<alert')) {
            return false;
        }

        return true;
    }

    private static function deveTentarSslFlexivel(string $url, array $resposta): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $errno = (int) ($resposta['errno'] ?? 0);
        $error = strtolower((string) ($resposta['error'] ?? ''));

        if (!self::hostConfiavel($host)) {
            return false;
        }

        return $errno === 60 ||
            str_contains($error, 'certificate') ||
            str_contains($error, 'issuer certificate');
    }

    private static function criarSimpleXml(string $xmlString): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($xml === false) {
            libxml_clear_errors();
            return null;
        }

        return $xml;
    }

    private static function parseRss(SimpleXMLElement $xml): array
    {
        $capNs = 'urn:oasis:names:tc:emergency:cap:1.2';

        if ($xml->getName() !== 'alert') {
            throw new Exception('XML nao esta no formato CAP esperado.');
        }

        $info = $xml->children($capNs)->info;

        if (!$info) {
            throw new Exception('Bloco <info> nao encontrado no XML CAP.');
        }

        $dataAlertaRaw = null;

        if (isset($xml->sent) && (string) $xml->sent !== '') {
            $dataAlertaRaw = (string) $xml->sent;
        } elseif (isset($info->onset) && (string) $info->onset !== '') {
            $dataAlertaRaw = (string) $info->onset;
        }

        if (!$dataAlertaRaw) {
            throw new Exception('INMET: data oficial do alerta nao encontrada.');
        }

        return [
            'data_alerta' => self::parseCapDate($dataAlertaRaw),
            'tipo_evento' => trim((string) $info->event),
            'nivel_gravidade' => self::mapearSeveridade((string) $info->severity),
            'inicio_alerta' => self::parseCapDate((string) $info->onset),
            'fim_alerta' => self::parseCapDate((string) $info->expires),
            'riscos' => trim((string) $info->description),
            'recomendacoes' => trim((string) $info->instruction),
            'municipios' => self::extrairMunicipiosCap($info),
            'area_geojson' => self::extrairPoligonoCap($info),
        ];
    }

    private static function parseCapDate(?string $data): ?string
    {
        if (!$data) {
            return null;
        }

        try {
            $dt = new DateTimeImmutable($data, new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception) {
            return null;
        }
    }

    private static function extrairMunicipiosCap(SimpleXMLElement $info): array
    {
        $lista = [];

        foreach ($info->area as $area) {
            if (!isset($area->areaDesc)) {
                continue;
            }

            $municipios = array_map('trim', explode(',', (string) $area->areaDesc));

            foreach ($municipios as $municipio) {
                if ($municipio !== '') {
                    $lista[] = $municipio;
                }
            }
        }

        return array_values(array_unique($lista));
    }

    private static function extrairPoligonoCap(SimpleXMLElement $info): ?array
    {
        foreach ($info->area as $area) {
            if (!isset($area->polygon)) {
                continue;
            }

            return [
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'geometry' => self::polygonCapParaGeoJson((string) $area->polygon),
                    'properties' => [],
                ]],
            ];
        }

        return null;
    }

    private static function polygonCapParaGeoJson(string $texto): array
    {
        $pares = preg_split('/\s+/', trim($texto));
        $coords = [];

        foreach ($pares as $par) {
            [$lat, $lon] = array_map('floatval', explode(',', $par));
            $coords[] = [$lon, $lat];
        }

        if ($coords !== [] && $coords[0] !== end($coords)) {
            $coords[] = $coords[0];
        }

        return [
            'type' => 'Polygon',
            'coordinates' => [$coords],
        ];
    }

    private static function mapearSeveridade(string $severity): string
    {
        return match (strtoupper(trim($severity))) {
            'MINOR' => 'BAIXO',
            'MODERATE' => 'MODERADO',
            'SEVERE' => 'ALTO',
            'EXTREME' => 'EXTREMO',
            default => strtoupper(trim($severity)),
        };
    }
}
