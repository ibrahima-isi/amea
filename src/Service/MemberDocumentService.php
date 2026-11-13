<?php
namespace Amea\Service;

use Amea\Storage\Storage;
use Amea\Storage\StorageException;

/** Spool and verify private content before sending response headers. */
class MemberDocumentService
{
    public function __construct(private Storage $storage) {}

    /** @return array{stream: resource, size: int, mime: string} */
    public function open(string $key, array $allowedMimes): array
    {
        $metadata = $this->storage->stat($key);
        if ($metadata === null) {
            throw new \OutOfBoundsException('Fichier introuvable.');
        }
        $source = $this->storage->read($key);
        $spool = tmpfile();
        if ($spool === false) {
            fclose($source);
            throw new StorageException('Unable to spool the document.');
        }
        try {
            $size = stream_copy_to_stream($source, $spool);
            if ($size === false || $size !== $metadata['size']) {
                throw new StorageException('Incomplete document stream.');
            }
            rewind($spool);
            $hash = hash_init('sha256');
            hash_update_stream($hash, $spool);
            if ($metadata['sha256'] !== null && hash_final($hash) !== $metadata['sha256']) {
                throw new StorageException('Document checksum mismatch.');
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file(stream_get_meta_data($spool)['uri']);
            if (!in_array($mime, $allowedMimes, true)) {
                throw new \UnexpectedValueException('Type de fichier non supporté.');
            }
            rewind($spool);
            return ['stream' => $spool, 'size' => $size, 'mime' => $mime];
        } catch (\Throwable $error) {
            fclose($spool);
            throw $error;
        } finally {
            fclose($source);
        }
    }
}
