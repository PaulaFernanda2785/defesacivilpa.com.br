<?php
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN','GESTOR','ANALISTA','OPERACOES']);


$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('ID inválido');
}

$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT numero, area_geojson, poligono
    FROM alertas
    WHERE id = ?
");
$stmt->execute([$id]);
$alerta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alerta) {
    die('Alerta não encontrado');
}

/* Seleciona o GeoJSON disponível */
$geojsonRaw = $alerta['area_geojson'] ?: $alerta['poligono'];

if (!$geojsonRaw) {
    die('Polígono não encontrado');
}

$geojson = json_decode($geojsonRaw, true);
if (!$geojson) {
    die('GeoJSON inválido');
}

/* ===============================
   NORMALIZA GEOMETRIAS
================================ */
$geometrias = [];

/* FeatureCollection */
if (($geojson['type'] ?? '') === 'FeatureCollection') {
    foreach ($geojson['features'] as $feature) {
        if (!empty($feature['geometry'])) {
            $geometrias[] = $feature['geometry'];
        }
    }
}

/* Feature */
elseif (($geojson['type'] ?? '') === 'Feature') {
    $geometrias[] = $geojson['geometry'];
}

/* Geometry pura */
elseif (!empty($geojson['coordinates'])) {
    $geometrias[] = $geojson;
}

if (!$geometrias) {
    die('Nenhuma geometria válida encontrada');
}

/* ===============================
   CONVERTE PARA KML
================================ */
$placemarks = '';

foreach ($geometrias as $geom) {

    if ($geom['type'] === 'Polygon') {
        $polygons = [$geom['coordinates']];
    } elseif ($geom['type'] === 'MultiPolygon') {
        $polygons = $geom['coordinates'];
    } else {
        continue;
    }

    foreach ($polygons as $poly) {
        $coords = [];

        foreach ($poly[0] as $p) {
            $coords[] = "{$p[0]},{$p[1]},0";
        }

        $placemarks .= "
        <Placemark>
            <name>Área do Alerta</name>
            <Style>
                <LineStyle>
                    <color>ff0000ff</color>
                    <width>3</width>
                </LineStyle>
                <PolyStyle>
                    <color>660000ff</color>
                </PolyStyle>
            </Style>
            <Polygon>
                <outerBoundaryIs>
                    <LinearRing>
                        <coordinates>
                            " . implode(' ', $coords) . "
                        </coordinates>
                    </LinearRing>
                </outerBoundaryIs>
            </Polygon>
        </Placemark>";
    }
}

/* =========================
   REGISTRA HISTÓRICO
========================= */
HistoricoService::registrar(
    $_SESSION['usuario']['id'],
    $_SESSION['usuario']['nome'],
    'BAIXAR_KML',
    'Baixou arquivo KML do alerta',
    "Alerta nº {$alerta['numero']} (ID $id)"
);


/* ===============================
   SAÍDA KML
================================ */
header('Content-Type: application/vnd.google-earth.kml+xml');
header(
    'Content-Disposition: attachment; filename="alerta_' .
    preg_replace('/[^0-9A-Za-z_-]/', '_', $alerta['numero']) .
    '.kml"'
);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
    <name>Alerta <?= htmlspecialchars($alerta['numero']) ?></name>
    <?= $placemarks ?>
</Document>
</kml>
