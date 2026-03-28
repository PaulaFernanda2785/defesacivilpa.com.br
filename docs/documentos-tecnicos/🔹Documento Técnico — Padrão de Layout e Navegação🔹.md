# 🔹Documento Técnico — Padrão de Layout e Navegação🔹

## 1. Estrutura visual padrão
- Layout com `sidebar` fixa e `topbar` contextual.
- `breadcrumb` para orientação de navegação.
- `footer` institucional com versão e ambiente.

## 2. Componentes reutilizados
- `pages/_sidebar.php`
- `pages/_topbar.php`
- `pages/_breadcrumb.php`
- `pages/_footer.php`

## 3. Princípios de navegação
- Navegação por domínio funcional (Operação, Conta, Gestão).
- Destaque de módulo ativo na sidebar.
- Acesso rápido a ações-chave em cada tela (CTAs primários/secundários).

## 4. Responsividade
- Estrutura adaptada para desktop e mobile.
- Controle de abertura/fechamento da sidebar em telas menores.
- Painéis em grade com degradação para coluna única.

## 5. Padrão de experiência
- Cards de resumo executivo no topo das páginas.
- Seções por bloco funcional com títulos padronizados.
- Filtros em formulários consistentes entre módulos.

## 6. Padrão visual
- Identidade institucional da Defesa Civil do Pará.
- Uso de logos em `assets/images`.
- CSS segmentado por página em `assets/css/pages`.
