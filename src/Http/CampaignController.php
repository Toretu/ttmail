<?php
declare(strict_types=1);

namespace Mailings\Http;

use Mailings\Database;
use Mailings\Schema;
use Mailings\Validator;
use PDO;

/** Campaign products (price, limited/unlimited quantity), sales against them, and the sold/remaining summary. */
final class CampaignController
{
    public function __construct(private readonly CrudController $campaigns)
    {
    }

    /**
     * Deletes the campaign with its products and sales. The body must contain confirm_name equal to the campaign name.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function deleteCampaign(array $params): array
    {
        $id = (int) $params['id'];
        $campaign = $this->campaigns->find($id);
        $body = Json::body();
        if (!is_string($body['confirm_name'] ?? null) || trim($body['confirm_name']) !== $campaign['name']) {
            throw new HttpException(422, 'Validation failed', ['confirm_name' => 'must exactly match the campaign name']);
        }

        $pdo = Database::connect();
        $mailed = $pdo->prepare("SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status IN ('sending','sent','failed','unknown')");
        $mailed->execute([$id]);
        if ($campaign['send_state'] === 'sending' || (int) $mailed->fetchColumn() > 0) {
            throw new HttpException(409, 'Mail has been sent for this campaign, so it cannot be deleted. Close it instead (status closed) to keep the history.');
        }
        $pdo->beginTransaction();
        try {
            $sales = $pdo->prepare('DELETE s FROM product_sales s JOIN campaign_products p ON p.id = s.product_id WHERE p.campaign_id = ?');
            $sales->execute([$id]);
            $products = $pdo->prepare('DELETE FROM campaign_products WHERE campaign_id = ?');
            $products->execute([$id]);
            $pdo->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $this->rollBack($pdo);
            throw $e;
        }
        return ['data' => ['deleted' => $id, 'sales_deleted' => $sales->rowCount(), 'products_deleted' => $products->rowCount()]];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function products(array $params): array
    {
        $this->campaigns->find((int) $params['id']);
        $stmt = Database::connect()->prepare('SELECT * FROM campaign_products WHERE campaign_id = ? ORDER BY sort_order, id');
        $stmt->execute([(int) $params['id']]);
        return ['data' => $stmt->fetchAll()];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function createProduct(array $params): array
    {
        $campaignId = (int) $params['id'];
        $this->campaigns->find($campaignId);
        $data = Validator::clean(Json::body(), Schema::productRules(), false) + ['campaign_id' => $campaignId];
        $data += ['quantity_available' => null];
        $pdo = Database::connect();
        try {
            $pdo->prepare('INSERT INTO campaign_products (campaign_id, name, description, price, quantity_available, sort_order)
                VALUES (:campaign_id, :name, :description, :price, :quantity_available, :sort_order)')
                ->execute($data + ['description' => null]);
        } catch (\PDOException $e) {
            throw $this->mapDuplicate($e, 'A product with this name already exists in the campaign');
        }
        return ['data' => $this->product((int) $pdo->lastInsertId()), '_status' => 201];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function updateProduct(array $params): array
    {
        $id = (int) $params['id'];
        $data = Validator::clean(Json::body(), Schema::productRules(), true);
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $product = $this->lockProduct($id);
            if (array_key_exists('quantity_available', $data) && $data['quantity_available'] !== null
                && $data['quantity_available'] < $this->used($id, null)) {
                throw new HttpException(409, 'Capacity cannot be lower than the quantity already sold or reserved (' . $this->used($id, null) . ')');
            }
            $set = implode(', ', array_map(static fn (string $c): string => "{$c} = :{$c}", array_keys($data)));
            $pdo->prepare("UPDATE campaign_products SET {$set} WHERE id = :id")->execute($data + ['id' => $product['id']]);
            $pdo->commit();
        } catch (\PDOException $e) {
            $this->rollBack($pdo);
            throw $this->mapDuplicate($e, 'A product with this name already exists in the campaign');
        } catch (\Throwable $e) {
            $this->rollBack($pdo);
            throw $e;
        }
        return ['data' => $this->product($id)];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function sales(array $params): array
    {
        $campaignId = (int) $params['id'];
        $this->campaigns->find($campaignId);
        $sql = 'SELECT s.*, p.name AS product_name, c.name AS company_name
            FROM product_sales s
            JOIN campaign_products p ON p.id = s.product_id
            JOIN companies c ON c.id = s.company_id
            WHERE p.campaign_id = :campaign';
        $args = ['campaign' => $campaignId];
        foreach (['status', 'product_id', 'company_id'] as $filter) {
            if (isset($_GET[$filter]) && is_string($_GET[$filter]) && $_GET[$filter] !== '') {
                $sql .= " AND s.{$filter} = :{$filter}";
                $args[$filter] = $_GET[$filter];
            }
        }
        $stmt = Database::connect()->prepare($sql . ' ORDER BY s.id DESC LIMIT 500');
        $stmt->execute($args);
        return ['data' => $stmt->fetchAll()];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function createSale(array $params): array
    {
        $campaignId = (int) $params['id'];
        $this->campaigns->find($campaignId);
        $data = Validator::clean(Json::body(), Schema::saleCreateRules(), false);

        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $product = $this->lockProduct($data['product_id']);
            if ((int) $product['campaign_id'] !== $campaignId) {
                throw new HttpException(422, 'Validation failed', ['product_id' => 'does not belong to this campaign']);
            }
            if ($data['status'] !== 'cancelled') {
                $this->assertCapacity($product, (int) $data['quantity'], null);
            }
            $pdo->prepare('INSERT INTO product_sales (product_id, company_id, quantity, unit_price, status, notes)
                VALUES (:product_id, :company_id, :quantity, :unit_price, :status, :notes)')
                ->execute([
                    'product_id' => $data['product_id'],
                    'company_id' => $data['company_id'],
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'] ?? $product['price'],
                    'status' => $data['status'],
                    'notes' => $data['notes'] ?? null,
                ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\PDOException $e) {
            $this->rollBack($pdo);
            if (($e->errorInfo[1] ?? 0) === 1452) {
                throw new HttpException(422, 'Validation failed', ['company_id' => 'refers to a company that does not exist']);
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->rollBack($pdo);
            throw $e;
        }
        return ['data' => $this->sale($id), '_status' => 201];
    }

    /** @param array<string,string> $params @return array<string,mixed> */
    public function updateSale(array $params): array
    {
        $id = (int) $params['id'];
        $data = Validator::clean(Json::body(), Schema::saleUpdateRules(), true);

        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $current = $this->sale($id);
            $product = $this->lockProduct((int) $current['product_id']);
            $next = $data + $current;
            if ($next['status'] !== 'cancelled') {
                $this->assertCapacity($product, (int) $next['quantity'], $id);
            }
            $set = implode(', ', array_map(static fn (string $c): string => "{$c} = :{$c}", array_keys($data)));
            $pdo->prepare("UPDATE product_sales SET {$set} WHERE id = :id")->execute($data + ['id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $this->rollBack($pdo);
            throw $e;
        }
        return ['data' => $this->sale($id)];
    }

    /**
     * Per product: capacity, sold, reserved, remaining (null = unlimited) and revenue; plus campaign totals.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    public function summary(array $params): array
    {
        $campaignId = (int) $params['id'];
        $campaign = $this->campaigns->find($campaignId);
        $stmt = Database::connect()->prepare(
            "SELECT p.id, p.name, p.price, p.quantity_available,
                COALESCE(SUM(CASE WHEN s.status = 'sold' THEN s.quantity END), 0) AS sold,
                COALESCE(SUM(CASE WHEN s.status = 'reserved' THEN s.quantity END), 0) AS reserved,
                COALESCE(SUM(CASE WHEN s.status = 'sold' THEN s.quantity * s.unit_price END), 0) AS revenue_sold,
                COALESCE(SUM(CASE WHEN s.status = 'reserved' THEN s.quantity * s.unit_price END), 0) AS revenue_reserved
             FROM campaign_products p
             LEFT JOIN product_sales s ON s.product_id = p.id
             WHERE p.campaign_id = ?
             GROUP BY p.id, p.name, p.price, p.quantity_available, p.sort_order
             ORDER BY p.sort_order, p.id"
        );
        $stmt->execute([$campaignId]);

        $totals = ['sold' => 0, 'reserved' => 0, 'revenue_sold' => 0.0, 'revenue_reserved' => 0.0];
        $products = [];
        foreach ($stmt->fetchAll() as $row) {
            $capacity = $row['quantity_available'] === null ? null : (int) $row['quantity_available'];
            $sold = (int) $row['sold'];
            $reserved = (int) $row['reserved'];
            $products[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'capacity' => $capacity,
                'sold' => $sold,
                'reserved' => $reserved,
                'remaining' => $capacity === null ? null : max(0, $capacity - $sold - $reserved),
                'revenue_sold' => number_format((float) $row['revenue_sold'], 2, '.', ''),
                'revenue_reserved' => number_format((float) $row['revenue_reserved'], 2, '.', ''),
            ];
            $totals['sold'] += $sold;
            $totals['reserved'] += $reserved;
            $totals['revenue_sold'] += (float) $row['revenue_sold'];
            $totals['revenue_reserved'] += (float) $row['revenue_reserved'];
        }
        $totals['revenue_sold'] = number_format($totals['revenue_sold'], 2, '.', '');
        $totals['revenue_reserved'] = number_format($totals['revenue_reserved'], 2, '.', '');

        return ['data' => ['campaign' => $campaign, 'products' => $products, 'totals' => $totals]];
    }

    /** @return array<string,mixed> */
    private function product(int $id): array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM campaign_products WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new HttpException(404, 'Product not found');
    }

    /** @return array<string,mixed> */
    private function sale(int $id): array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM product_sales WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new HttpException(404, 'Sale not found');
    }

    /** Locks the product row so concurrent sales cannot oversell it. @return array<string,mixed> */
    private function lockProduct(int $id): array
    {
        $stmt = Database::connect()->prepare('SELECT * FROM campaign_products WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new HttpException(404, 'Product not found');
    }

    private function used(int $productId, ?int $excludeSale): int
    {
        $stmt = Database::connect()->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM product_sales WHERE product_id = ? AND status IN ('reserved','sold') AND id <> ?"
        );
        $stmt->execute([$productId, $excludeSale ?? 0]);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $product */
    private function assertCapacity(array $product, int $quantity, ?int $excludeSale): void
    {
        if ($product['quantity_available'] === null) {
            return;
        }
        $left = (int) $product['quantity_available'] - $this->used((int) $product['id'], $excludeSale);
        if ($quantity > $left) {
            throw new HttpException(409, $left <= 0 ? 'This product is sold out' : "Only {$left} left of this product");
        }
    }

    private function rollBack(PDO $pdo): void
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    private function mapDuplicate(\PDOException $e, string $message): \Throwable
    {
        return ($e->errorInfo[1] ?? 0) === 1062 ? new HttpException(409, $message) : $e;
    }
}
