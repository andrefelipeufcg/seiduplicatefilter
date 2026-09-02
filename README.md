# SEI Duplicate Filter — Plugin GLPI 11

Plugin para GLPI 11 que impede a criação de chamados duplicados originados pelo **Sistema Eletrônico de Informações (SEI)** via MailCollector.

## Funcionalidade

1. **Intercepta** a criação de tickets via hook `pre_item_add` (Ticket).
2. **Verifica** se o e-mail de origem corresponde ao configurado (padrão: `no-reply@sei.gov.br`).
3. **Extrai** o número do processo SEI capturando a numeração que vier logo após o prefixo configurado no título (ex: `SEI - Processo n`).
4. **Consulta** o banco para verificar se já existe um chamado aberto com o mesmo número de processo.
5. **Bloqueia** silenciosamente a criação do chamado duplicado e registra no log.

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
