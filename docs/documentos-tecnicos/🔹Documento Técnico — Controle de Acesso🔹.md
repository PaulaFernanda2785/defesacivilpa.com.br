# 🔹Documento Técnico — Controle de Acesso🔹

## 1. Modelo de autenticação
- Sessão baseada em `$_SESSION['usuario']`.
- Login por e-mail/senha (`Auth::login`).
- Logout com destruição de sessão (`Session::destroy`).

## 2. Autorização por perfil
Perfis suportados:
- `ADMIN`
- `GESTOR`
- `ANALISTA`
- `OPERACOES`

A autorização é aplicada por `Protect::check([...])` no início de cada controlador restrito.

## 3. Matriz de acesso funcional

| Módulo | ADMIN | GESTOR | ANALISTA | OPERACOES |
|---|---|---|---|---|
| Painel operacional | Sim | Sim | Sim | Sim |
| Alertas (listar/detalhar) | Sim | Sim | Sim | Sim |
| Alertas (cadastrar/editar/salvar) | Sim | Sim | Sim | Não |
| Alertas (encerrar/cancelar) | Sim | Sim | Não | Não |
| Importação INMET | Sim | Sim | Sim | Não |
| Envio de alerta (API envio) | Sim | Sim | Sim | Não |
| Mapa multirriscos | Sim | Sim | Sim | Sim |
| Análises (painéis e PDF) | Sim | Sim | Sim | Sim |
| Histórico de usuários | Sim | Sim | Não | Não |
| Gestão de usuários | Sim | Não | Não | Não |

## 4. Restrições adicionais
- Alteração de usuário e gestão de perfis: exclusivo `ADMIN`.
- Troca de senha própria: disponível para usuários autenticados.
- Sessão expirada por inatividade bloqueia operação e força novo login.

## 5. Mecanismos de segurança de acesso
- CSRF validado automaticamente em métodos mutáveis.
- Token de idempotência para reduzir reenvio duplo.
- Mensagens HTTP específicas para resposta JSON em APIs.
