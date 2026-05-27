<?php

namespace Amea\Controller;

use Amea\Repository\SliderRepository;
use Amea\Config\Database;

class HomeController extends BaseController
{
    private SliderRepository $sliderRepo;

    public function __construct()
    {
        parent::__construct();
        $db = Database::fromEnv()->getConnection();
        $this->sliderRepo = new SliderRepository($db);
    }

    public function index(): void
    {
        $slider_images = $this->sliderRepo->getActiveImages();

        $this->render('home/index.html.twig', [
            'slider_images' => $slider_images,
            'index_active' => true,
        ]);
    }
}
