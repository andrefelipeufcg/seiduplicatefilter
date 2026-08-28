<?php

/**
 * Front-controller da página de configuração do plugin SEI Duplicate Filter.
 *
 * Rota: /plugins/seiduplicatefilter/front/config.form.php
 */

use GlpiPlugin\Seiduplicatefilter\Config;

$inc = __DIR__ . '/../../../inc/includes.php';
if (!file_exists($inc)) {
    $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/inc/includes.php';
}
if (!file_exists($inc)) {
    $inc = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../inc/includes.php';
}
include $inc;

Session::checkRight('config', UPDATE);

$config = new Config();

// Processa POST de atualização.
if (isset($_POST['update'])) {
    $config->check(1, UPDATE);
    if ($config->update($_POST)) {
        Session::addMessageAfterRedirect(__('Configuração salva com sucesso.', 'seiduplicatefilter'), false, INFO);
    } else {
        Session::addMessageAfterRedirect(__('Erro ao salvar configuração.', 'seiduplicatefilter'), false, ERROR);
    }
    Html::back();
}

// Renderiza a página de configuração.
Html::header(
    __('SEI Duplicate Filter', 'seiduplicatefilter'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugins'
);

$config->showConfigForm();

Html::footer();
