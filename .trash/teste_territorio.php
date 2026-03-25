<?php


/* =====================================================
   IMPORTA DEPENDÊNCIAS
===================================================== */
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Services/TerritorioService.php';

/* =====================================================
   BUSCA UM ALERTA COM POLÍGONO
===================================================== */
$db = Database::getConnection();

$stmt = $db->query("
    SELECT id, numero, poligono
    FROM alertas
    WHERE poligono IS NOT NULL
    ORDER BY id DESC
    LIMIT 1
");

$alerta = $stmt->fetch();

if (!$alerta) {
    die('❌ Nenhum alerta com polígono encontrado no banco.');
}

/* =====================================================
   CONVERTE POLÍGONO
===================================================== */
$poligono = json_decode($alerta['poligono'], true);

if (!$poligono || !isset($poligono['type'])) {
    die('❌ Polígono inválido ou malformado.');
}

/* =====================================================
   TESTE – MUNICÍPIOS AFETADOS
===================================================== */
$municipios = TerritorioService::municipiosAfetados($poligono);

/* =====================================================
   TESTE – REGIÕES DE INTEGRAÇÃO
===================================================== */
$regioes = TerritorioService::regioesAfetadas($municipios);

/* =====================================================
   SAÍDA FORMATADA
===================================================== */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Teste Inteligência Territorial</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f4f6f9;
        padding: 20px;
    }
    h1 {
        color: #0b3c5d;
    }
    h2 {
        margin-top: 30px;
        color: #1f6fb2;
    }
    pre {
        background: #fff;
        padding: 15px;
        border-radius: 6px;
        border: 1px solid #ddd;
        overflow-x: auto;
    }
    .ok {
        color: #2ecc71;
        font-weight: bold;
    }
</style>
</head>
<body>

<h1>🧭 Teste da Inteligência Territorial</h1>

<p><strong>Alerta testado:</strong> <?= htmlspecialchars($alerta['numero']) ?></p>

<h2>📍 Municípios Afetados</h2>

<?php if (empty($municipios)): ?>
    <p>❌ Nenhum município identificado.</p>
<?php else: ?>
    <pre><?php print_r($municipios); ?></pre>
    <p class="ok">✔ Municípios identificados com sucesso</p>
<?php endif; ?>

<h2>🗺️ Regiões de Integração Afetadas</h2>

<?php if (empty($regioes)): ?>
    <p>❌ Nenhuma região identificada.</p>
<?php else: ?>
    <pre><?php print_r($regioes); ?></pre>
    <p class="ok">✔ Regiões de integração identificadas com sucesso</p>
<?php endif; ?>

</body>
</html>
