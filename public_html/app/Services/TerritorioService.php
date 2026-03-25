<?php

class TerritorioService
{
    /* =====================================================
       CAMINHOS
    ===================================================== */

    private static function geojsonPath(): string
    {
        return __DIR__ . '/../../storage/geo/municipios_pa.geojson';
    }

    private static function csvRegioesPath(): string
    {
        return __DIR__ . '/../../storage/csv/municipios_regioes_pa.csv';
    }

    /* =====================================================
       MUNICÍPIOS AFETADOS
    ===================================================== */

    public static function municipiosAfetados(array $poligonoAlerta): array
    {
        $municipios = self::carregarMunicipios();
        $afetados = [];

        foreach ($municipios as $m) {
            if (self::intersecta($poligonoAlerta, $m['geometry'])) {
                $afetados[] = [
                    'codigo' => $m['codigo'],
                    'nome'   => $m['nome']
                ];
            }
        }

        return $afetados;
    }

    private static function carregarMunicipios(): array
    {
        $arquivo = self::geojsonPath();

        if (!file_exists($arquivo)) {
            throw new Exception('Arquivo municipios_pa.geojson não encontrado');
        }

        $geojson = json_decode(file_get_contents($arquivo), true);
        $lista = [];

        foreach ($geojson['features'] as $feature) {
            $lista[] = [
                'codigo'   => $feature['properties']['id'],
                'nome'     => $feature['properties']['name'],
                'geometry' => $feature['geometry']
            ];
        }

        return $lista;
    }

    /* =====================================================
       REGIÕES DE INTEGRAÇÃO
    ===================================================== */

    public static function regioesAfetadas(array $municipios): array
    {
        $mapa = self::carregarMapaRegioes();
        $regioes = [];

        foreach ($municipios as $m) {
            $codigo = $m['codigo'];

            if (isset($mapa[$codigo])) {
                $regioes[$mapa[$codigo]] = true;
            }
        }

        return $regioes;
    }

    public static function municipiosPorRegiao(array $municipios): array
    {
        $mapa = self::carregarMapaRegioes();
        $regioes = [];

        foreach ($municipios as $municipio) {
            $codigo = (string) ($municipio['codigo'] ?? '');
            $nome = trim((string) ($municipio['nome'] ?? ''));
            $regiao = $mapa[$codigo] ?? 'Regiao nao identificada';

            if ($nome === '') {
                continue;
            }

            if (!isset($regioes[$regiao])) {
                $regioes[$regiao] = [];
            }

            $regioes[$regiao][] = $nome;
        }

        foreach ($regioes as &$listaMunicipios) {
            $listaMunicipios = array_values(array_unique($listaMunicipios));
            sort($listaMunicipios, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($listaMunicipios);

        ksort($regioes, SORT_NATURAL | SORT_FLAG_CASE);

        return $regioes;
    }

    private static function carregarMapaRegioes(): array
    {
        $arquivo = self::csvRegioesPath();

        if (!file_exists($arquivo)) {
            throw new Exception('Arquivo municipios_regioes_pa.csv não encontrado');
        }

        $handle = fopen($arquivo, 'r');

        // lê cabeçalho e remove BOM
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$header || count($header) < 3) {
            throw new Exception('CSV inválido');
        }

        $mapa = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {

            // ignora linhas inválidas
            if (count($row) < 3) {
                continue;
            }

            $cod_ibge = trim($row[0]);
            $regiao   = trim($row[2]);

            if ($cod_ibge !== '' && $regiao !== '') {
                $mapa[$cod_ibge] = $regiao;
            }
        }

        fclose($handle);
        return $mapa;
    }

    /* =====================================================
       GEOMETRIA
    ===================================================== */

    private static function intersecta(array $geo1, array $geo2): bool
    {
        $coords1 = self::extrairPontos($geo1);
        $coords2 = self::extrairPontos($geo2);

        if (empty($coords1) || empty($coords2)) {
            return false;
        }

        $bbox1 = self::boundingBox($coords1);
        $bbox2 = self::boundingBox($coords2);

        if ($bbox1 === null || $bbox2 === null) {
            return false;
        }

        if (!self::bboxIntersecta($bbox1, $bbox2)) {
            return false;
        }

        foreach ($coords1 as $p) {
            if (self::pontoDentroPoligono($p, $coords2)) {
                return true;
            }
        }

        foreach ($coords2 as $p) {
            if (self::pontoDentroPoligono($p, $coords1)) {
                return true;
            }
        }

        return false;
    }

    private static function boundingBox(array $coords): ?array
    {
        if (empty($coords)) {
            return null;
        }

        $lngs = array_column($coords, 0);
        $lats = array_column($coords, 1);

        if (empty($lngs) || empty($lats)) {
            return null;
        }

        return [
            min($lngs),
            min($lats),
            max($lngs),
            max($lats)
        ];
    }

    private static function bboxIntersecta(array $a, array $b): bool
    {
        return !(
            $a[2] < $b[0] ||
            $a[0] > $b[2] ||
            $a[3] < $b[1] ||
            $a[1] > $b[3]
        );
    }

    private static function extrairPontos(array $geo): array
    {
        $pontos = [];

        if (($geo['type'] ?? '') === 'FeatureCollection') {
            foreach ($geo['features'] as $f) {
                $pontos = array_merge($pontos, self::extrairPontos($f['geometry']));
            }
        }

        if (($geo['type'] ?? '') === 'Polygon') {
            foreach ($geo['coordinates'][0] ?? [] as $c) {
                $pontos[] = $c;
            }
        }

        if (($geo['type'] ?? '') === 'MultiPolygon') {
            foreach ($geo['coordinates'] as $poly) {
                foreach ($poly[0] ?? [] as $c) {
                    $pontos[] = $c;
                }
            }
        }

        return $pontos;
    }

    private static function pontoDentroPoligono(array $ponto, array $poligono): bool
    {
        $x = $ponto[0];
        $y = $ponto[1];

        $inside = false;
        $n = count($poligono);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $poligono[$i][0];
            $yi = $poligono[$i][1];
            $xj = $poligono[$j][0];
            $yj = $poligono[$j][1];

            $intersect = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
    
    
    public static function kmlParaGeojson(string $kml): array
    {
        libxml_use_internal_errors(true);
    
        $xml = simplexml_load_string($kml);
        if (!$xml) {
            return [];
        }
    
        $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');
    
        $placemarks = $xml->xpath('//kml:Placemark');
        if (!$placemarks) {
            return [];
        }
    
        $features = [];
    
        foreach ($placemarks as $placemark) {
    
            if (!isset($placemark->Polygon)) {
                continue;
            }
    
            $coords = (string)$placemark->Polygon
                ->outerBoundaryIs
                ->LinearRing
                ->coordinates;
    
            $coordsArray = [];
    
            foreach (explode(' ', trim($coords)) as $coord) {
                [$lon, $lat] = explode(',', trim($coord));
                $coordsArray[] = [(float)$lon, (float)$lat];
            }
    
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$coordsArray]
                ],
                'properties' => new stdClass()
            ];
        }
    
        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }

}
