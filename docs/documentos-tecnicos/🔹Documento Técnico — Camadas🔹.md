# 🔹Documento Técnico — Camadas🔹

## 1. Bootstrap
Responsáveis por iniciar contexto de execução:
- `Env::loadFromCandidates`
- `TimeHelper::bootstrap`
- `Session::start`
- `Database::getConnection`

## 2. Middleware de proteção
Aplicado no início dos controladores:
- `Protect::check` para autenticação, autorização e CSRF automático em métodos mutáveis.
- Resposta diferenciada para HTML/JSON em casos de sessão inválida.

## 3. Controllers (entrada)
Arquivos em `pages/*` e `api/*` atuam como controladores:
- Recebem parâmetros (`$_GET`, `$_POST`, JSON body).
- Chamam validação e serviços.
- Retornam view HTML ou payload JSON.

## 4. Services (negócio)
Implementam regras principais em `app/Services`:
- Alerta, território, integração INMET, envio COMPDEC.
- Módulos analíticos.
- Geração de PDF de alerta, histórico e relatório analítico.

## 5. Repositories (acesso a dados)
Não há camada repository explícita. O acesso é feito diretamente por PDO em controllers/services.

## 6. Views
Renderização por arquivos PHP/HTML em `pages/*` com includes estruturais:
- `_sidebar.php`
- `_topbar.php`
- `_breadcrumb.php`
- `_footer.php`

## 7. Assets e client-side
- Scripts JS em `assets/js` e `assets/js/pages`.
- Estilos em `assets/css` e `assets/css/pages`.
- Interação de mapa e gráficos no cliente para experiência operacional.

## 8. Observação arquitetural
A separação em camadas é funcional, porém **não é framework-MVC estrito**. A disciplina de organização por pasta e serviço é o mecanismo principal de governança técnica.
