<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Exception\ValidationException;
use App\Service\ComplaintService;

/** Şikâyet oluşturma (FR-27). */
final class ComplaintController extends Controller
{
    public function __construct(View $view, Session $session, Auth $auth, private readonly ComplaintService $complaints)
    {
        parent::__construct($view, $session, $auth);
    }

    public function create(Request $request): Response
    {
        return $this->render('complaint/create', [
            'title' => 'Şikâyet oluştur',
            'errors' => [],
            'old' => ['ilan' => $request->query('ilan'), 'rezervasyon' => $request->query('rezervasyon'), 'aciklama' => ''],
        ]);
    }

    public function store(Request $request): Response
    {
        try {
            $this->complaints->create($this->userId(), $request->nullableInt('ilan'), $request->input('rezervasyon'), $request->input('aciklama'));
        } catch (ValidationException $e) {
            return $this->render('complaint/create', [
                'title' => 'Şikâyet oluştur',
                'errors' => $e->errors(),
                'old' => ['ilan' => $request->input('ilan'), 'rezervasyon' => $request->input('rezervasyon'), 'aciklama' => $request->input('aciklama')],
            ], 422);
        }
        $this->flash('success', 'Şikâyetiniz yöneticiye iletildi. Teşekkür ederiz.');

        return $this->redirect('/');
    }
}
