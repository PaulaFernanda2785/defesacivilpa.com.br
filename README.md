# Sistema Inteligente Multirriscos - Defesa Civil do Estado do Para

Plataforma web corporativa para monitoramento, gestao, analise e divulgacao de alertas multirriscos com base territorial no Estado do Para.

## O que e o sistema

O Sistema Inteligente Multirriscos centraliza o ciclo operacional de alertas em uma unica aplicacao, permitindo:

- cadastrar e importar alertas oficiais (ex.: INMET);
- mapear municipios e regioes afetadas por area geografica;
- acompanhar vigencia, severidade, tipologia e historico dos alertas;
- gerar documentos oficiais e exportacoes (PDF, CSV e KML);
- apoiar decisao estrategica, tatica e operacional com indicadores.

## Problematica

A operacao de monitoramento de riscos exige leitura rapida de dados de fontes diferentes, com impacto direto na tomada de decisao. Sem uma plataforma integrada, surgem gargalos como:

- informacoes dispersas em sistemas, planilhas e fluxos manuais;
- dificuldade para cruzar alerta, territorio afetado e gravidade em tempo util;
- baixa padronizacao na emissao de relatorios e comunicacoes;
- menor rastreabilidade das acoes executadas por usuarios.

## Justificativa

O projeto foi desenvolvido para aumentar a capacidade de resposta da Defesa Civil do Estado do Para por meio de uma base unica e auditavel de informacoes de risco. A solucao busca:

- reduzir tempo entre monitoramento, analise e acao;
- melhorar confiabilidade e consistencia dos dados operacionais;
- fortalecer governanca, seguranca e rastreabilidade;
- padronizar comunicacao tecnica e suporte a decisoes criticas.

## Como foi desenvolvido

O sistema foi construido em arquitetura modular, com separacao entre apresentacao, regras de negocio, APIs e persistencia.

Etapas e diretrizes principais de desenvolvimento:

1. Definicao de escopo funcional para operacao de alertas, mapa, analises e historico.
2. Estruturacao da aplicacao em camadas (`Core`, `Helpers`, `Services`, `pages`, `api`).
3. Implementacao de fluxos operacionais (cadastro/importacao, territorializacao, envio e exportacoes).
4. Aplicacao de controles de seguranca (sessao, CSRF, RBAC, headers e validacoes).
5. Consolidacao de modulos analiticos e otimizacoes SQL para ganho de desempenho.

## Principais funcionalidades

- Autenticacao e controle de acesso por perfil (`ADMIN`, `GESTOR`, `ANALISTA`, `OPERACOES`).
- Gestao completa de alertas (cadastro, edicao, encerramento, cancelamento).
- Importacao de alertas oficiais por URL do INMET.
- Territorializacao automatica por municipios e regioes de integracao.
- Mapa multirriscos com filtros, KPIs, ranking e linha do tempo.
- Analises temporal, severidade, tipologia e indices (IRP/IPT).
- Auditoria de acoes de usuario com relatorio em PDF.
- Gestao de usuarios e perfis administrativos.

## Arquitetura e stack

- Backend: PHP (modular por scripts e servicos).
- Banco de dados: MariaDB/MySQL (PDO).
- Frontend: HTML, CSS e JavaScript.
- Mapas: Leaflet + OpenStreetMap.
- PDF: Dompdf.
- E-mail: PHPMailer (SMTP).
- Integracoes operacionais:
  - INMET (importacao de alertas oficiais).
  - Google Sheets (catalogo COMPDEC via CSV publicado).

## Estrutura resumida do repositorio

```text
.
|-- docs/                # Documentacao tecnica e evolucao
|-- infra/               # Arquivos de infraestrutura local
|-- public_html/         # Aplicacao web (codigo publicado)
|   |-- api/             # Endpoints JSON
|   |-- app/             # Core, Helpers, Services e bibliotecas
|   |-- assets/          # CSS, JS, imagens e vendor front-end
|   |-- pages/           # Telas e fluxos por modulo
|   |-- banco_db/        # Dump SQL de referencia
|   |-- storage/         # Artefatos de runtime
|   `-- uploads/         # Arquivos enviados por usuarios
`-- storage/             # Scripts e apoio operacional fora da web root
```

## Como executar localmente (resumo)

### 1) Pre-requisitos

- Apache + PHP em ambiente local (ex.: WAMP).
- MariaDB/MySQL.

### 2) Configurar ambiente

1. Copie o arquivo de exemplo:
   ```powershell
   Copy-Item .env.example .env
   ```
2. Ajuste variaveis de banco e SMTP no `.env`.
3. Configure o virtual host local (arquivos em `infra/wamp/`), se necessario.

### 3) Importar banco

- Use o dump de referencia em:
  - `public_html/banco_db/u696029111_DefesaCivilPA.sql`
- Ou execute o script local:
  - `storage/database/setup_local_database.ps1`

### 4) Permissoes de escrita

Garanta escrita para:

- `public_html/uploads/`
- `public_html/storage/`

### 5) Acesso

- URL local sugerida: `http://multirriscos.defesacivilpa.com.br`

## Documentacao tecnica

A documentacao detalhada esta em `docs/documentos-tecnicos/`, incluindo:

- arquitetura do projeto;
- requisitos funcionais e nao funcionais;
- controle de acesso;
- modelagem e dicionario de dados;
- manual de usuario.

## Status do projeto

- Plataforma operacional em evolucao continua.
- Referencia tecnica documentada com base em atualizacoes ate 2026-03-28.

## Licenca

Este repositorio utiliza a licenca MIT. Consulte o arquivo `LICENSE`.

