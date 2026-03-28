# 🔹Documento Técnico — Manual do Usuário — Resumo🔹

## 1. Objetivo
Guia rápido de operação do **Sistema Inteligente Multirriscos** para consulta diária.

## 2. Perfis e acesso
- `ADMIN`: acesso total, inclusive usuários.
- `GESTOR`: operação completa de alertas, análises e histórico.
- `ANALISTA`: operação de alertas, mapa e análises.
- `OPERACOES`: consulta operacional (painel, mapa, análises e detalhes).

## 3. Fluxo operacional essencial
1. Entrar no sistema pelo login.
2. Consultar o **Painel** para visão inicial.
3. Abrir **Alertas** para cadastrar/importar/atualizar.
4. Validar território no **Mapa Multirriscos**.
5. Usar **Análises** para leitura estratégica.
6. Exportar PDF/CSV/KML conforme necessidade.

## 4. Módulos principais
- **Painel**: visão geral, vigência e distribuição de alertas.
- **Alertas**: cadastro manual, edição, encerramento, envio e exportação.
- **Importação INMET**: prévia + confirmação de aviso oficial.
- **Mapa Multirriscos**: filtros territoriais e ranking de pressão.
- **Análises**: temporal, severidade, tipologia e índices.
- **Histórico**: auditoria de ações de usuários (ADMIN/GESTOR).
- **Usuários**: gestão de contas e perfis (ADMIN).

## 5. Exportações
- PDF do alerta.
- CSV da listagem de alertas.
- KML de área do alerta.
- PDF analítico.
- PDF de histórico.

## 6. Boas práticas
- Confirmar vigência antes de envio oficial.
- Revisar área no mapa antes de concluir alerta.
- Usar filtros por período para análises comparáveis.
- Encerrar sessão ao fim do uso.

## 7. Referência
- Data de referência técnica: **2026-03-28**.
- Base: código em `public_html` e schema SQL em `public_html/banco_db/u696029111_DefesaCivilPA.sql`.
