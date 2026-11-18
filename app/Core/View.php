<?php

declare(strict_types=1);

namespace App\Core;

/** Renders a PHP template with the shared view contract. */
final class View
{
    public function __construct(private string $directory)
    {
    }

    /**
     * @param array<string, mixed> $data user, csrfToken, error, oldUsername, role
     */
    public function render(string $template, array $data = [], int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
        }

        $vars = $data + [
            'user' => null,
            'csrfToken' => '',
            'error' => null,
            'oldUsername' => '',
            'role' => '',
        ];
        extract($vars, EXTR_SKIP);

        require $this->directory . '/' . $template . '.php';
    }
}
