# 🔹Documento Técnico — Implementações Técnicas🔹

## 1. Infraestrutura de aplicação
- Backend em PHP com organização modular.
- Banco MariaDB/MySQL via PDO com exceções habilitadas.
- Configuração por variáveis de ambiente (`.env`).

## 2. Segurança aplicada
- `Protect::check` para autenticação/autorização.
- `Csrf::validateRequestOrFail` para proteção de requisição.
- Controle de sessão com cookie `httponly`, `samesite` e timeout.
- `SecurityHeaders` para `nosniff`, `frame`, `referrer` e `permissions-policy`.

## 3. Upload e manipulação de arquivos
- `UploadHelper::storeImage`: valida mime e tamanho de imagem.
- `UploadHelper::storeKml`: valida assinatura básica de KML.
- `UploadHelper::decodeBase64Png`: valida payload de mapa em PNG.
- Armazenamento em `uploads/*` e `storage/mapas`.

## 4. Implementação geoespacial
- Leitura de geometrias por GeoJSON.
- Conversão KML para GeoJSON em `TerritorioService`.
- Cruzamento territorial para municípios/regiões afetados.
- Exposição de dados geográficos via APIs do módulo mapa.

## 5. Implementação de relatórios
- Dompdf para PDFs operacionais e analíticos.
- Serviço dedicado para PDF de alerta.
- Serviço dedicado para PDF de histórico.
- Serviço dedicado para relatório analítico consolidado.

## 6. Implementação de comunicação
- PHPMailer encapsulado por `EmailService`.
- Envio de alerta para COMPDEC com anexo PDF.
- Processamento em lotes no envio para controle operacional.

## 7. Implementação analítica
- Serviços por domínio analítico (`Analise*Service`).
- Consultas agregadas por período, gravidade, tipologia e território.
- Endpoints de consumo para mapa e dashboards.
