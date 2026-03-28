# 🔹 DOCUMENTO TÉCNICO FINAL🔹

## 1. Escopo consolidado
O sistema consolida operação de alertas multirriscos para a Defesa Civil do Estado do Pará, cobrindo:
- Autenticação e sessão por perfil.
- Gestão de alertas manuais e importados (INMET).
- Territorialização por município e região de integração.
- Painel geográfico (mapa multirriscos) com filtros operacionais.
- Módulos analíticos e relatórios PDF.
- Auditoria de ações de usuário.

## 2. Arquitetura de execução
- Stack principal: `PHP + MariaDB + HTML/CSS/JS`.
- Entrada por scripts em `public_html/pages` e `public_html/api`.
- Núcleo de domínio em `public_html/app`:
  - `Core`: sessão, auth, CSRF, proteção e conexão DB.
  - `Helpers`: validação, segurança de headers, tempo, upload.
  - `Services`: regras de negócio e geração de relatórios.

## 3. Domínios funcionais implementados
- **Alertas**: criação, edição, encerramento/cancelamento, exportação, envio COMPDEC.
- **Integração INMET**: ingestão CAP/XML a partir de URL oficial com deduplicação por `inmet_id`.
- **Território**: interseção de área do alerta com malha municipal e mapeamento regional.
- **Análises**: temporal, severidade, tipologia e índices de pressão/risco.
- **Histórico**: trilha de auditoria por ação, com filtros e PDF.
- **Usuários**: administração de perfis, status e senha.

## 4. Banco de dados consolidado
Entidades operacionais centrais:
- `alertas`
- `alerta_municipios`
- `alerta_regioes`
- `historico_usuarios`
- `municipios_regioes_pa`
- `usuarios`

## 5. Segurança aplicada
- Validação de sessão e perfil por `Protect::check`.
- Proteção CSRF com token + idempotência de requisição.
- Prepared statements PDO em rotas de negócio.
- Headers de segurança e política de conteúdo para páginas públicas.
- Controle de timeout por inatividade.

## 6. Limitações técnicas atuais
- Ausência de suíte de testes automatizados no repositório.
- Dependência de dados geográficos e planilha externa para alguns fluxos.
- Arquitetura baseada em scripts (não framework full-stack), exigindo disciplina de organização.
- Existem pontos de endurecimento e otimização descritos em documento próprio.

## 7. Data de referência
- Consolidação técnica: **28/03/2026**.
- Repositório analisado: `d:\wamp64\www\defesacivilpa.com.br`.
