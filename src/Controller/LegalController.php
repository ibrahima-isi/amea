<?php

namespace Amea\Controller;

class LegalController extends BaseController
{
    public function index(): void
    {
        $this->render('legal-notice.html.twig', []);
    }
}
