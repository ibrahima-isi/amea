<?php
namespace Amea\Service;

use Amea\Repository\JournalRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use OutOfBoundsException;

final class JournalService
{
    private \Closure $clock;
    private const TYPES = ['ACTIVITY', 'REPORT', 'ANNOUNCEMENT'];
    private const TEXT_LIMITS = [
        'title' => 180, 'summary' => 320, 'body' => 50000,
        'cover_alt' => 500, 'location' => 255,
    ];
    private const INPUTS = [
        'type', 'title', 'summary', 'body', 'cover_alt', 'location',
        'activity_start_date', 'activity_start_time',
        'activity_end_date', 'activity_end_time', 'remove_cover',
    ];

    public function __construct(private JournalRepository $posts, ?callable $clock = null)
    {
        $this->clock = $clock === null
            ? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('Africa/Dakar'))
            : \Closure::fromCallable($clock);
    }

    public function save(?int $id, array $input, int $actorId, bool $publish = false, ?array $trustedCover = null): array
    {
        $this->assertActor($actorId);
        $existing = $id === null ? null : $this->requirePost($id);
        $form = $this->formValues($existing);
        foreach (self::INPUTS as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            if (!is_string($input[$field]) && !is_int($input[$field]) && !is_bool($input[$field])) {
                throw new InvalidArgumentException('Un champ du journal est invalide.');
            }
            $form[$field] = trim((string)$input[$field]);
        }
        if (!in_array($form['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('Choisissez un type de publication valide.');
        }
        foreach (self::TEXT_LIMITS as $field => $limit) {
            if (mb_strlen($form[$field], 'UTF-8') > $limit || !mb_check_encoding($form[$field], 'UTF-8')) {
                throw new InvalidArgumentException('Un texte du journal dépasse la longueur autorisée ou est invalide.');
            }
        }
        if ($form['title'] === '') {
            throw new InvalidArgumentException('Le titre est obligatoire.');
        }

        $values = array_intersect_key($form, self::TEXT_LIMITS);
        $values['type'] = $form['type'];
        $values['activity_start_at'] = null;
        $values['activity_end_at'] = null;
        $values['activity_start_time_set'] = 0;
        $values['activity_end_time_set'] = 0;
        $values['location'] = null;
        if ($form['type'] === 'ACTIVITY') {
            $endDate = $form['activity_end_date'] ?: $form['activity_start_date'];
            $start = $this->activityDate($form['activity_start_date'], $form['activity_start_time'], false);
            $end = $this->activityDate($endDate, $form['activity_end_time'], true);
            if ($start !== null && $end !== null && $end < $start) {
                throw new InvalidArgumentException('La fin de l’activité ne peut pas précéder son début.');
            }
            $values['activity_start_at'] = $start?->format('Y-m-d H:i:s');
            $values['activity_end_at'] = $end?->format('Y-m-d H:i:s');
            $values['activity_start_time_set'] = (int)($form['activity_start_time'] !== '');
            $values['activity_end_time_set'] = (int)($form['activity_end_time'] !== '');
            $values['location'] = $form['location'];
        }

        $values['cover_path'] = $existing['cover_path'] ?? null;
        $values['cover_mime'] = $existing['cover_mime'] ?? null;
        if ($form['remove_cover'] === '1') {
            $values['cover_path'] = null;
            $values['cover_mime'] = null;
        }
        if ($trustedCover !== null) {
            $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = $trustedCover['mime'] ?? null;
            $path = $trustedCover['path'] ?? null;
            if (!is_string($mime) || !is_string($path) || !isset($mimeExtensions[$mime])
                || !preg_match('#^uploads/journal/covers/[a-f0-9]{32}\\.' . $mimeExtensions[$mime] . '$#D', $path)) {
                throw new InvalidArgumentException('La couverture du journal est invalide.');
            }
            $values['cover_path'] = $path;
            $values['cover_mime'] = $mime;
        }

        $status = $publish ? 'PUBLISHED' : ($existing['status'] ?? 'DRAFT');
        if ($status === 'PUBLISHED') {
            if ($form['summary'] === '' || $form['body'] === '') {
                throw new InvalidArgumentException('Le résumé et le texte sont obligatoires pour publier.');
            }
            if ($values['type'] === 'ACTIVITY'
                && (!$values['activity_start_at'] || !$values['activity_end_at'] || $values['location'] === '')) {
                throw new InvalidArgumentException('Les dates et le lieu sont obligatoires pour publier une activité.');
            }
            if ($values['cover_path'] !== null && $form['cover_alt'] === '') {
                throw new InvalidArgumentException('Décrivez l’image de couverture avant de publier.');
            }
        }

        $now = $this->now();
        $values['status'] = $status;
        $values['updated_by'] = $actorId;
        $values['updated_at'] = $now;
        $values['published_at'] = $existing['published_at'] ?? ($status === 'PUBLISHED' ? $now : null);
        if ($existing === null) {
            $values['slug'] = $this->slug($form['title']);
            $values['created_by'] = $actorId;
            $values['created_at'] = $now;
            $id = $this->posts->insert($values);
        } else {
            $this->posts->update($id, $values);
        }
        return $this->requirePost($id);
    }

    public function withdraw(int $id, int $actorId): array
    {
        $this->assertActor($actorId);
        $this->requirePost($id);
        $this->posts->update($id, [
            'status' => 'DRAFT', 'updated_by' => $actorId, 'updated_at' => $this->now(),
        ]);
        return $this->requirePost($id);
    }

    private function formValues(?array $post): array
    {
        $values = array_fill_keys(self::INPUTS, '');
        if ($post === null) {
            return $values;
        }
        foreach (['type', 'title', 'summary', 'body', 'cover_alt', 'location'] as $field) {
            $values[$field] = (string)($post[$field] ?? '');
        }
        foreach (['start', 'end'] as $part) {
            $date = $post['activity_' . $part . '_at'] ?? '';
            $values['activity_' . $part . '_date'] = substr($date, 0, 10);
            if (!empty($post['activity_' . $part . '_time_set'])) {
                $values['activity_' . $part . '_time'] = substr($date, 11, 5);
            }
        }
        return $values;
    }

    private function activityDate(string $date, string $time, bool $end): ?DateTimeImmutable
    {
        if ($date === '') {
            if ($time !== '') {
                throw new InvalidArgumentException('Une heure nécessite une date d’activité.');
            }
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || (int)substr($date, 0, 4) < 1000
            || ($time !== '' && !preg_match('/^\d{2}:\d{2}$/D', $time))) {
            throw new InvalidArgumentException('Une date ou une heure d’activité est invalide.');
        }
        $value = $date . ' ' . ($time !== '' ? $time . ':00' : ($end ? '23:59:59' : '00:00:00'));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Africa/Dakar'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
            || $parsed->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException('Une date ou une heure d’activité est invalide.');
        }
        return $parsed;
    }

    private function now(): string
    {
        $now = ($this->clock)();
        if (!$now instanceof DateTimeImmutable) {
            throw new \LogicException('L’horloge du journal est invalide.');
        }
        return $now->setTimezone(new DateTimeZone('Africa/Dakar'))->format('Y-m-d H:i:s');
    }

    private function requirePost(int $id): array
    {
        $post = $id > 0 ? $this->posts->findById($id) : null;
        if ($post === null) {
            throw new OutOfBoundsException('Publication introuvable.');
        }
        return $post;
    }

    private function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Un compte de gestion est requis.');
        }
    }

    private function slug(string $title): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        $stem = preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii === false ? '' : $ascii));
        $stem = trim(substr($stem, 0, 160), '-');
        return ($stem ?: 'publication') . '-' . bin2hex(random_bytes(4));
    }
}
