<?php
/**
 * Samrajya Mens Wear - Unified Database Adapter
 * Seamlessly interfaces with Cloud Firestore or Local Fallback Store.
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted');
}

require_once __DIR__ . '/firestore.php';

class DatabaseManager {
    private static ?DatabaseManager $instance = null;
    private ?FirestoreClient $firestore = null;
    private ?PDO $sqlite = null;
    private string $activeDriver = 'local'; // 'firestore' or 'local'
    private array $config;
    private string $dbPath;

    private function __construct(array $config) {
        $this->config = $config;
        $this->dbPath = APP_ROOT . '/storage/data/samrajya_db.sqlite';

        // Initialize SQLite local store (always available as resilient store/cache)
        $this->initSqlite();

        // Check if Firebase Service Account credentials are valid
        $fbConfig = $config['firebase_admin'] ?? [];
        if (!empty($fbConfig['service_account_path']) && file_exists($fbConfig['service_account_path'])) {
            try {
                $client = new FirestoreClient($fbConfig);
                if ($client->isConfigured()) {
                    $this->firestore = $client;
                    $this->activeDriver = 'firestore';
                }
            } catch (Exception $e) {
                error_log("Firebase initialization fallback: " . $e->getMessage());
                $this->activeDriver = 'local';
            }
        }
    }

    public static function getInstance(array $config = []): DatabaseManager {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function getDriver(): string {
        return $this->activeDriver;
    }

    public function isFirestore(): bool {
        return $this->activeDriver === 'firestore' && $this->firestore !== null;
    }

    public function getFirestoreClient(): ?FirestoreClient {
        return $this->firestore;
    }

    /**
     * Initializes local SQLite document-store schema
     */
    private function initSqlite(): void {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->sqlite = new PDO('sqlite:' . $this->dbPath);
        $this->sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Document table mirroring Firestore collection/doc paradigm
        $this->sqlite->exec("CREATE TABLE IF NOT EXISTS firestore_docs (
            collection TEXT NOT NULL,
            doc_id TEXT NOT NULL,
            data_json TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            PRIMARY KEY (collection, doc_id)
        )");

        $this->sqlite->exec("CREATE TABLE IF NOT EXISTS counters (
            id TEXT PRIMARY KEY,
            current INTEGER NOT NULL DEFAULT 0,
            updated_at TEXT NOT NULL
        )");

        $this->sqlite->exec("CREATE INDEX IF NOT EXISTS idx_coll ON firestore_docs (collection)");
        $this->sqlite->exec("CREATE INDEX IF NOT EXISTS idx_coll_updated ON firestore_docs (collection, updated_at)");
    }

    /**
     * Get document by collection and ID (Instant local read, fallback to cloud)
     */
    public function get(string $collection, string $docId): ?array {
        $stmt = $this->sqlite->prepare("SELECT data_json FROM firestore_docs WHERE collection = ? AND doc_id = ?");
        $stmt->execute([$collection, $docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $data = json_decode($row['data_json'], true);
            $data['__id'] = $docId;
            return $data;
        }

        // If not found in local cache, check Cloud Firestore
        if ($this->isFirestore()) {
            try {
                $doc = $this->firestore->getDocument($collection, $docId);
                if ($doc) {
                    $now = date('c');
                    $this->sqlite->prepare("INSERT OR REPLACE INTO firestore_docs (collection, doc_id, data_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?)")
                        ->execute([$collection, $docId, json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $now]);
                    return $doc;
                }
            } catch (Exception $e) {
                error_log("Firestore get error: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Set / upsert document
     */
    public function set(string $collection, string $docId, array $data, bool $merge = false): array {
        $now = date('c');

        // Always save to SQLite for local reliability/backup
        $existing = null;
        if ($merge) {
            $existing = $this->get($collection, $docId);
            if ($existing) {
                unset($existing['__id'], $existing['__createTime'], $existing['__updateTime']);
                $data = array_replace_recursive($existing, $data);
            }
        }

        $createdAt = $existing['createdAt'] ?? $now;
        $data['updatedAt'] = $now;
        if (!isset($data['createdAt'])) {
            $data['createdAt'] = $createdAt;
        }

        $stmt = $this->sqlite->prepare("INSERT INTO firestore_docs (collection, doc_id, data_json, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?) 
            ON CONFLICT(collection, doc_id) DO UPDATE SET 
            data_json = excluded.data_json, 
            updated_at = excluded.updated_at");
        $stmt->execute([$collection, $docId, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $createdAt, $now]);

        // Sync to Cloud Firestore if connected
        if ($this->isFirestore()) {
            try {
                $this->firestore->setDocument($collection, $docId, $data, $merge);
            } catch (Exception $e) {
                error_log("Firestore set error: " . $e->getMessage());
            }
        }

        $data['__id'] = $docId;
        return $data;
    }

    /**
     * Delete document
     */
    public function delete(string $collection, string $docId): bool {
        $stmt = $this->sqlite->prepare("DELETE FROM firestore_docs WHERE collection = ? AND doc_id = ?");
        $stmt->execute([$collection, $docId]);

        if ($this->isFirestore()) {
            try {
                $this->firestore->deleteDocument($collection, $docId);
            } catch (Exception $e) {
                error_log("Firestore delete error: " . $e->getMessage());
            }
        }
        return true;
    }

    /**
     * Query all documents from collection with optional sorting & filtering
     * Performs ultra-fast local SQLite query; syncs on write so data is always identical to Cloud.
     */
    public function query(string $collection, ?callable $filter = null, ?string $sortField = null, string $sortDir = 'ASC'): array {
        // Query local SQLite for instant, sub-millisecond response
        $stmt = $this->sqlite->prepare("SELECT doc_id, data_json FROM firestore_docs WHERE collection = ? ORDER BY updated_at DESC");
        $stmt->execute([$collection]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $item = json_decode($row['data_json'], true);
            if (is_array($item)) {
                $item['__id'] = $row['doc_id'];
                if ($filter === null || $filter($item)) {
                    $results[] = $item;
                }
            }
        }

        // If local is completely empty and Firestore is connected, seed local from Firestore
        if (empty($results) && $this->isFirestore()) {
            try {
                $cloudDocs = $this->firestore->listDocuments($collection);
                if (!empty($cloudDocs)) {
                    $now = date('c');
                    foreach ($cloudDocs as $cDoc) {
                        $cId = $cDoc['__id'] ?? ($cDoc['id'] ?? uniqid());
                        $this->sqlite->prepare("INSERT OR REPLACE INTO firestore_docs (collection, doc_id, data_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$collection, $cId, json_encode($cDoc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $now]);
                    }
                    if ($filter !== null) {
                        $cloudDocs = array_values(array_filter($cloudDocs, $filter));
                    }
                    $results = $cloudDocs;
                }
            } catch (Exception $e) {
                error_log("Firestore query fallback: " . $e->getMessage());
            }
        }

        if ($sortField !== null && !empty($results)) {
            usort($results, function($a, $b) use ($sortField, $sortDir) {
                $va = $a[$sortField] ?? null;
                $vb = $b[$sortField] ?? null;
                if ($va == $vb) return 0;
                if (strtolower($sortDir) === 'desc') {
                    return ($va < $vb) ? 1 : -1;
                }
                return ($va > $vb) ? 1 : -1;
            });
        }

        return $results;
    }

    /**
     * Concurrency-safe Atomic Counter for Invoices and Returns
     */
    public function nextInvoiceNumber(string $type = 'gst'): string {
        $prefix = ($type === 'gst') 
            ? ($this->config['shop']['gst_invoice_prefix'] ?? 'SMW-GST-') 
            : ($this->config['shop']['nongst_invoice_prefix'] ?? 'SMW-BILL-');

        $yearMonth = date('Ym');
        $counterKey = "invoices_{$type}_{$yearMonth}";

        $nextVal = 1;

        if ($this->isFirestore()) {
            try {
                $nextVal = $this->firestore->incrementCounter($counterKey, 1);
            } catch (Exception $e) {
                error_log("Firestore counter fallback: " . $e->getMessage());
                $nextVal = $this->incrementLocalCounter($counterKey);
            }
        } else {
            $nextVal = $this->incrementLocalCounter($counterKey);
        }

        $padded = str_pad((string)$nextVal, 4, '0', STR_PAD_LEFT);
        return "{$prefix}{$yearMonth}-{$padded}";
    }

    public function nextReturnNumber(): string {
        $prefix = $this->config['shop']['return_prefix'] ?? 'SMW-RET-';
        $yearMonth = date('Ym');
        $counterKey = "returns_{$yearMonth}";

        $nextVal = $this->incrementLocalCounter($counterKey);
        $padded = str_pad((string)$nextVal, 4, '0', STR_PAD_LEFT);
        return "{$prefix}{$yearMonth}-{$padded}";
    }

    private function incrementLocalCounter(string $key, int $delta = 1): int {
        $this->sqlite->beginTransaction();
        try {
            $stmt = $this->sqlite->prepare("INSERT INTO counters (id, current, updated_at) VALUES (?, ?, ?)
                ON CONFLICT(id) DO UPDATE SET current = counters.current + ?, updated_at = ?");
            $now = date('c');
            $stmt->execute([$key, $delta, $now, $delta, $now]);

            $fetch = $this->sqlite->prepare("SELECT current FROM counters WHERE id = ?");
            $fetch->execute([$key]);
            $val = (int)$fetch->fetchColumn();
            $this->sqlite->commit();
            return $val;
        } catch (Exception $e) {
            $this->sqlite->rollBack();
            throw $e;
        }
    }

    /**
     * Synchronize all local documents to Cloud Firestore
     */
    public function syncLocalToFirestore(): array {
        if (!$this->isFirestore()) {
            throw new Exception("Firebase Service Account is not configured or connected.");
        }

        $stmt = $this->sqlite->query("SELECT collection, doc_id, data_json FROM firestore_docs");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $row) {
            try {
                $data = json_decode($row['data_json'], true);
                if (is_array($data)) {
                    $this->firestore->setDocument($row['collection'], $row['doc_id'], $data);
                    $synced++;
                }
            } catch (Exception $e) {
                $failed++;
                $errors[] = "Error syncing {$row['collection']}/{$row['doc_id']}: " . $e->getMessage();
            }
        }

        return [
            'total' => count($rows),
            'synced' => $synced,
            'failed' => $failed,
            'errors' => $errors
        ];
    }
}

/**
 * Global helper to access DatabaseManager
 */
function db(): DatabaseManager {
    global $config;
    return DatabaseManager::getInstance($config ?? []);
}
