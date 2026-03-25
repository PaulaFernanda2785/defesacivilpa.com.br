<?php
require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Helpers/TimeHelper.php';

class AlertaService
{
    
    /**
     * Gera numeração automática por ano,
     * baseada na ordem cronológica da data do alerta.
     *
     * Exemplo: 001/2026
     */
    public static function gerarNumero(string $dataAlerta): string
{
    $db = Database::getConnection();

    $ano = date('Y', strtotime($dataAlerta));

    $stmt = $db->prepare("
        SELECT MAX(
            CAST(SUBSTRING_INDEX(numero, '/', 1) AS UNSIGNED)
        )
        FROM alertas
        WHERE numero LIKE ?
    ");

    $stmt->execute(['%/' . $ano]);

    $ultimoNumero = (int) $stmt->fetchColumn();
    $novoNumero   = $ultimoNumero + 1;

    return str_pad($novoNumero, 3, '0', STR_PAD_LEFT) . '/' . $ano;
}


    /**
     * Salva o alerta no banco
     * Retorna TRUE em sucesso / FALSE em falha
     */
    public static function salvar(array $dados): bool
    {
        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            /* ===============================
               PROCESSA IMAGEM DO MAPA
            =============================== */
            $imagemPath = null;

            if (!empty($dados['imagem_mapa'])) {

                $base64 = preg_replace(
                    '#^data:image/\w+;base64,#i',
                    '',
                    $dados['imagem_mapa']
                );

                $base64 = str_replace(' ', '+', $base64);

                $arquivo   = 'mapa_alerta_' . time() . '.png';
                $diretorio = __DIR__ . '/../../storage/geo/';

                if (!is_dir($diretorio)) {
                    mkdir($diretorio, 0755, true);
                }

                if (file_put_contents($diretorio . $arquivo, base64_decode($base64)) === false) {
                    throw new Exception('Falha ao salvar imagem do mapa.');
                }

                $imagemPath = '/storage/geo/' . $arquivo;
            }

            /* ===============================
               INSERÇÃO NO BANCO
            =============================== */
            $sql = "
                INSERT INTO alertas (
                    numero,
                    fonte,
                    tipo_evento,
                    nivel_gravidade,
                    data_alerta,
                    inicio_alerta,
                    fim_alerta,
                    riscos,
                    recomendacoes,
                    area_geojson,
                    imagem_mapa,
                    status,
                    criado_em
                ) VALUES (
                    :numero,
                    :fonte,
                    :tipo_evento,
                    :nivel_gravidade,
                    :data_alerta,
                    :inicio_alerta,
                    :fim_alerta,
                    :riscos,
                    :recomendacoes,
                    :area_geojson,
                    :imagem_mapa,
                    'ATIVO',
                    :criado_em
                )
            ";

            $stmt = $db->prepare($sql);

            $stmt->execute([
                ':numero'          => self::gerarNumero($dados['data_alerta']),
                ':fonte'           => $dados['fonte'] ?? 'INMET',
                ':tipo_evento'     => $dados['tipo_evento'],
                ':nivel_gravidade' => $dados['nivel_gravidade'],
                ':data_alerta'     => $dados['data_alerta'],
                ':inicio_alerta'   => $dados['inicio_alerta'],
                ':fim_alerta'      => $dados['fim_alerta'],
                ':riscos'          => $dados['riscos'],
                ':recomendacoes'   => $dados['recomendacoes'],
                ':area_geojson'    => $dados['area_geojson'],
                ':imagem_mapa'     => $imagemPath,
                ':criado_em'       => TimeHelper::now()
            ]);

            $db->commit();
            return true;

        } catch (Throwable $e) {

            $db->rollBack();

            // Em produção:
            // error_log($e->getMessage());

            return false;
        }
    }
}
