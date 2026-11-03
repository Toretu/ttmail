<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;
use Mailings\Validator;
use PDOException;

/** Generic list/get/create/update for a table. Column names come only from the fixed $rules keys. */
final class CrudController
{
    /**
     * @param array<string,array<string,mixed>> $rules
     * @param list<string> $searchColumns
     * @param list<string> $filterColumns
     */
    public function __construct(
        private readonly string $table,
        private readonly array $rules,
        private readonly array $searchColumns,
        private readonly array $filterColumns = [],
        private readonly string $label = 'Record',
    ) {
    }

    /** @return array<string,mixed> */
    public function list(): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $like = [];
            foreach ($this->searchColumns as $i => $column) {
                $like[] = "{$column} LIKE :q{$i}";
                $params["q{$i}"] = '%' . addcslashes($q, '%_\\') . '%';
            }
            $where[] = '(' . implode(' OR ', $like) . ')';
        }
        foreach ($this->filterColumns as $column) {
            if (isset($_GET[$column]) && is_string($_GET[$column]) && $_GET[$column] !== '') {
                $where[] = "{$column} = :f_{$column}";
                $params["f_{$column}"] = $_GET[$column];
            }
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        $pdo = Database::connect();
        $count = $pdo->prepare("SELECT COUNT(*) FROM {$this->table}{$clause}");
        $count->execute($params);

        $stmt = $pdo->prepare("SELECT * FROM {$this->table}{$clause} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(),
            'meta' => ['total' => (int) $count->fetchColumn(), 'limit' => $limit, 'offset' => $offset],
        ];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function get(array $params): array
    {
        return ['data' => $this->find((int) $params['id'])];
    }

    /** @return array<string,mixed> */
    public function create(): array
    {
        $data = Validator::clean(Json::body(), $this->rules, false);
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );
        $pdo = Database::connect();
        $this->guard(static fn () => $pdo->prepare($sql)->execute($data));
        return ['data' => $this->find((int) $pdo->lastInsertId()), '_status' => 201];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function update(array $params): array
    {
        $id = (int) $params['id'];
        $this->find($id);
        $data = Validator::clean(Json::body(), $this->rules, true);
        $set = implode(', ', array_map(static fn (string $c): string => "{$c} = :{$c}", array_keys($data)));
        $data['id'] = $id;
        $sql = "UPDATE {$this->table} SET {$set} WHERE id = :id";
        $this->guard(static fn () => Database::connect()->prepare($sql)->execute($data));
        return ['data' => $this->find($id)];
    }

    /** @return array<string,mixed> */
    public function find(int $id): array
    {
        $stmt = Database::connect()->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new HttpException(404, "{$this->label} not found");
        }
        return $row;
    }

    private function guard(callable $action): void
    {
        try {
            $action();
        } catch (PDOException $e) {
            $code = $e->errorInfo[1] ?? 0;
            if ($code === 1062) {
                throw new HttpException(409, 'A record with the same unique value already exists');
            }
            if ($code === 1452) {
                throw new HttpException(422, 'Validation failed', ['reference' => 'refers to a record that does not exist']);
            }
            throw $e;
        }
    }
}
