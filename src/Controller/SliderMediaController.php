<?php
namespace Amea\Controller;

use Amea\Cache\CachePolicy;
use Amea\Cache\HttpCache;
use Amea\Core\Container;
use Amea\Repository\SliderRepository;
use Amea\Service\MemberDocumentService;
use Amea\Storage\StorageException;

/**
 * Serves active homepage carousel images without starting sessions, instantiating Twig,
 * or querying unrelated settings. Emits Cloudflare public media headers and conditional 304
 * revalidation on the public host while failing closed with private, no-store on staff.
 */
final class SliderMediaController
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function serve(): void
    {
        $isStaff = defined('AMEA_APP') && AMEA_APP === 'staff';
        // Fail-closed defaults so invalid, missing, staff, or error responses are never cached.
        HttpCache::sendNoStore($isStaff);
        header('X-Content-Type-Options: nosniff');

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!is_string($path) || !preg_match('#^/uploads/slider/([a-zA-Z0-9_-][a-zA-Z0-9_.-]*\.(?:jpe?g|png|gif|webp))$#Di', $path, $match)) {
            http_response_code(404);
            return;
        }
        if (preg_match('/\.(?:php[0-9]?|phtml|phar)(?:\.|$)/i', $match[1]) || str_contains($match[1], '..')) {
            http_response_code(404);
            return;
        }

        $record = (new SliderRepository(Container::db()))->findByPath(ltrim($path, '/'));
        if ($record === null) {
            http_response_code(404);
            return;
        }

        $storage = Container::storage();
        $reader = new MemberDocumentService($storage);
        try {
            $metadata = $storage->stat($record['image_path']);
            if ($metadata === null) {
                http_response_code(404);
                return;
            }

            $openVerified = static function () use ($reader, $record, $metadata): array {
                $file = $reader->open($record['image_path'], self::ALLOWED_MIMES, $metadata);
                $image = @getimagesize(stream_get_meta_data($file['stream'])['uri']);
                if (!$image || ($image['mime'] ?? null) !== $file['mime']) {
                    fclose($file['stream']);
                    throw new \UnexpectedValueException('Image invalide.');
                }
                return $file;
            };

            // Staff administration host never emits public/CDN cache headers on non-/assets/ routes.
            if ($isStaff) {
                $file = $openVerified();
                try {
                    header('Content-Type: ' . $file['mime']);
                    header('Content-Length: ' . $file['size']);
                    fpassthru($file['stream']);
                } finally {
                    fclose($file['stream']);
                }
                return;
            }

            $etagPrefix = 'slider:' . $record['id'] . ':' . $record['image_path'] . ':';
            $lastModified = (int) ($metadata['createdAt'] ?? 0) ?: null;
            $allowMetadata304 = $metadata['sha256'] !== null
                && in_array($metadata['mime'], self::ALLOWED_MIMES, true);

            HttpCache::respondPublicMedia(
                $etagPrefix,
                $metadata['sha256'],
                $lastModified,
                CachePolicy::publicMedia(
                    browserMaxAge: 86400,
                    cdnMaxAge: 604800,
                    cdnSwr: 2592000,
                    cdnSie: 2592000
                ),
                $allowMetadata304,
                $openVerified,
                cacheable: $path === '/' . $record['image_path'] && HttpCache::hasQuery('')
            );
        } catch (\OutOfBoundsException|\UnexpectedValueException) {
            http_response_code(404);
            return;
        } catch (StorageException) {
            http_response_code(503);
            return;
        }
    }
}
