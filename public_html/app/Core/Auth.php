<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Session.php';

class Auth {

    public static function login($email, $senha) {
        Session::start();

        $email = mb_strtolower(trim((string) $email));

        $db = Database::getConnection();

        $stmt = $db->prepare(
            "SELECT id, nome, email, senha_hash, perfil, status 
             FROM usuarios 
             WHERE email = ? AND status = 'ATIVO' 
             LIMIT 1"
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($senha, $user['senha_hash'])) {
            Session::regenerate();
            $_SESSION['usuario'] = [
                'id'     => $user['id'],
                'nome'   => $user['nome'],
                'email'  => $user['email'],
                'perfil' => $user['perfil']
            ];
            Session::touchActivity();
            return true;
        }

        return false;
    }

    public static function check() {
        Session::start();
        return isset($_SESSION['usuario']);
    }

    public static function logout() {
        Session::start();
        Session::destroy();
        header('Location: /index.php');
        exit;
    }
}
