<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Exception\ValidationException;
use App\Model\Role;
use App\Model\User;
use App\Service\AuthService;

/** Giriş, kayıt, çıkış ve parola sıfırlama (FR-01 - FR-04; taslak 05). */
final class AuthController extends Controller
{
    public function __construct(View $view, Session $session, Auth $auth, private readonly AuthService $authService)
    {
        parent::__construct($view, $session, $auth);
    }

    public function showLogin(Request $request): Response
    {
        if ($this->auth->check()) {
            return $this->redirect($this->homeFor($this->user()));
        }

        return $this->render('auth/login', [
            'title' => 'Giriş / Kayıt',
            'returnTo' => $request->query('donus'),
            'loginErrors' => [],
            'registerErrors' => [],
            'old' => ['rol' => $request->query('rol') === Role::IlanSahibi->value ? Role::IlanSahibi->value : Role::Musteri->value],
        ]);
    }

    public function login(Request $request): Response
    {
        try {
            $user = $this->authService->login($request->input('eposta'), $request->input('parola'));
        } catch (ValidationException $e) {
            return $this->render('auth/login', [
                'title' => 'Giriş / Kayıt',
                'returnTo' => $request->input('donus'),
                'loginErrors' => $e->errors(),
                'registerErrors' => [],
                'old' => ['giris_eposta' => $request->input('eposta')],
            ], 422);
        }
        $this->auth->login($user);
        $this->flash('success', 'Hoş geldiniz, ' . $user->firstName . '!');

        return Response::redirect($this->view->url($this->safePath($request->input('donus'), $this->homeFor($user))));
    }

    public function register(Request $request): Response
    {
        $role = $request->input('rol') === Role::IlanSahibi->value ? Role::IlanSahibi : Role::Musteri;
        try {
            $user = $this->authService->register(
                $request->input('ad'),
                $request->input('soyad'),
                $request->input('eposta'),
                $request->input('parola'),
                $role,
            );
        } catch (ValidationException $e) {
            return $this->render('auth/login', [
                'title' => 'Giriş / Kayıt',
                'returnTo' => $request->input('donus'),
                'loginErrors' => [],
                'registerErrors' => $e->errors(),
                'old' => [
                    'ad' => $request->input('ad'),
                    'soyad' => $request->input('soyad'),
                    'eposta' => $request->input('eposta'),
                    'rol' => $role->value,
                ],
            ], 422);
        }
        $this->auth->login($user);
        $this->flash('success', 'Hesabınız oluşturuldu. Hoş geldiniz, ' . $user->firstName . '!');

        return Response::redirect($this->view->url($this->safePath($request->input('donus'), $this->homeFor($user))));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        $this->session->start($request->isHttps());
        $this->flash('info', 'Güvenli bir şekilde çıkış yaptınız.');

        return $this->redirect('/');
    }

    public function showForgot(Request $request): Response
    {
        return $this->render('auth/forgot', ['title' => 'Parola sıfırlama', 'errors' => [], 'old' => []]);
    }

    public function forgot(Request $request): Response
    {
        $email = trim($request->input('eposta'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->render('auth/forgot', [
                'title' => 'Parola sıfırlama',
                'errors' => ['eposta' => 'Geçerli bir e-posta adresi girin.'],
                'old' => ['eposta' => $request->input('eposta')],
            ], 422);
        }
        $base = ($request->isHttps() ? 'https://' : 'http://') . $request->header('Host');
        $this->authService->requestPasswordReset($email, fn (string $token): string => $base . $this->view->url('/parola-sifirla/' . $token));
        // Kayıtlı olsun olmasın aynı yanıt (hesap varlığı sızdırılmaz); simüle modda giriş sayfası posta kutusunu gösterir.
        $this->flash('info', 'Bu e-posta adresiyle kayıtlı bir hesap varsa sıfırlama bağlantısı gönderildi. Güvenlik nedeniyle adresin kayıtlı olup olmadığını göstermiyoruz.');

        return $this->redirect('/giris');
    }

    /** FR-04: bağlantı açılınca önce geçerliliği denetlenir; süresi dolmuş veya kullanılmışsa form gösterilmez. */
    public function showReset(Request $request, string $token): Response
    {
        try {
            $record = $this->authService->usableResetToken($token);
        } catch (ValidationException $e) {
            return $this->invalidResetLink($token, $e);
        }

        return $this->render('auth/reset', ['title' => 'Yeni parola', 'token' => $token, 'errors' => [], 'invalid' => null, 'expiresAt' => $record->expiresAt]);
    }

    public function reset(Request $request, string $token): Response
    {
        try {
            $this->authService->resetPassword($token, $request->input('parola'));
        } catch (ValidationException $e) {
            if (isset($e->errors()['jeton'])) {
                return $this->invalidResetLink($token, $e);
            }

            return $this->render('auth/reset', ['title' => 'Yeni parola', 'token' => $token, 'errors' => $e->errors(), 'invalid' => null, 'expiresAt' => null], 422);
        }
        $this->flash('success', 'Parolanız güncellendi. Yeni parolanızla giriş yapabilirsiniz.');

        return $this->redirect('/giris');
    }

    /** Geçersiz, kullanılmış veya süresi dolmuş bağlantı: form yerine nedeni ve yeni bağlantı isteme düğmesi (410 Gone). */
    private function invalidResetLink(string $token, ValidationException $e): Response
    {
        return $this->render('auth/reset', [
            'title' => 'Yeni parola',
            'token' => $token,
            'errors' => [],
            'invalid' => $e->errors()['jeton'] ?? $e->getMessage(),
            'expiresAt' => null,
        ], 410);
    }

    private function homeFor(User $user): string
    {
        return match ($user->role) {
            Role::IlanSahibi => '/panel',
            Role::Yonetici => '/yonetim',
            Role::Musteri => '/',
        };
    }
}
