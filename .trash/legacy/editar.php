<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';

Protect::check(['ADMIN','GESTOR','ANALISTA']);

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: /pages/alertas/listar.php');
    exit;
}

$db = Database::getConnection();

$stmt = $db->prepare("SELECT * FROM alertas WHERE id = ?");
$stmt->execute([$id]);
$alerta = $stmt->fetch();

if (!$alerta) {
    header('Location: /pages/alertas/listar.php');
    exit;
}

$erro = $_GET['erro'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Editar Alerta <?= htmlspecialchars($alerta['numero']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/alertas.css">

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
</head>
<body>

<div class="layout">

    <aside class="sidebar">
        <div class="logo">
            <img src="/assets/images/logo-cedec.png">
            <h2>Multirriscos</h2>
        </div>
        <nav>
            <a href="/pages/painel.php">🏠 Painel</a>
            <a href="/pages/alertas/listar.php" class="active">🚨 Alertas</a>
        </nav>
        <div class="sidebar-footer">
            <div class="sistema-nome">
                Sistema Inteligente Multirriscos
            </div>
        
            <div class="sistema-info">
                Versão <strong>1.0.0</strong><br>
                Ambiente: <span class="ambiente producao">Produção</span>
            </div>
        
            <div class="sistema-orgao">
                Defesa Civil do Estado do Pará
            </div>
        </div>

    </aside>

    <main class="content">

        <header class="topbar">

            <div class="institucional">
                <strong>
                    Corpo de Bombeiros Militar do Pará<br>
                    Coordenadoria Estadual de Proteção e Defesa Civil
                </strong>
                
            </div>
            <div class="user-info">
                <a href="/logout.php" class="logout">Sair</a>
            </div>
        </header>
       <?php
            $breadcrumb = [
                'Painel'  => '/pages/painel.php',
                'Alertas' => '/pages/alertas/listar.php',
                'Editar Alerta' => null
            ];
            include __DIR__ . '/../_breadcrumb.php';
        ?>

        <section class="dashboard">

            <h1>Editar Alerta <?= htmlspecialchars($alerta['numero']) ?></h1>

            <?php if ($erro === 'campos'): ?>
                <div class="erro">Preencha todos os campos obrigatórios.</div>
            <?php elseif ($erro === 'salvar'): ?>
                <div class="erro">Erro ao atualizar o alerta.</div>
            <?php endif; ?>

            <div class="alerta-wrapper">
                <div class="alerta-container">

                    <!-- FORMULÁRIO -->
                    <form method="post"
                          action="/pages/alertas/atualizar.php"
                          class="form-alerta"
                          id="form-alerta">

                        <input type="hidden" name="id" value="<?= $alerta['id'] ?>">

                        <label>Tipo de Evento</label>
                        <select name="tipo_evento" required>
                            <?php
                            $eventos = ['Chuvas Intensas','Tempestades','Movimento de Massa','Inundação','Alagamentos'];
                            foreach ($eventos as $e):
                            ?>
                            <option <?= $alerta['tipo_evento']===$e?'selected':'' ?>>
                                <?= $e ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Nível de Gravidade</label>
                        <select name="nivel_gravidade" required>
                            <?php
                            $gravidades = ['BAIXO','MODERADO','ALTO','MUITO ALTO'];
                            foreach ($gravidades as $g):
                            ?>
                            <option <?= $alerta['nivel_gravidade']===$g?'selected':'' ?>>
                                <?= $g ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Data do Alerta</label>
                        <input type="date" name="data_alerta"
                               value="<?= $alerta['data_alerta'] ?>" required>

                        <label>Início do Alerta</label>
                        <input type="datetime-local" name="inicio_alerta"
                               value="<?= date('Y-m-d\TH:i', strtotime($alerta['inicio_alerta'])) ?>" required>

                        <label>Fim do Alerta</label>
                        <input type="datetime-local" name="fim_alerta"
                               value="<?= date('Y-m-d\TH:i', strtotime($alerta['fim_alerta'])) ?>" required>

                        <label>Riscos Potenciais</label>
                        <textarea name="riscos" required><?= htmlspecialchars($alerta['riscos']) ?></textarea>

                        <label>Recomendações</label>
                        <textarea name="recomendacoes" required><?= htmlspecialchars($alerta['recomendacoes']) ?></textarea>

                        <!-- MAPA -->
                        <input type="hidden" name="poligono" id="poligono"
                               value='<?= htmlspecialchars($alerta['poligono']) ?>'>
                        <input type="hidden" name="imagem_mapa" id="imagem_mapa">

                        <div class="acoes-form">
                            <button type="submit">Atualizar Alerta</button>
                        </div>

                    </form>

                    <div class="mapa-container">
                        <strong>Área afetada (pode redesenhar)</strong>
                        <div id="mapa"></div>
                    </div>

                </div>
            </div>

        </section>

        <?php include __DIR__ . '/../_footer.php'; ?>

    </main>
</div>

<script>
    const POLIGONO_EXISTENTE = <?= $alerta['poligono'] ?: 'null' ?>;
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

<script src="/assets/js/mapa-alerta-edicao.js"></script>

</body>
</html>
