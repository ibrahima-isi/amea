<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\SliderRepository;

class HomeController extends BaseController
{
    private SliderRepository $sliderRepo;

    public function __construct()
    {
        parent::__construct();
        $this->sliderRepo = new SliderRepository(Container::db());
    }

    public function index(): void
    {
        $slider_images = $this->sliderRepo->getActiveImages();

        $this->render('home/index.html.twig', [
            'slider_images' => $slider_images,
            'journal_posts' => (new \Amea\Repository\JournalRepository(Container::db()))->findRecentPublished(3),
            'index_active' => true,
        ]);
    }
}
