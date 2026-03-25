<?php
/**
 * Espera:
 * $breadcrumb = [
 *   'Painel' => '/pages/painel.php',
 *   'Alertas' => '/pages/alertas/listar.php',
 *   'Cadastrar' => null
 * ];
 */

if (!isset($breadcrumb) || !is_array($breadcrumb)) {
    return;
}

$tituloPagina = array_key_last($breadcrumb);
?>

<div class="page-header">

    <h1 class="page-title">
        <?= htmlspecialchars($tituloPagina) ?>
    </h1>

    <div class="breadcrumb">
        <?php
        $i = 0;
        $total = count($breadcrumb);

        foreach ($breadcrumb as $label => $link):
            $i++;
        ?>
            <?php if ($link && $i < $total): ?>
                <a href="<?= $link ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
                <span>/</span>
            <?php else: ?>
                <span class="current">
                    <?= htmlspecialchars($label) ?>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

</div>
