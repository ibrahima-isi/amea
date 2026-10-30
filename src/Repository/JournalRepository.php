<?php
namespace Amea\Repository;

use PDO;
use InvalidArgumentException;

class JournalRepository
{
    private const FIELDS = ['type','status','slug','title','summary','body','cover_path','cover_mime','cover_alt','activity_start_at','activity_end_at','activity_start_time_set','activity_end_time_set','location','published_at','created_by','updated_by','created_at','updated_at'];

    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        return $this->one('SELECT * FROM journal_posts WHERE id = ?', [$id]);
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->one('SELECT * FROM journal_posts WHERE status = ? AND slug = ?', ['PUBLISHED', $slug]);
    }

    private function one(string $sql, array $params): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAdminPage(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $where = []; $params = [];
        foreach (['status' => ['DRAFT','PUBLISHED'], 'type' => ['ACTIVITY','REPORT','ANNOUNCEMENT']] as $field => $allowed) {
            if (isset($filters[$field]) && in_array($filters[$field], $allowed, true)) {
                $where[] = "$field = ?"; $params[] = $filters[$field];
            }
        }
        return $this->page($where ? implode(' AND ', $where) : '1=1', $params, 'updated_at DESC, id DESC', $page, $perPage);
    }

    public function findPublicPage(string $view, int $page, int $perPage, string $now): array
    {
        $where = 'status = ?'; $params = ['PUBLISHED']; $order = 'published_at DESC, id DESC';
        switch ($view) {
            case 'upcoming': case 'archives':
                $where .= ' AND type = ? AND activity_end_at ' . ($view === 'upcoming' ? '>=' : '<') . ' ?';
                $params[] = 'ACTIVITY'; $params[] = $now;
                $order = $view === 'upcoming' ? 'activity_start_at ASC, id ASC' : 'activity_end_at DESC, id DESC';
                break;
            case 'reports': case 'announcements':
                $where .= ' AND type = ?'; $params[] = $view === 'reports' ? 'REPORT' : 'ANNOUNCEMENT';
                break;
            case 'all': break;
            default: throw new InvalidArgumentException('Vue du journal invalide.');
        }
        return $this->page($where, $params, $order, $page, $perPage);
    }

    private function page(string $where, array $params, string $order, int $page, int $perPage): array
    {
        $perPage = max(1, min(50, $perPage));
        $page = max(1, min($page, intdiv(PHP_INT_MAX, $perPage)));
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM journal_posts WHERE $where");
        $statement->execute($params); $total = (int) $statement->fetchColumn();
        $statement = $this->pdo->prepare("SELECT * FROM journal_posts WHERE $where ORDER BY $order LIMIT ? OFFSET ?");
        foreach ($params as $index => $value) { $statement->bindValue($index + 1, $value); }
        $statement->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
        $statement->bindValue(count($params) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        return ['items' => $statement->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function findRecentPublished(int $limit = 3): array
    {
        return $this->page('status = ?', ['PUBLISHED'], 'published_at DESC, id DESC', 1, $limit)['items'];
    }

    public function insert(array $values): int
    {
        $values = array_intersect_key($values, array_flip(self::FIELDS));
        $fields = array_keys($values);
        $statement = $this->pdo->prepare('INSERT INTO journal_posts (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')');
        $statement->execute(array_values($values));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $values): bool
    {
        $values = array_intersect_key($values, array_flip(self::FIELDS));
        if (!$values) { return false; }
        $assignments = array_map(static fn(string $field): string => "$field = ?", array_keys($values));
        $statement = $this->pdo->prepare('UPDATE journal_posts SET ' . implode(',', $assignments) . ' WHERE id = ?');
        return $statement->execute([...array_values($values), $id]);
    }
}
