<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Helpers/TimeHelper.php';
require_once __DIR__ . '/../Services/HistoricoService.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/PdfService.php';



class AlertaEnvioService
{
    /**
     * Envio manual de alerta para COMPDEC
     */
    public static function enviar(int $alertaId, array $usuario): array
    {
        set_time_limit(300); // 5 minutos

        if ($alertaId <= 0) {
            return ['ok' => false, 'erro' => 'ID do alerta inválido'];
        }
    
        
        
        /* 🔄 REABRE CONEXÃO PARA EVITAR MySQL GONE AWAY */
        $db = Database::getConnection();

        /* 1️⃣ ALERTA */
        $stmt = $db->prepare("
            SELECT 
                id, 
                numero, 
                tipo_evento, 
                nivel_gravidade, 
                fonte,
                data_alerta, 
                inicio_alerta, 
                fim_alerta, 
                status, 
                imagem_mapa
            FROM alertas
            WHERE id = :id
        ");
        $stmt->execute([':id' => $alertaId]);
        $alerta = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if (!$alerta) {
            return ['ok' => false, 'erro' => 'Alerta não encontrado'];
        }
    
        if ($alerta['status'] !== 'ATIVO') {
            return ['ok' => false, 'erro' => 'Somente alertas ATIVOS podem ser enviados'];
        }
        
        // 🔴 3️⃣ NOVA REGRA — BLOQUEIO SEM MAPA
        if (empty($alerta['imagem_mapa'])) {
            return [
                'ok' => false,
                'erro' => 'O mapa do alerta ainda não foi gerado. Acesse o detalhe do alerta antes de enviar.'
            ];
        }
        // ⬇️ A PARTIR DAQUI O ALERTA ESTÁ APTO PARA ENVIO
        
        
        /* 2️⃣ MUNICÍPIOS AFETADOS */
        $stmt = $db->prepare("
            SELECT DISTINCT am.municipio_codigo, am.municipio_nome
            FROM alerta_municipios am
            WHERE am.alerta_id = :alerta
        ");
        $stmt->execute([':alerta' => $alertaId]);
        $municipiosAfetados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        if (!$municipiosAfetados) {
            return ['ok' => false, 'erro' => 'Este alerta não possui municípios vinculados'];
        }
    
        $municipiosMap = array_column($municipiosAfetados, 'municipio_nome');
    
        /* 3️⃣ MUNICÍPIOS AGRUPADOS (PDF) */
        $stmt = $db->prepare("
            SELECT mr.regiao_integracao, am.municipio_nome
            FROM alerta_municipios am
            JOIN municipios_regioes_pa mr ON mr.cod_ibge = am.municipio_codigo
            WHERE am.alerta_id = :alerta
            ORDER BY mr.regiao_integracao, am.municipio_nome
        ");
        $stmt->execute([':alerta' => $alertaId]);
    
        $municipiosPorRegiao = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $municipiosPorRegiao[$row['regiao_integracao']][] = $row['municipio_nome'];
        }
    
        /* 4️⃣ COMPDEC VÁLIDAS */
        $compdecs = self::buscarCompdecsValidas();
    
        $emailsDestino = [];
        foreach ($compdecs as $c) {
            if (in_array($c['municipio'], $municipiosMap)) {
                $emailsDestino[] = $c['email'];
            }
        }
    
        $emailsDestino = array_values(array_unique($emailsDestino));

        if (!$emailsDestino) {
            return [
                'ok' => false,
                'erro' => 'Nenhuma COMPDEC válida encontrada para os municípios afetados'
            ];
        }
        
        /* 🔴 LIMITE MÁXIMO DE 144 ENVIO POR EXECUÇÃO */
        $limiteMaximo = 30;
        
        $totalEmails = count($emailsDestino);

        $offset = (int)($_POST['offset'] ?? 0);
        
        $emailsDestino = array_slice(
            $emailsDestino,
            $offset,
            $limiteMaximo
        );
        
        $proximoOffset = $offset + $limiteMaximo;
        $temMais = $proximoOffset < $totalEmails;


    
        /* 5️⃣ PDF EM MEMÓRIA */
        $pdfBytes = PdfService::gerarAlerta(
            $alerta,
            $municipiosPorRegiao,
            $alerta['imagem_mapa'] ?? '',
            'string'
        );
    
        if (!$pdfBytes) {
            return ['ok' => false, 'erro' => 'Falha ao gerar o PDF do alerta'];
        }
    
        /* 6️⃣ CONTEÚDO DO EMAIL */
        $emailConteudo = self::montarEmailAlerta($alerta);
        
        $emailService = new EmailService();
    
        $enviados = 0;

        foreach ($emailsDestino as $destino) {
        
            $emailService = new EmailService(); // 🔄 nova instância por envio
        
            if ($emailService->enviar(
                $destino,
                $emailConteudo['assunto'],
                $emailConteudo['texto'],
                $emailConteudo['html'],
                [
                    'nome'  => 'alerta_' . $alerta['numero'] . '.pdf',
                    'bytes' => $pdfBytes
                ]
            )) {
                $enviados++;
            }
        }

        /* 🔥 LIBERA MEMÓRIA DO PDF */
        unset($pdfBytes);

    
        if ($enviados === 0) {
            return ['ok' => false, 'erro' => 'Nenhum e-mail pôde ser enviado'];
        }
    
        /* 7️⃣ ATUALIZA ALERTA */

        
        /* 🔄 ABRE NOVA CONEXÃO */
        $db = Database::getConnection();
        
        $stmtUpdate = $db->prepare("
            UPDATE alertas
            SET alerta_enviado_compdec = 1,
                data_envio_compdec = :data_envio_compdec
            WHERE id = :id
        ");
        
        $stmtUpdate->execute([
            ':data_envio_compdec' => TimeHelper::now(),
            ':id' => $alertaId
        ]);
        
        /* 🔄 GARANTE CONEXÃO ATIVA PARA HISTÓRICO */
        $db = null;

        /* 8️⃣ HISTÓRICO */
        HistoricoService::registrar(
            $usuario['id'],
            $usuario['nome'],
            'ENVIAR_ALERTA',
            'Enviou alerta para COMPDEC',
            "Alerta nº {$alerta['numero']} | E-mails enviados: {$enviados}"
        );
    
        return [
            'ok' => true,
            'mensagem' => "Lote enviado ({$enviados} e-mails)",
            'proximo_offset' => $temMais ? $proximoOffset : null,
            'finalizado' => !$temMais
        ];

    }


    /**
     * Busca COMPDEC com email válido a partir da planilha Google
     */
    private static function buscarCompdecsValidas(): array
    {
        $url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQK16_yCETLrGVH3EfM9yMrwirNW2ZkX6t1nwTfPaKzjQppBbKok1J9zEob9c65xJlWC44Qs4QeeLCU/pub?gid=0&single=true&output=csv';

        $csv = @file_get_contents($url);
        if ($csv === false) {
            return [];
        }

        $linhas = array_map(
            fn($linha) => str_getcsv($linha, ',', '"', '\\'),
            explode("\n", $csv)
        );

        if (count($linhas) < 2) {
            return [];
        }

        // Cabeçalhos
        $cabecalho = array_map('trim', $linhas[0]);

        $dados = [];

        foreach (array_slice($linhas, 1) as $linha) {

            if (count($linha) !== count($cabecalho)) {
                continue;
            }

            $row = array_combine($cabecalho, $linha);

            $temCompdec = strtoupper(trim($row['tem_compdec'] ?? ''));
            $email      = trim($row['email'] ?? '');

            if ($temCompdec !== 'SIM') {
                continue;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $dados[] = $row;
        }

        return $dados;
    }
    
       /* =====================================================
                 MONTA CONTEÚDO DO E-MAIL DO ALERTA
    ===================================================== */
    private static function montarEmailAlerta(array $alerta): array
    {
    $numero   = $alerta['numero'];
    $evento   = $alerta['tipo_evento'];
    $gravidade = $alerta['nivel_gravidade'];
    $fonte    = $alerta['fonte'] ?? '—';

    $dataAlerta = !empty($alerta['data_alerta'])
        ? date('d/m/Y', strtotime($alerta['data_alerta']))
        : '—';

    $inicio = !empty($alerta['inicio_alerta'])
        ? date('d/m/Y H:i', strtotime($alerta['inicio_alerta']))
        : '—';

    $fim = !empty($alerta['fim_alerta'])
        ? date('d/m/Y H:i', strtotime($alerta['fim_alerta']))
        : '—';

    /* ASSUNTO */
    $assunto = "ALERTA DEFESA CIVIL – Nº {$numero} – {$gravidade}";

    /* TEXTO SIMPLES */
    $texto = <<<TXT
ALERTA DA DEFESA CIVIL DO ESTADO DO PARÁ

Número do Alerta: {$numero}
Evento: {$evento}
Gravidade: {$gravidade}
Fonte: {$fonte}

Data do Alerta: {$dataAlerta}
Vigência: {$inicio} até {$fim}

Este e-mail é um comunicado oficial da Defesa Civil do Estado do Pará.
O documento completo do alerta segue em anexo (PDF).

Não responda este e-mail.
TXT;

    /* HTML */
    $html = <<<HTML
<h2 style="color:#0b3c68">🚨 Alerta da Defesa Civil do Estado do Pará</h2>

<p><strong>Número do Alerta:</strong> {$numero}</p>
<p><strong>Evento:</strong> {$evento}</p>
<p><strong>Gravidade:</strong> {$gravidade}</p>
<p><strong>Fonte:</strong> {$fonte}</p>

<hr>

<p><strong>Data do Alerta:</strong> {$dataAlerta}<br>
<strong>Vigência:</strong> {$inicio} até {$fim}</p>

<p>
Este e-mail é um <strong>comunicado oficial</strong> da
Defesa Civil do Estado do Pará.
</p>

<p>
📎 O <strong>PDF do alerta</strong> segue em anexo com todas as informações oficiais.
</p>

<p style="font-size:12px;color:#555">
Não responda este e-mail.
</p>
HTML;

    return [
        'assunto' => $assunto,
        'texto'   => $texto,
        'html'    => $html
    ];
}

    
    
}
