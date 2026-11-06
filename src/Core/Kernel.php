<?php

declare(strict_types=1);

namespace App\Core;

use App\Exception\BusyException;
use App\Exception\ForbiddenException;
use App\Exception\NotFoundException;
use App\Model\Role;
use App\Seed\DemoSeeder;
use Throwable;

/**
 * Ön denetleyicinin çekirdeği: her istek aynı yoldan geçer
 * public/index.php -> Kernel -> Router -> Controller -> Service -> Repository.
 *
 * Burada her istek için: oturum, oturum zaman aşımı, CSRF ve rota bazlı yetki denetimi yapılır.
 */
final class Kernel
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(Request $request): Response
    {
        $view = $this->container->view($request->basePath);
        $session = $this->container->session();
        $auth = $this->container->auth();
        $logger = $this->container->logger();

        try {
            $this->ensureDemoData();
            $session->start($request->isHttps());
            $now = $this->container->clock->now()->getTimestamp();

            // Bonus: uzun süre işlem yapılmayan oturum kapatılır.
            if ($auth->check() && $session->isTimedOut($now)) {
                $auth->logout();
                $session->start($request->isHttps());
                $session->flash('warning', 'Oturumunuz uzun süre işlem yapılmadığı için kapatıldı. Lütfen tekrar giriş yapın.');

                return $this->secure(Response::redirect($view->url('/giris')));
            }
            $session->touch($now);

            $view->share('csrfToken', $this->container->csrf()->token());
            $view->share('currentUser', $auth->user());
            $view->share('currentPath', $request->path);
            $view->share('appName', $this->container->settings->appName);

            $match = $this->container->router()->match($request->method, $request->path);
            if ($match === null) {
                throw new NotFoundException('Aradığınız sayfa bulunamadı.');
            }
            if ($match === 'method') {
                return $this->secure($this->error($view, 405, 'İzin verilmeyen işlem', 'Bu adres bu yöntemle kullanılamaz.'));
            }

            // Durum değiştiren her POST isteği CSRF jetonu taşımalıdır (NFR-14).
            if ($request->isPost() && !$this->container->csrf()->verify($request->input('_csrf'))) {
                return $this->secure($this->error($view, 419, 'Güvenlik doğrulaması başarısız', 'Form süresi dolmuş olabilir. Lütfen sayfayı yenileyip tekrar deneyin.'));
            }

            // Rota bazlı yetki (FR-05): arayüzde gizli olsa bile doğrudan istek de reddedilir.
            $access = $match['access'];
            if ($access !== null && !$auth->check()) {
                if (str_starts_with($request->path, '/parca/')) {
                    return $this->secure(Response::html('Oturum gerekli.', 401));
                }
                $session->flash('info', 'Bu sayfa için giriş yapmalısınız.');

                return $this->secure(Response::redirect($view->url('/giris', ['donus' => $request->fullPath()])));
            }
            if (is_array($access) && !$auth->hasRole(...$access)) {
                throw new ForbiddenException('Bu sayfaya erişim yetkiniz yok.');
            }

            [$class, $method] = $match['handler'];
            $controller = $this->container->controller($class);
            /** @var Response $response */
            $response = $controller->{$method}($request, ...$match['params']);

            return $this->secure($response);
        } catch (NotFoundException $e) {
            return $this->secure($this->error($view, 404, 'Bulunamadı', $e->getMessage()));
        } catch (ForbiddenException $e) {
            return $this->secure($this->error($view, 403, 'Yetkiniz yok', $e->getMessage()));
        } catch (BusyException $e) {
            return $this->secure($this->error($view, 503, 'Yoğunluk', $e->getMessage()));
        } catch (Throwable $e) {
            // Ayrıntı günlüğe yazılır (hassas veri içermez); kullanıcıya genel mesaj gösterilir (NFR-10).
            $logger->error('beklenmeyen_hata', ['tur' => $e::class, 'mesaj' => $e->getMessage(), 'yer' => basename($e->getFile()) . ':' . $e->getLine()]);

            return $this->secure($this->error($view, 500, 'Beklenmeyen hata', 'Bir sorun oluştu. Lütfen daha sonra tekrar deneyin.'));
        }
    }

    private function error(View $view, int $status, string $title, string $message): Response
    {
        $session = $this->container->session();
        $user = $this->container->auth()->user();
        $home = match (true) {
            $user?->role === Role::IlanSahibi => '/panel',
            $user?->role === Role::Yonetici => '/yonetim',
            default => '/',
        };

        return Response::html($view->render('errors/error', [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'home' => $home,
            'flashes' => $session->takeFlashes(),
        ]), $status);
    }

    /** Temel güvenlik başlıkları. */
    private function secure(Response $response): Response
    {
        $response->headers['X-Content-Type-Options'] = 'nosniff';
        $response->headers['X-Frame-Options'] = 'DENY';
        $response->headers['Referrer-Policy'] = 'same-origin';

        return $response;
    }

    /** Sunumda boş veriyle demo yapılmaz: veri klasörü boşsa örnek veri bir kez yüklenir. */
    private function ensureDemoData(): void
    {
        $dir = $this->container->settings->dataDir;
        if (!is_file($dir . '/users.json')) {
            (new DemoSeeder($this->container))->run();
            $this->container->logger()->info('ornek_veri_yuklendi');
        }
    }
}
