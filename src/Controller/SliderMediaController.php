<?php
namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\SliderRepository;
use Amea\Service\MemberDocumentService;
use Amea\Storage\StorageException;

final class SliderMediaController extends BaseController
{
    public function __construct()
    {
        parent::__construct(false);
    }

    public function serve(): void
    {
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!is_string($path) || !preg_match('#^/uploads/slider/([a-zA-Z0-9_-][a-zA-Z0-9_.-]*\.(?:jpe?g|png|gif|webp))$#Di', $path, $match)) {
            http_response_code(404); return;
        }
        if (preg_match('/\.(?:php[0-9]?|phtml|phar)(?:\.|$)/i', $match[1]) || str_contains($match[1], '..')) {
            http_response_code(404); return;
        }
        $record = (new SliderRepository(Container::db()))->findByPath(ltrim($path, '/'));
        if ($record === null) { http_response_code(404); return; }
        try {
            $file = (new MemberDocumentService(Container::storage()))->open($record['image_path'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
            $image = @getimagesize(stream_get_meta_data($file['stream'])['uri']);
            if (!$image || ($image['mime'] ?? null) !== $file['mime']) {
                fclose($file['stream']);
                http_response_code(404); return;
            }
        } catch (\OutOfBoundsException|\UnexpectedValueException) {
            http_response_code(404); return;
        } catch (StorageException) {
            http_response_code(503); return;
        }
        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . $file['size']);
        fpassthru($file['stream']);
        fclose($file['stream']);
    }
}
