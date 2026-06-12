<?php
declare(strict_types=1);

class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function login(string $username, string $password): ?array
    {
        $user = Database::one(
            'SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1',
            [$username]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }
        unset($user['password_hash']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(string $redirect = APP_BASE . '/login.php'): array
    {
        $user = self::user();
        if (!$user) {
            header('Location: ' . $redirect);
            exit;
        }
        return $user;
    }

    /** Map a role to the default landing screen key used by the SPA. */
    public static function defaultScreen(string $role): string
    {
        return match ($role) {
            'admin'           => 'admin',
            'academic_deputy' => 'deputy',
            'exam_manager'    => 'manager',
            'teacher'         => 'teacher-list',
            'exam_supervisor' => 'supervisor',
            'student'         => 'student-login',
            default           => 'home',
        };
    }
}
