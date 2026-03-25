<?php

class PaginationHelper
{
    public const MAX_NUMEROS_PADRAO = 5;

    public static function marcadoresCompactos(
        int $paginaAtual,
        int $totalPaginas,
        int $maxNumeros = self::MAX_NUMEROS_PADRAO
    ): array {
        $totalPaginas = max(1, $totalPaginas);
        $paginaAtual = min(max(1, $paginaAtual), $totalPaginas);
        $maxNumeros = max(3, $maxNumeros);

        if ($totalPaginas <= $maxNumeros) {
            return range(1, $totalPaginas);
        }

        $alvo = min($maxNumeros, $totalPaginas);
        $marcadores = [1, $totalPaginas, $paginaAtual];
        $passo = 1;

        while (count(array_unique($marcadores)) < $alvo) {
            $adicionou = false;
            $anterior = $paginaAtual - $passo;
            $proxima = $paginaAtual + $passo;

            if ($anterior > 1) {
                $marcadores[] = $anterior;
                $adicionou = true;
            }

            if (count(array_unique($marcadores)) >= $alvo) {
                break;
            }

            if ($proxima < $totalPaginas) {
                $marcadores[] = $proxima;
                $adicionou = true;
            }

            if (!$adicionou) {
                break;
            }

            $passo++;
        }

        $marcadores = array_values(array_unique(array_filter(
            $marcadores,
            static fn (int $item): bool => $item >= 1 && $item <= $totalPaginas
        )));
        sort($marcadores);

        $itens = [];
        $anterior = null;

        foreach ($marcadores as $marcador) {
            if ($anterior !== null && $marcador - $anterior > 1) {
                $itens[] = '...';
            }

            $itens[] = $marcador;
            $anterior = $marcador;
        }

        return $itens;
    }
}
