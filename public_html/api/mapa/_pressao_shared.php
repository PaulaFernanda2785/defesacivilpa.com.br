<?php

function normalizarDataFiltroMapa(?string $valor): ?DateTimeImmutable
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    if (!($data instanceof DateTimeImmutable)) {
        return null;
    }

    return $data->format('Y-m-d') === $valor ? $data : null;
}

function pesoGravidadeMapa(?string $nivel): int
{
    return match (strtoupper(trim((string) $nivel))) {
        'BAIXO' => 1,
        'MODERADO' => 2,
        'ALTO' => 3,
        'MUITO ALTO' => 4,
        'EXTREMO' => 5,
        default => 0,
    };
}

function casoPesoGravidadeSql(string $coluna = 'a.nivel_gravidade'): string
{
    return "CASE
        WHEN {$coluna} = 'BAIXO' THEN 1
        WHEN {$coluna} = 'MODERADO' THEN 2
        WHEN {$coluna} = 'ALTO' THEN 3
        WHEN {$coluna} = 'MUITO ALTO' THEN 4
        WHEN {$coluna} = 'EXTREMO' THEN 5
        ELSE 0
    END";
}

function montarFiltroMapaPressao(
    array $entrada,
    string $aliasAlerta = 'a',
    string $aliasMunicipio = 'am',
    string $aliasRegiao = 'mr'
): array {
    $where = ["{$aliasAlerta}.status = 'ATIVO'"];
    $params = [];

    $dataInicio = normalizarDataFiltroMapa((string) ($entrada['data_inicio'] ?? ''));
    if ($dataInicio instanceof DateTimeImmutable) {
        $where[] = "{$aliasAlerta}.data_alerta >= :data_inicio";
        $params[':data_inicio'] = $dataInicio->format('Y-m-d');
    }

    $dataFim = normalizarDataFiltroMapa((string) ($entrada['data_fim'] ?? ''));
    if ($dataFim instanceof DateTimeImmutable) {
        $where[] = "{$aliasAlerta}.data_alerta < :data_fim_exclusiva";
        $params[':data_fim_exclusiva'] = $dataFim->modify('+1 day')->format('Y-m-d');
    }

    $gravidade = trim((string) ($entrada['gravidade'] ?? ''));
    if ($gravidade !== '') {
        $where[] = "{$aliasAlerta}.nivel_gravidade = :gravidade";
        $params[':gravidade'] = $gravidade;
    }

    $fonte = trim((string) ($entrada['fonte'] ?? ''));
    if ($fonte !== '') {
        $where[] = "{$aliasAlerta}.fonte = :fonte";
        $params[':fonte'] = $fonte;
    }

    $tipoEvento = trim((string) ($entrada['tipo_evento'] ?? ''));
    if ($tipoEvento !== '') {
        $where[] = "{$aliasAlerta}.tipo_evento = :tipo_evento";
        $params[':tipo_evento'] = $tipoEvento;
    }

    $regiao = trim((string) ($entrada['regiao'] ?? ''));
    if ($regiao !== '') {
        $where[] = "{$aliasRegiao}.regiao_integracao = :regiao";
        $params[':regiao'] = $regiao;
    }

    $municipio = trim((string) ($entrada['municipio'] ?? ''));
    if ($municipio !== '') {
        if (preg_match('/^\d{7}$/', $municipio)) {
            $where[] = "{$aliasMunicipio}.municipio_codigo = :municipio_codigo";
            $params[':municipio_codigo'] = $municipio;
        } else {
            $where[] = "{$aliasMunicipio}.municipio_nome = :municipio_nome";
            $params[':municipio_nome'] = $municipio;
        }
    }

    return [$where, $params];
}
