<?php

namespace Amea\Repository;

use PDO;

class SliderRepository
{
    public function __construct(private PDO $conn) {}

    public function getActiveImages(): array
    {
        $stmt = $this->conn->query("SELECT * FROM slider_images WHERE is_active = 1 ORDER BY display_order ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
