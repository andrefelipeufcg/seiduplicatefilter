# SEI Duplicate Filter — Plugin GLPI 11/12

Plugin que impede a criação de chamados duplicados originados pelo **Sistema Eletrônico de Informações (SEI)** via MailCollector.

O requisito declarado é GLPI 11 ou superior e PHP 8.2 ou superior. A branch mais atual inclui adaptações para o GLPI 12.

## Funcionalidade

1. **Intercepta** a criação de tickets via hook `pre_item_add` (Ticket).
2. **Verifica** se o e-mail de origem corresponde ao configurado (padrão: `no-reply@sei.gov.br`).
3. **Extrai** um número de processo completo no formato `00000.000000/0000-00` do título ou corpo; se o prefixo configurado não for encontrado, procura o formato diretamente.
4. **Consulta** o banco para verificar se já existe um chamado aberto com o mesmo número de processo.
5. **Bloqueia** a criação do chamado duplicado e registra o bloqueio no log.

## Instalação

1. Copie ou crie um symlink da pasta `seiduplicatefilter` para `<GLPI_ROOT>/plugins/`.
2. Acesse **Configurar > Plugins** no GLPI.
3. Instale e ative o plugin.

## Configuração

Após ativação, acesse **Configurar > Plugins > SEI Duplicate Filter** para:

- Definir o e-mail de origem (padrão: `no-reply@sei.gov.br`).
- Definir o prefixo exato que antecede o número do processo no assunto do e-mail (padrão: `SEI - Processo n`).
- Ativar/desativar o filtro.

## Logs

Os registros de chamados bloqueados são gravados em:
- **Arquivo:** `files/_log/seiduplicatefilter.log`
- **Tabela:** `glpi_plugin_seiduplicatefilter_logs`

Nos códigos do GLPI 11.0.11 e 12.0.2, o cancelamento da criação pelo plugin é tratado pelo coletor como falha de importação: o erro é contabilizado, o e-mail é registrado como não importado e encaminhado para a pasta de recusados. Assim, o chamado duplicado não é criado, mas o bloqueio pode aparecer como erro na coleta.

## Estrutura

```
seiduplicatefilter/
├── setup.php               # Registro do plugin e hooks
├── hook.php                # Lógica de negócio (install, uninstall, callback)
├── front/
│   └── config.form.php     # Front-controller da configuração
├── src/
│   └── Config.php          # Classe de configuração (GlpiPlugin\Seiduplicatefilter)
└── README.md
```

## Licença

GPLv3+
