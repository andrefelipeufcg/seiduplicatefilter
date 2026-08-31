<?php

/**
 * Classe de configuração do plugin SEI Duplicate Filter.
 *
 * Gerencia a tabela `glpi_plugin_seiduplicatefilter_configs` com os campos:
 * - sender_email: e-mail de origem a ser filtrado.
 * - is_active: flag de ativação/desativação do filtro.
 *
 * @category Plugin
 * @package  GlpiPlugin\Seiduplicatefilter
 * @author   andrefelipeufcg
 * @license  GPLv3+
 */

namespace GlpiPlugin\Seiduplicatefilter;

use CommonDBTM;
use Session;
use Html;

class Config extends CommonDBTM
{
    /**
     * Tabela do banco de dados associada.
     */
    public static $table = 'glpi_plugin_seiduplicatefilter_configs';

    /**
     * Direito necessário para acessar a configuração.
     */
    public static $rightname = 'config';

    /**
     * Exibe o nome na aba de configuração.
     */
    public function getTabNameForItem(\CommonGLPI $item, $withtemplate = 0): string
    {
        return __('SEI Duplicate Filter', 'seiduplicatefilter');
    }

    /**
     * Renderiza o conteúdo da aba de configuração.
     */
    public static function displayTabContentForItem(
        \CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ): bool {
        $config = new self();
        $config->showConfigForm();
        return true;
    }

    /**
     * Exibe o formulário de configuração do plugin.
     */
    public function showConfigForm(): void
    {
        if (!$this->getFromDB(1)) {
            return;
        }

        echo '<form method="post" action="' . static::getFormURL() . '">';
        echo '<input type="hidden" name="id" value="1">';
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . Session::getNewCSRFToken() . '">';

        echo '<div class="card mx-auto mt-3" style="max-width: 700px;">';
        echo '<div class="card-header"><h3 class="card-title">';
        echo __('Configuração: Filtro de Chamados Duplicados do SEI no GLPI', 'seiduplicatefilter');
        echo '</h3></div>';
        echo '<div class="card-body">';

        // Campo: E-mail de origem.
        echo '<div class="mb-3 row">';
        echo '<label class="col-sm-4 col-form-label">';
        echo __('E-mail de origem (SEI)', 'seiduplicatefilter');
        echo '</label>';
        echo '<div class="col-sm-8">';
        echo '<input type="email" class="form-control" name="sender_email" value="';
        echo htmlspecialchars($this->fields['sender_email'] ?? 'no-reply@ufcg.edu.br');
        echo '" required>';
        echo '<small class="form-text text-muted">';
        echo __('Apenas chamados oriundos deste endereço serão analisados.', 'seiduplicatefilter');
        echo '</small>';
        echo '</div></div>';

        // Campo: Padrão do Assunto.
        echo '<div class="mb-3 row">';
        echo '<label class="col-sm-4 col-form-label">';
        echo __('Padrão do Assunto (SEI)', 'seiduplicatefilter');
        echo '</label>';
        echo '<div class="col-sm-8">';
        echo '<input type="text" class="form-control" name="subject_pattern" value="';
        echo htmlspecialchars($this->fields['subject_pattern'] ?? 'SEI - Processo n[NUMERO_DO_PROCESSO]enviado para esta Unidade');
        echo '" required>';
        echo '<small class="form-text text-muted">';
        echo __('Use a tag <strong>[NUMERO_DO_PROCESSO]</strong> onde o número deve aparecer.', 'seiduplicatefilter');
        echo '</small>';
        echo '</div></div>';

        // Campo: Plugin ativo.
        echo '<div class="mb-3 row">';
        echo '<label class="col-sm-4 col-form-label">';
        echo __('Filtro ativo', 'seiduplicatefilter');
        echo '</label>';
        echo '<div class="col-sm-8">';
        $isActive = (int) ($this->fields['is_active'] ?? 1);
        echo '<div class="form-check form-switch mt-2">';
        echo '<input class="form-check-input" type="checkbox" name="is_active" value="1"';
        echo $isActive ? ' checked' : '';
        echo '>';
        echo '</div>';
        echo '</div></div>';

        echo '</div>'; // card-body

        echo '<div class="card-footer text-end">';
        echo '<button type="submit" name="update" class="btn btn-primary">';
        echo __('Salvar', 'seiduplicatefilter');
        echo '</button>';
        echo '</div>';

        echo '</div>'; // card
        echo '</form>';
    }

    /**
     * Processa a atualização do formulário de configuração.
     *
     * @param array $input Dados do formulário.
     * @return array|false Dados sanitizados ou false em caso de erro.
     */
    public function prepareInputForUpdate($input): array|false
    {
        // Garante que is_active seja 0 se o checkbox não for enviado.
        $input['is_active'] = isset($input['is_active']) ? 1 : 0;

        // Valida o formato do e-mail.
        if (!empty($input['sender_email']) && !filter_var($input['sender_email'], FILTER_VALIDATE_EMAIL)) {
            Session::addMessageAfterRedirect(
                __('Endereço de e-mail inválido.', 'seiduplicatefilter'),
                false,
                ERROR
            );
            return false;
        }

        // Sanitiza o padrão do assunto.
        if (isset($input['subject_pattern'])) {
            $input['subject_pattern'] = trim($input['subject_pattern']);
            if (empty($input['subject_pattern'])) {
                $input['subject_pattern'] = 'SEI - Processo n[NUMERO_DO_PROCESSO]enviado para esta Unidade';
            }
        }

        return $input;
    }
}
