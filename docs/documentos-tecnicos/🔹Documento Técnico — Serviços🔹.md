# 🔹Documento Técnico — Serviços🔹

## 1. Serviços de alerta e operação
- `AlertaService`
  - Gera número sequencial anual.
  - Salva alerta com estrutura padrão.
- `AlertaEnvioService`
  - Monta lista de destinos COMPDEC.
  - Gera anexo PDF e dispara envio em lotes.
  - Atualiza status de envio no alerta.
- `InmetService`
  - Importa dados oficiais via URL do INMET.
  - Extrai dados CAP/XML e converte para payload interno.
- `TerritorioService`
  - Identifica municípios/regiões afetados.
  - Converte KML para GeoJSON.

## 2. Serviços de análise
- `AnaliseTemporalService`
- `AnaliseSeveridadeService`
- `AnaliseTipologiaService`
- `AnaliseIndiceRiscoService`
- `AnaliseIndicePressaoService`
- `AnaliseGlobalService`
- `AnaliseAlertasEmitidosService`
- `AnaliseImpactoTerritorialService`
- `AnaliseMunicipiosImpactadosService`
- `AnaliseFiltroService`

Esses serviços consolidam consultas, agregações e comparativos para os painéis analíticos.

## 3. Serviços de relatório e comunicação
- `PdfService`: PDF de alerta operacional.
- `RelatorioAnaliticoPdfService`: relatório consolidado de análises.
- `PdfHistoricoService`: relatório de auditoria de usuários.
- `EmailService`: encapsula PHPMailer para envio SMTP.

## 4. Serviço de auditoria
- `HistoricoService`
  - Registra ações de usuário.
  - Fornece catálogo de ações e utilidades de filtro.

## 5. Convenções de implementação
- Métodos estáticos predominantes em serviços utilitários.
- Retornos em `array` para controllers web/api.
- Uso de PDO com parâmetros para consultas críticas.
