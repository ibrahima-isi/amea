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

        $carousel_indicators = '';
        $carousel_items = '';
        $is_first = true;
        foreach ($slider_images as $i => $image) {
            $active_class = $is_first ? 'active' : '';
            $carousel_indicators .= '<button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="' . $i . '" class="' . $active_class . '" aria-current="' . ($is_first ? 'true' : 'false') . '" aria-label="Slide ' . ($i + 1) . '"></button>';
            $carousel_items .= '
                <div class="carousel-item ' . $active_class . '">
                    <img src="' . htmlspecialchars($image['image_path']) . '" class="d-block w-100 carousel-img-fixed" alt="' . htmlspecialchars($image['title']) . '">
                    <div class="carousel-caption d-none d-md-block">
                        <h5>' . htmlspecialchars($image['title']) . '</h5>
                        <p>' . htmlspecialchars($image['caption']) . '</p>
                    </div>
                </div>';
            $is_first = false;
        }

        // We still use the old TemplateEngine for now as we are refactoring incrementally
        $data = [
            'carousel_indicators' => $carousel_indicators,
            'carousel_items' => $carousel_items,
            'index_active' => 'active',
            'register_active' => '',
            'login_active' => '',
        ];

        // This is a bit hacky because the current TemplateEngine is very specific
        // In a real senior refactor, we would move to Twig as discussed.
        $this->render('templates/index.html', $data);
    }
}
