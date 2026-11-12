<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/notificationService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError('Dados inválidos.', 400);
}

$db = Database::getInstance()->getConnection();
$action = (string) ($input['action'] ?? '');

if ($action === 'request') {
    $email = trim(strtolower((string) ($input['email'] ?? '')));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $recent = $db->prepare('SELECT id FROM password_reset_tokens WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE) LIMIT 1');
                $recent->execute([(int) $user['id']]);
                if (!$recent->fetchColumn()) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                    $db->beginTransaction();
                    $delete = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
                    $delete->execute([(int) $user['id']]);
                    $insert = $db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
                    $insert->execute([(int) $user['id'], $tokenHash, $expiresAt]);
                    $db->commit();

                    $platform = getPlatformSetting($db, 'saas_company_name') ?: 'Maps Prospector';
                    $resetUrl = BASE_URL . 'app/?reset=' . rawurlencode($token);
                    $safeName = htmlspecialchars((string) ($user['name'] ?? 'usuário'), ENT_QUOTES, 'UTF-8');
                    $safePlatform = htmlspecialchars((string) $platform, ENT_QUOTES, 'UTF-8');
                    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
                    $body = '<div style="font-family:Arial,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;padding:24px">'
                        . '<h2>Redefinição de senha</h2><p>Olá, <strong>' . $safeName . '</strong>.</p>'
                        . '<p>Recebemos uma solicitação para redefinir sua senha em ' . $safePlatform . '.</p>'
                        . '<p><a href="' . $safeUrl . '">Criar uma nova senha</a></p>'
                        . '<p>Este link expira em uma hora. Se você não solicitou a redefinição, ignore esta mensagem.</p></div>';
                    if (!NotificationService::sendEmail($db, $email, 'Redefinição de senha - ' . $platform, $body)) {
                        error_log('Password reset email could not be sent.');
                    }
                }
            }
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Password reset request failed: ' . $error->getMessage());
        }
    }

    jsonSuccess(null, 'Se o e-mail estiver cadastrado, você receberá instruções para redefinir sua senha.');
}

if ($action === 'reset') {
    $token = (string) ($input['token'] ?? '');
    $password = (string) ($input['password'] ?? '');
    $passwordConfirm = (string) ($input['passwordConfirm'] ?? '');

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        jsonError('Link de redefinição inválido ou expirado.', 400);
    }
    if (strlen($password) < 8) {
        jsonError('A senha deve ter pelo menos 8 caracteres.', 400);
    }
    if (!hash_equals($password, $passwordConfirm)) {
        jsonError('As senhas não coincidem.', 400);
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > NOW() LIMIT 1 FOR UPDATE');
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reset) {
            $db->rollBack();
            jsonError('Link de redefinição inválido ou expirado.', 400);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $sessionToken = bin2hex(random_bytes(32));
        $update = $db->prepare('UPDATE users SET password = ?, current_session_token = ? WHERE id = ?');
        $update->execute([$passwordHash, $sessionToken, (int) $reset['user_id']]);
        $delete = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $delete->execute([(int) $reset['user_id']]);
        $db->commit();

        jsonSuccess(null, 'Senha redefinida. Você já pode entrar com a nova senha.');
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Password reset completion failed: ' . $error->getMessage());
        jsonError('Não foi possível redefinir a senha agora. Tente novamente.', 500);
    }
}

jsonError('Ação inválida.', 400);
