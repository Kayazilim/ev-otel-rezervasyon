<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Exception\ForbiddenException;
use App\Exception\ValidationException;
use App\Model\User;

/**
 * Controller: isteği alır, servisi çağırır, görünümü seçer. SQL yazmaz, hesap yapmaz.
 */
abstract class Controller
{
    public function __construct(
        protected readonly View $view,
        protected readonly Session $session,
        protected readonly Auth $auth,
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        $data['flashes'] = $this->session->takeFlashes();
        // Bir önceki istekte hatalı gönderilen satır içi formun değerleri (View::old, View::formErrors)
        $this->view->share('formState', $this->session->takeForm());

        return Response::html($this->view->render($template, $data), $status);
    }

    /**
     * Sayfa bölümü (kısmi yenileme ve canlı arama): yerleşim olmadan yalnızca parça.
     *
     * @param array<string, mixed> $data
     */
    protected function fragment(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data, null), $status);
    }

    /** @param array<string, mixed> $query */
    protected function redirect(string $path, array $query = []): Response
    {
        return Response::redirect($this->view->url($path, $query));
    }

    /**
     * Sayfada birden çok satır içi form varken (yönetici listeleri, ret ve iptal nedeni) hatalı gönderim:
     * hata mesajı, girilen değerler ve alan hataları oturuma yazılır, kullanıcı formun bulunduğu sayfaya
     * yönlendirilir. Sayfa yenilense de işlem tekrarlanmaz (PRG), girilen değerler kaybolmaz.
     */
    protected function backWithInput(Request $request, string $formKey, ValidationException $e, string $fallback): Response
    {
        $old = [];
        foreach ($request->post as $name => $value) {
            if (is_string($value) && !in_array($name, ['_csrf', 'donus'], true)) {
                $old[$name] = $value;
            }
        }
        $this->session->flashForm($formKey, $old, $e->errors());
        $this->flash('danger', $e->getMessage());

        return $this->redirect($this->returnPath($request, $fallback));
    }

    /** Formun gönderildiği sayfa ("donus"); parça ve JSON adresleri dönüş adresi olamaz. */
    protected function returnPath(Request $request, string $fallback): string
    {
        $path = $this->safePath($request->input('donus'), $fallback);

        return str_starts_with($path, '/parca/') || str_starts_with($path, '/api/') ? $fallback : $path;
    }

    protected function flash(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }

    protected function user(): User
    {
        return $this->auth->user() ?? throw new ForbiddenException('Bu işlem için giriş yapmalısınız.');
    }

    protected function userId(): int
    {
        return (int) $this->user()->id;
    }

    /** Yalnızca uygulama içi adreslere geri dönülür (açık yönlendirme engeli). */
    protected function safePath(string $path, string $fallback): string
    {
        return str_starts_with($path, '/') && !str_starts_with($path, '//') && !str_contains($path, '\\') ? $path : $fallback;
    }
}
