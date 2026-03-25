<?php
require_once __DIR__ . '/../../app/Core/Protect.php';

Protect::check(['ADMIN','GESTOR','ANALISTA']);

$erro = $_GET['erro'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Cadastrar Alerta – INMET</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/alertas.css">

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
</head>
<body>

<div class="layout">

    <!-- MENU LATERAL -->
    <aside class="sidebar">
        <div class="logo">
            <img src="/assets/images/logo-cedec.png" alt="CEDEC-PA">
            <h2>Multirriscos</h2>
        </div>
        <nav>
            <a href="/pages/painel.php">🏠 Painel</a>
            <a href="/pages/alertas/listar.php" class="active">🚨 Alertas</a>
            <a href="/pages/mapas/inmet.php">🗺️ Mapa Multirriscos</a>
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

    <!-- CONTEÚDO -->
    <main class="content">

        <!-- CABEÇALHO -->
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
                'Cadastrar Alerta' => null
            ];
            include __DIR__ . '/../_breadcrumb.php';
        ?>


        <!-- CONTEÚDO DA PÁGINA -->
        <section class="dashboard">

            <h1>Cadastrar Alerta – INMET</h1>

            <!-- MENSAGENS DE ERRO -->
            <?php if ($erro === 'campos'): ?>
                <div class="erro">
                    Preencha todos os campos obrigatórios.
                </div>
            <?php elseif ($erro === 'mapa'): ?>
                <div class="erro">
                    É obrigatório desenhar a área afetada no mapa.
                </div>
            <?php elseif ($erro === 'salvar'): ?>
                <div class="erro">
                    Erro ao salvar o alerta. Tente novamente.
                </div>
            <?php endif; ?>

            <div class="alerta-wrapper">
                <div class="alerta-container">

                    <!-- FORMULÁRIO (SOMENTE TELA) -->
                    <form method="post"
                          action="/pages/alertas/salvar.php"
                          class="form-alerta"
                          id="form-alerta">

                        <a href="/pages/alertas/listar.php" class="voltar">
                            ← Voltar para alertas cadastrados
                        </a>

                        <label>Tipo de Evento</label>
                        <select name="tipo_evento" required>
                            <option value="">Selecione</option>
                            <option>Chuvas Intensas</option>
                            <option>Tempestades</option>
                            <option>Movimento de Massa</option>
                            <option>Inundação</option>
                            <option>Alagamentos</option>
                        </select>

                        <label>Nível de Gravidade</label>
                        <select name="nivel_gravidade" required>
                            <option value="">Selecione</option>
                            <option>BAIXO</option>
                            <option>MODERADO</option>
                            <option>ALTO</option>
                            <option>MUITO ALTO</option>
                        </select>

                        <label>Data do Alerta</label>
                        <input type="date" name="data_alerta" required>

                        <label>Início do Alerta</label>
                        <input type="datetime-local" name="inicio_alerta" required>

                        <label>Fim do Alerta</label>
                        <input type="datetime-local" name="fim_alerta" required>

                        <label>Riscos Potenciais</label>
                        <textarea name="riscos" required></textarea>

                        <label>Recomendações</label>
                        <textarea name="recomendacoes" required></textarea>

                        <!-- CAMPOS OCULTOS -->
                        <input type="hidden" name="poligono" id="poligono">
                        <input type="hidden" name="imagem_mapa" id="imagem_mapa">

                        <div class="acoes-form">
                            <button type="submit">Salvar Alerta</button>
                        </div>

                    </form>

                    <!-- MAPA -->
                    <div class="mapa-container">
                        <strong>Desenhe a área afetada</strong>
                        <div id="mapa"></div>
                    </div>

                </div>
            </div>

        </section>

        <?php include __DIR__ . '/../_footer.php'; ?>

    </main>

</div>

<!-- SCRIPTS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

<script src="/assets/js/mapa-alerta.js"></script>

</body>
</html>
