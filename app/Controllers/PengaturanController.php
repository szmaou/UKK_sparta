<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Session;
use App\Core\View;
use App\Models\Pengaturan;
use App\Services\Auth;

/** Admin settings, currently the configurable late-return fine tariff. */
final class PengaturanController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private Pengaturan $pengaturan
    ) {
    }

    public function index(): void
    {
        $this->renderForm(['tarif_denda' => (string) $this->pengaturan->tarifDenda()]);
    }

    public function update(): void
    {
        $parsed = $this->pengaturan->parse($_POST);
        if ($parsed['errors'] !== []) {
            $this->renderForm($parsed['values'], $parsed['errors'], 422);
            return;
        }
        $this->pengaturan->save((int) $parsed['values']['tarif_denda'], $this->actorId());
        $this->session->set('flash_success', 'Tarif denda berhasil diperbarui.');
        redirect('/admin/pengaturan');
    }

    /** @param array<string, string> $values @param array<string, string> $errors */
    private function renderForm(array $values, array $errors = [], int $status = 200): void
    {
        $user = $this->auth->user();
        $success = $this->session->get('flash_success');
        $error = $this->session->get('flash_error');
        $this->session->forget('flash_success');
        $this->session->forget('flash_error');
        $this->view->render('admin/pengaturan/index', [
            'user' => $user,
            'role' => $user === null ? '' : (string) $user['role'],
            'csrfToken' => $this->session->csrfToken(),
            'values' => $values,
            'errors' => $errors,
            'pageTitle' => 'Pengaturan Denda',
            'activeNav' => 'pengaturan',
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }

    private function actorId(): int
    {
        $user = $this->auth->user();
        return $user === null ? 0 : (int) $user['id'];
    }
}
