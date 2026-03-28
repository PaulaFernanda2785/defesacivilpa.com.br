# 🔹Documento Técnico — Estrutura de Pastas🔹

## 1. Raiz do projeto
- `.github/`: workflows e automação de repositório.
- `docs/`: documentação técnica e histórico de evolução.
- `infra/`: artefatos de infraestrutura local.
- `public_html/`: aplicação web publicada.
- `storage/`: armazenamento auxiliar fora da raiz web.

## 2. Estrutura principal de aplicação (`public_html`)
- `api/`: endpoints JSON.
  - `alertas/`
  - `ia/`
  - `mapa/`
- `app/`: núcleo de backend.
  - `Core/`
  - `Helpers/`
  - `Services/`
  - `config/`
  - `Libraries/PHPMailer/`
  - `Lib/dompdf/`
- `assets/`: front-end estático.
  - `css/`
  - `js/`
  - `images/`
  - `vendor/`
- `pages/`: telas e controladores web por domínio.
  - `alertas/`
  - `analises/`
  - `historico/`
  - `mapas/`
  - `usuarios/`
- `storage/`: cache e artefatos temporários do runtime.
- `uploads/`: arquivos de entrada de usuário (imagens/KML).
- `config/`: configurações auxiliares.
- `banco_db/`: dump SQL de referência.
- `scripts/`: scripts utilitários de importação/carga.

## 3. Observações operacionais
- A aplicação é orientada a scripts PHP por pasta funcional.
- Os diretórios de runtime (`storage`, `uploads`) devem ter controle de permissão e backup.
- `banco_db` e `scripts` exigem atenção de hardening em ambiente produtivo.

## 4. Data de referência
- Estrutura validada em **2026-03-28**.
