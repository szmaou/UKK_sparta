<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\ActivityLog;
use App\Services\Auth;

/** Admin audit trail (FR-08). Read-only: logs are append-only and never edited here. */
final class ActivityLogController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private ActivityLog $logs
    ) {
    }

    public function index(): void
    {
        $q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $action = is_string($_GET['action'] ?? null) ? trim($_GET['action']) : '';
        $page = $this->pageParam();
        $result = $this->logs->paginate($q, $action, $page);
        $this->render('admin/activity-logs/index', [
            'items' => $result['items'],
            'page' => $page,
            'totalPages' => $result['totalPages'],
            'total' => $result['total'],
            'q' => $q,
            'action' => $action,
            'actions' => $this->logs->actions(),
            'pageTitle' => 'Log Aktivitas',
        ]);
    }

    private function pageParam(): int
    {
        $page = $_GET['page'] ?? null;

        return is_string($page) && preg_match('/^[1-9]\d{0,6}$/', $page) === 1 ? (int) $page : 1;
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data, int $status = 200): void
    {
        $user = $this->auth->user();
        $success = $this->session->get('flash_success');
        $error = $this->session->get('flash_error');
        $this->session->forget('flash_success');
        $this->session->forget('flash_error');
        $this->view->render($template, $data + [
            'user' => $user,
            'role' => $user === null ? '' : (string) $user['role'],
            'csrfToken' => $this->session->csrfToken(),
            'errors' => [],
            'values' => [],
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
