<?php

/**
 * Hooks de instalação, desinstalação e lógica de negócio do plugin.
 *
 * @category Plugin
 * @package  GlpiPlugin\Seiduplicatefilter
 * @author   andrefelipeufcg
 * @license  GPLv3+
 */

use Glpi\Toolbox\Sanitizer;

// ─────────────────────────────────────────────────────────────────────────────
// Instalação / Desinstalação
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Cria a tabela de log de processos SEI filtrados.
 */
function plugin_seiduplicatefilter_install(): bool
{
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $migration         = new Migration(PLUGIN_SEIDUPLICATEFILTER_VERSION);

    // Tabela de configuração do plugin.
    if (!$DB->tableExists('glpi_plugin_seiduplicatefilter_configs')) {
        $query = "CREATE TABLE `glpi_plugin_seiduplicatefilter_configs` (
            `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `sender_email`   VARCHAR(255) NOT NULL DEFAULT 'suporte@ufcg.edu.br',
            `subject_pattern` VARCHAR(255) NOT NULL DEFAULT 'SEI - Processo nº [NUMERO_DO_PROCESSO] enviado para esta Unidade',
            `is_active`      TINYINT NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB
        DEFAULT CHARSET={$default_charset}
        COLLATE={$default_collation}";
        $DB->doQuery($query);

        $DB->insert('glpi_plugin_seiduplicatefilter_configs', [
            'id'              => 1,
            'sender_email'    => 'suporte@ufcg.edu.br',
            'subject_pattern' => 'SEI - Processo nº [NUMERO_DO_PROCESSO] enviado para esta Unidade',
            'is_active'       => 1,
        ]);
    }

    // Tabela de log — armazena processos filtrados para auditoria.
    if (!$DB->tableExists('glpi_plugin_seiduplicatefilter_logs')) {
        $query = "CREATE TABLE `glpi_plugin_seiduplicatefilter_logs` (
            `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `process_number`    VARCHAR(50)  NOT NULL,
            `existing_ticket_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `blocked_title`     TEXT DEFAULT NULL,
            `date_blocked`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `process_number` (`process_number`)
        ) ENGINE=InnoDB
        DEFAULT CHARSET={$default_charset}
        COLLATE={$default_collation}";
        $DB->doQuery($query);
    }

    $migration->executeMigration();
    return true;
}

/**
 * Remove as tabelas criadas pelo plugin.
 */
function plugin_seiduplicatefilter_uninstall(): bool
{
    global $DB;

    foreach (['glpi_plugin_seiduplicatefilter_configs', 'glpi_plugin_seiduplicatefilter_logs'] as $table) {
        if ($DB->tableExists($table)) {
            $DB->dropTable($table);
        }
    }
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// Funções auxiliares
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Retorna o e-mail do remetente configurado (padrão: suporte@ufcg.edu.br).
 */
function plugin_seiduplicatefilter_get_sender_email(): string
{
    $config = new \GlpiPlugin\Seiduplicatefilter\Config();
    if ($config->getFromDB(1)) {
        return trim($config->fields['sender_email'] ?? 'suporte@ufcg.edu.br');
    }
    return 'suporte@ufcg.edu.br';
}

/**
 * Retorna o padrão do assunto configurado.
 */
function plugin_seiduplicatefilter_get_subject_pattern(): string
{
    $config = new \GlpiPlugin\Seiduplicatefilter\Config();
    if ($config->getFromDB(1)) {
        return trim($config->fields['subject_pattern'] ?? 'SEI - Processo nº [NUMERO_DO_PROCESSO] enviado para esta Unidade');
    }
    return 'SEI - Processo nº [NUMERO_DO_PROCESSO] enviado para esta Unidade';
}

/**
 * Verifica se o plugin está ativo na configuração.
 */
function plugin_seiduplicatefilter_is_active(): bool
{
    $config = new \GlpiPlugin\Seiduplicatefilter\Config();
    if ($config->getFromDB(1)) {
        return (int) ($config->fields['is_active'] ?? 1) === 1;
    }
    return true;
}

/**
 * Extrai o número de processo SEI de uma string (título ou corpo).
 *
 * Padrão esperado: "SEI - Processo nº XXXXX.XXXXXX/XXXX-XX"
 * Exemplo capturado: 23096.058513/2026-31
 *
 * @param string $text Texto a ser analisado (título ou corpo do e-mail).
 * @return string|null O número do processo capturado ou null se não encontrado.
 */
function plugin_seiduplicatefilter_extract_process_number(string $text): ?string
{
    // Remove tags HTML e decodifica entidades para análise limpa.
    $clean = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

    /**
     * Monta o Regex com base no padrão configurado pelo usuário.
     * Substitui a tag [NUMERO_DO_PROCESSO] pelo regex que captura o formato.
     */
    $subjectPattern = plugin_seiduplicatefilter_get_subject_pattern();
    
    // Se o usuário limpou o campo, usa um padrão genérico
    if (empty($subjectPattern)) {
        $subjectPattern = '[NUMERO_DO_PROCESSO]';
    }

    // Escapa o texto configurado para evitar quebras no Regex
    $regexStr = preg_quote($subjectPattern, '/');
    
    // Substitui a tag escaped pela captura do formato numérico do processo
    $regexStr = str_replace('\[NUMERO_DO_PROCESSO\]', '(\d{5}\.\d{6}\/\d{4}-\d{2})', $regexStr);

    $pattern = '/' . $regexStr . '/iu';

    if (preg_match($pattern, $clean, $matches)) {
        return $matches[1];
    }

    // Fallback: captura o padrão numérico mesmo sem o prefixo
    // caso o formato do e-mail sofra variações não previstas.
    $fallbackPattern = '/(\d{5}\.\d{6}\/\d{4}-\d{2})/';
    if (preg_match($fallbackPattern, $clean, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Consulta o banco de dados para verificar se já existe um chamado aberto
 * contendo o número de processo SEI informado.
 *
 * Chamados "abertos" são aqueles com status diferente de:
 *   - CLOSED (6)
 *
 * A busca é feita no título (name) e no conteúdo (content) do chamado.
 *
 * @param string $processNumber Número do processo SEI (ex: 23096.058513/2026-31).
 * @return int|null ID do chamado existente ou null se nenhum encontrado.
 */
function plugin_seiduplicatefilter_find_existing_ticket(string $processNumber): ?int
{
    global $DB;

    // Usa o método seguro $DB->request() para evitar SQL Injection.
    // O GLPI abstrai o escape de parâmetros internamente.
    $iterator = $DB->request([
        'SELECT' => ['id'],
        'FROM'   => 'glpi_tickets',
        'WHERE'  => [
            'is_deleted' => 0,
            'NOT'        => ['status' => \CommonITILObject::CLOSED],
            'OR'         => [
                ['name'    => ['LIKE', '%' . $processNumber . '%']],
                ['content' => ['LIKE', '%' . $processNumber . '%']],
            ],
        ],
        'LIMIT'  => 1,
    ]);

    if (count($iterator) > 0) {
        $row = $iterator->current();
        return (int) $row['id'];
    }

    return null;
}

/**
 * Registra no log do GLPI e na tabela de auditoria que um chamado duplicado
 * foi bloqueado.
 */
function plugin_seiduplicatefilter_log_blocked(
    string $processNumber,
    int $existingTicketId,
    string $blockedTitle
): void {
    global $DB;

    // Log no sistema de arquivos do GLPI (files/_log/seiduplicatefilter.log).
    Toolbox::logInFile(
        'seiduplicatefilter',
        sprintf(
            'Chamado duplicado BLOQUEADO — Processo SEI: %s | Ticket existente: #%d | Título rejeitado: %s',
            $processNumber,
            $existingTicketId,
            $blockedTitle
        )
    );

    // Persiste na tabela de auditoria para consulta futura.
    $DB->insert('glpi_plugin_seiduplicatefilter_logs', [
        'process_number'     => $processNumber,
        'existing_ticket_id' => $existingTicketId,
        'blocked_title'      => $blockedTitle,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Callback principal — PRE_ITEM_ADD para Ticket
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Intercepta a criação de um Ticket ANTES da gravação no banco de dados.
 *
 * Fluxo:
 * 1. Verifica se o plugin está ativo.
 * 2. Verifica se o chamado vem do MailCollector (campo _mailgate presente).
 * 3. Verifica se o e-mail de origem é o configurado (padrão: suporte@ufcg.edu.br).
 * 4. Extrai o número de processo SEI do título ou corpo.
 * 5. Consulta o banco para verificar duplicidade.
 * 6. Cancela a criação definindo $item->input = false se duplicado.
 *
 * @param CommonDBTM $item Instância do Ticket em processo de criação.
 */
function plugin_seiduplicatefilter_pre_item_add(CommonDBTM $item): void
{
    // Atua exclusivamente sobre Tickets.
    if (!($item instanceof Ticket)) {
        return;
    }

    // 1. Verifica se o plugin está ativo.
    if (!plugin_seiduplicatefilter_is_active()) {
        return;
    }

    $input = $item->input;

    // 2. Verifica se o chamado é proveniente do MailCollector.
    //    O campo '_mailgate' é preenchido pelo GLPI quando o ticket
    //    é criado a partir de um e-mail coletado pelo MailCollector.
    if (!isset($input['_mailgate']) || (int) $input['_mailgate'] <= 0) {
        // Chamado criado manualmente — não interceptar.
        return;
    }

    // 3. Verifica o e-mail de origem.
    //    O campo '_head[from]' ou '_users_id_requester_notif' pode conter o remetente.
    //    Em algumas versões o GLPI armazena no campo '_head' como array.
    $senderEmail = '';

    // Tenta obter o e-mail do remetente de diferentes locais do input.
    if (isset($input['_head']['from'])) {
        $senderEmail = $input['_head']['from'];
    } elseif (isset($input['_sender'])) {
        $senderEmail = $input['_sender'];
    } elseif (isset($input['_from'])) {
        $senderEmail = $input['_from'];
    }

    // Extrai apenas o endereço de e-mail caso venha no formato "Nome <email@domain>".
    if (preg_match('/<([^>]+)>/', $senderEmail, $emailMatch)) {
        $senderEmail = $emailMatch[1];
    }

    $senderEmail = strtolower(trim($senderEmail));
    $configuredEmail = strtolower(plugin_seiduplicatefilter_get_sender_email());

    if ($senderEmail !== $configuredEmail) {
        // E-mail de origem não corresponde ao configurado — não interceptar.
        Toolbox::logInFile(
            'seiduplicatefilter',
            sprintf(
                'E-mail de origem "%s" não corresponde ao configurado "%s" — chamado liberado.',
                $senderEmail,
                $configuredEmail
            )
        );
        return;
    }

    // 4. Extrai o número de processo SEI do título e/ou corpo do e-mail.
    $title   = $input['name']    ?? '';
    $content = $input['content'] ?? '';

    $processNumber = plugin_seiduplicatefilter_extract_process_number($title);

    if ($processNumber === null) {
        // Tenta no corpo do e-mail se não encontrou no título.
        $processNumber = plugin_seiduplicatefilter_extract_process_number($content);
    }

    if ($processNumber === null) {
        // Nenhum número de processo SEI encontrado — permitir criação normal.
        Toolbox::logInFile(
            'seiduplicatefilter',
            sprintf(
                'Nenhum número de processo SEI encontrado no título "%s" — chamado liberado.',
                $title
            )
        );
        return;
    }

    // 5. Consulta o banco para verificar se já existe chamado aberto com este processo.
    $existingTicketId = plugin_seiduplicatefilter_find_existing_ticket($processNumber);

    if ($existingTicketId === null) {
        // Não existe chamado duplicado — permitir criação.
        Toolbox::logInFile(
            'seiduplicatefilter',
            sprintf(
                'Processo SEI %s — nenhum chamado duplicado encontrado. Criação permitida.',
                $processNumber
            )
        );
        return;
    }

    // 6. DUPLICIDADE DETECTADA — bloquear a criação do chamado.
    plugin_seiduplicatefilter_log_blocked($processNumber, $existingTicketId, $title);

    /**
     * Definir $item->input = false cancela silenciosamente a criação do Ticket.
     * O GLPI verifica essa condição em CommonDBTM::prepareInputForAdd()
     * e aborta o processo de INSERT.
     *
     * @see \Glpi\Plugin\Hooks::PRE_ITEM_ADD — documentação do hook.
     */
    $item->input = false;
}
