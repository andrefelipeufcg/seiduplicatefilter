<?php

/**
 * Plugin SEI Duplicate Filter para GLPI 11
 *
 * Intercepta a criação de chamados oriundos do MailCollector para evitar
 * duplicidade de tickets gerados pelo Sistema Eletrônico de Informações (SEI).
 *
 * @category Plugin
 * @package  GlpiPlugin\Seiduplicatefilter
 * @author   andrefelipeufcg
 * @license  GPLv3+
 */

use Glpi\Plugin\Hooks;

define('PLUGIN_SEIDUPLICATEFILTER_VERSION', '1.1.0');
define('PLUGIN_SEIDUPLICATEFILTER_MIN_GLPI', '11.0.0');
define('PLUGIN_SEIDUPLICATEFILTER_MAX_GLPI', '12.0.99');

/**
 * Inicialização do plugin — registra hooks e classes.
 */
function plugin_init_seiduplicatefilter(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['seiduplicatefilter'] = true;

    // Carrega o arquivo de hooks (install, uninstall, callback).
    include_once(__DIR__ . '/hook.php');

    /**
     * Hook PRE_ITEM_ADD para Ticket.
     *
     * Este é o ponto central de interceptação: o GLPI dispara este hook
     * ANTES de gravar o Ticket no banco. Se o callback definir
     * $item->input = false, a criação é cancelada silenciosamente.
     *
     * @see \Glpi\Plugin\Hooks::PRE_ITEM_ADD
     * @see CommonDBTM::prepareInputForAdd()
     */
    $PLUGIN_HOOKS[Hooks::PRE_ITEM_ADD]['seiduplicatefilter'] = [
        'Ticket' => 'plugin_seiduplicatefilter_pre_item_add',
    ];

    // Página de configuração acessível em Configurar > Plugins.
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['seiduplicatefilter'] = 'front/config.form.php';

}

/**
 * Metadados do plugin exibidos na tela Configurar > Plugins.
 */
function plugin_version_seiduplicatefilter(): array
{
    return [
        'name'         => __('SEI Duplicate Filter', 'seiduplicatefilter'),
        'version'      => PLUGIN_SEIDUPLICATEFILTER_VERSION,
        'author'       => 'andrefelipeufcg',
        'license'      => 'GPLv3+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_SEIDUPLICATEFILTER_MIN_GLPI,
                'max' => PLUGIN_SEIDUPLICATEFILTER_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.2',
            ],
        ],
    ];
}

/**
 * Verifica se a versão do GLPI está dentro dos limites suportados.
 */
function plugin_seiduplicatefilter_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_SEIDUPLICATEFILTER_MIN_GLPI, '<')) {
        echo 'Este plugin requer GLPI >= ' . PLUGIN_SEIDUPLICATEFILTER_MIN_GLPI;
        return false;
    }
    if (version_compare(GLPI_VERSION, PLUGIN_SEIDUPLICATEFILTER_MAX_GLPI, '>')) {
        echo 'Este plugin requer GLPI <= ' . PLUGIN_SEIDUPLICATEFILTER_MAX_GLPI;
        return false;
    }
    return true;
}

/**
 * Verifica configuração — sempre true (sem pré-requisitos de config).
 */
function plugin_seiduplicatefilter_check_config(): bool
{
    return true;
}
