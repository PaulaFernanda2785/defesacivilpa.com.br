# Checklist de Publicacao para Hostinger

## Antes do deploy

1. Validar login, cadastro/edicao de alertas, geracao de mapa, PDF e envio de alerta no ambiente Wamp.
2. Confirmar backup do banco atual na Hostinger.
3. Rotacionar senhas externas ainda pendentes no painel da Hostinger, principalmente SMTP.
4. Registrar os novos valores apenas em `.env` privado fora do `public_html`.
5. Validar UTF-8 em nomes, regioes e textos longos antes da publicacao.
6. Garantir que o pacote final nao inclua dumps, documentos internos e scripts operacionais desnecessarios.
7. Conferir se `public_html/assets/css/pages/` e `public_html/assets/js/pages/` foram publicados junto com o shell responsivo.
8. Executar no banco de origem a migracao descrita em `docs/MIGRACAO_NIVEL_GRAVIDADE_EXTREMO.md` antes de liberar o novo formulario de alertas.
9. Executar no banco de origem a otimizacao de indices descrita em `docs/OTIMIZACAO_SQL_ANALISES_2026_03_25.md` antes de liberar as paginas de analises/mapa.
10. Executar a janela operacional com ordem de comandos e rollback guiado em `docs/JANELA_MANUTENCAO_SQL_ANALISES_2026_03_25.md`.

## Estrutura recomendada do pacote

- `public_html/`
  - codigo PHP da aplicacao
  - assets
  - uploads necessarios
  - `.htaccess`
- fora de `public_html/`
  - arquivo `.env` ou configuracao equivalente
  - backups locais do banco
  - backups
  - documentos internos

## Ordem de publicacao

1. Colocar a aplicacao em janela controlada de manutencao.
2. Publicar os arquivos novos em paralelo a um backup dos atuais.
3. Publicar ou recriar o `.env` privado fora do `public_html`.
4. Atualizar configuracoes de ambiente.
5. Validar permissao de escrita em `uploads/` e `storage/`.
6. Testar login, painel, listagem de alertas, detalhe, PDF, envio e historico.
7. Testar navegacao mobile e abertura do menu hamburguer nas paginas principais.
8. Validar textos com acento em usuarios, municipios, regioes e recomendacoes.
9. Confirmar que `MUITO ALTO` continua disponivel, que `EXTREMO` aparece como quinto nivel e que o sistema exibe `EXTREMO` em roxo nas telas e mapas.
10. Encerrar manutencao apenas depois da validacao funcional minima.

## Rollback

1. Restaurar snapshot/backup do banco.
2. Restaurar pacote anterior de arquivos.
3. Limpar cache de navegador e do servidor, se aplicavel.
4. Revalidar acesso basico e emissao de alertas.
