<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;
use App\Models\Laporan;
use App\Services\Auth;

/** Petugas period report and its print view (FR-11). */
final class LaporanController
{
    public function __construct(
        private Auth $auth,
        private Session $session,
        private View $view,
        private Laporan $laporan
    ) {
    }

    public function index(): void
    {
        $parsed = $this->parsedQuery();
        $rekap = $this->laporan->rekap($parsed['values']['from'], $parsed['values']['to'], $parsed['values']['status']);
        $this->render('petugas/laporan/index', [
            'values' => $parsed['values'],
            'errors' => $parsed['errors'],
            'items' => $rekap['items'],
            'summary' => $rekap['summary'],
            'truncated' => $rekap['truncated'],
            'pageTitle' => 'Laporan Peminjaman',
        ], $parsed['errors'] === [] ? 200 : 422);
    }

    public function cetak(): void
    {
        $parsed = $this->parsedQuery();
        $rekap = $this->laporan->rekap($parsed['values']['from'], $parsed['values']['to'], $parsed['values']['status']);
        $user = $this->auth->user();
        $this->view->render('petugas/laporan/cetak', [
            'user' => $user,
            'role' => $user === null ? '' : (string) $user['role'],
            'csrfToken' => $this->session->csrfToken(),
            'values' => $parsed['values'],
            'errors' => $parsed['errors'],
            'items' => $rekap['items'],
            'summary' => $rekap['summary'],
            'truncated' => $rekap['truncated'],
            'printedAt' => date('d-m-Y H:i'),
        ], $parsed['errors'] === [] ? 200 : 422);
    }

    /** Invalid input falls back to the default range so the page still renders. */
    private function parsedQuery(): array
    {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        if (!is_string($from) || $from === '') {
            $from = date('Y-m-01');
        }
        if (!is_string($to) || $to === '') {
            $to = date('Y-m-d');
        }

        return $this->laporan->parse($from, $to, $_GET['status'] ?? null);
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
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ], $status);
    }
}
