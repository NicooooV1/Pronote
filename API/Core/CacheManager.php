<?php
declare(strict_types=1);

namespace API\Core;

/**
 * CacheManager — Cache multi-driver (file / Redis)
 *
 * Configurable via .env :
 *   CACHE_DRIVER=file   (file | redis)
 *   REDIS_HOST=127.0.0.1
 *   REDIS_PORT=6379
 *
 * Usage :
 *   $cache = app('cache');
 *   $cache->put('key', $value, 300);   // TTL en secondes
 *   $value = $cache->get('key');
 *   $value = $cache->remember('key', 300, fn() => expensiveCall());
 */
class CacheManager
{
	protected string $driver;
	protected string $filePath;
	protected ?\Redis $redis = null;

	public function __construct(?string $driver = null, ?string $basePath = null)
	{
		$this->driver = $driver ?? (function_exists('env') ? (env('CACHE_DRIVER', 'file') ?: 'file') : 'file');
		$this->filePath = ($basePath ?? (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2))) . '/storage/cache';

		if ($this->driver === 'redis') {
			$this->initRedis();
		} else {
			$this->ensureDirectory($this->filePath);
		}
	}

	/**
	 * Récupère une valeur du cache
	 *
	 * @param string $key
	 * @param mixed  $default Valeur par défaut si absent ou expiré
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		if ($this->driver === 'redis' && $this->redis) {
			return $this->redisGet($key, $default);
		}
		return $this->fileGet($key, $default);
	}

	/**
	 * Stocke une valeur en cache
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param int    $ttl Durée de vie en secondes (0 = permanent)
	 */
	public function put(string $key, mixed $value, int $ttl = 0): bool
	{
		if ($this->driver === 'redis' && $this->redis) {
			return $this->redisPut($key, $value, $ttl);
		}
		return $this->filePut($key, $value, $ttl);
	}

	/**
	 * Récupère ou calcule et met en cache
	 *
	 * @param string   $key
	 * @param int      $ttl
	 * @param callable $callback Fonction appelée si le cache est absent
	 * @return mixed
	 */
	public function remember(string $key, int $ttl, callable $callback): mixed
	{
		$value = $this->get($key);
		if ($value !== null) {
			return $value;
		}

		$value = $callback();
		$this->put($key, $value, $ttl);
		return $value;
	}

	/**
	 * Vérifie si une clé existe en cache (et n'est pas expirée)
	 */
	public function has(string $key): bool
	{
		return $this->get($key) !== null;
	}

	/**
	 * Supprime une clé du cache
	 */
	public function forget(string $key): bool
	{
		if ($this->driver === 'redis' && $this->redis) {
			return (bool) $this->redis->del($this->prefixKey($key));
		}

		$file = $this->filePath($key);
		return file_exists($file) && unlink($file);
	}

	/**
	 * Vide tout le cache
	 */
	public function flush(): bool
	{
		if ($this->driver === 'redis' && $this->redis) {
			// Supprime seulement les clés avec notre préfixe
			$keys = $this->redis->keys($this->prefixKey('*'));
			if (!empty($keys)) {
				$this->redis->del($keys);
			}
			return true;
		}

		// File driver : supprimer tous les fichiers .cache
		$files = glob($this->filePath . '/*.cache');
		if ($files === false) {
			return false;
		}
		foreach ($files as $file) {
			unlink($file);
		}
		return true;
	}

	/**
	 * Incrémente une valeur numérique
	 */
	public function increment(string $key, int $amount = 1): int
	{
		if ($this->driver === 'redis' && $this->redis) {
			return (int) $this->redis->incrBy($this->prefixKey($key), $amount);
		}

		$current = (int) ($this->get($key) ?? 0);
		$new = $current + $amount;
		$this->put($key, $new, 0);
		return $new;
	}

	/**
	 * Nettoie les entrées expirées (file driver uniquement)
	 */
	public function gc(): int
	{
		if ($this->driver !== 'file') {
			return 0; // Redis gère l'expiration automatiquement
		}

		$cleaned = 0;
		$files = glob($this->filePath . '/*.cache');
		if ($files === false) {
			return 0;
		}

		foreach ($files as $file) {
			$data = $this->readCacheFile($file);
			if ($data === null) {
				unlink($file);
				$cleaned++;
			}
		}

		return $cleaned;
	}

	// ─── File Driver ────────────────────────────────────────────────

	protected function fileGet(string $key, mixed $default): mixed
	{
		$file = $this->filePath($key);
		if (!file_exists($file)) {
			return $default;
		}

		$data = $this->readCacheFile($file);
		if ($data === null) {
			unlink($file); // Expiré
			return $default;
		}

		return $data;
	}

	protected function filePut(string $key, mixed $value, int $ttl): bool
	{
		$file = $this->filePath($key);
		$expiresAt = $ttl > 0 ? time() + $ttl : 0;

		$payload = serialize([
			'expires_at' => $expiresAt,
			'value' => $value,
		]);

		return file_put_contents($file, $payload, LOCK_EX) !== false;
	}

	protected function readCacheFile(string $file): mixed
	{
		$content = @file_get_contents($file);
		if ($content === false) {
			return null;
		}

		$data = @unserialize($content);
		if ($data === false || !is_array($data) || !array_key_exists('value', $data)) {
			return null;
		}

		// Vérifier expiration (0 = permanent)
		if ($data['expires_at'] > 0 && $data['expires_at'] < time()) {
			return null;
		}

		return $data['value'];
	}

	protected function filePath(string $key): string
	{
		return $this->filePath . '/' . md5($key) . '.cache';
	}

	protected function ensureDirectory(string $path): void
	{
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}
	}

	// ─── Redis Driver ───────────────────────────────────────────────

	protected function initRedis(): void
	{
		if (!extension_loaded('redis')) {
			// Fallback au file driver si Redis n'est pas installé
			$this->driver = 'file';
			$this->ensureDirectory($this->filePath);
			return;
		}

		try {
			$this->redis = new \Redis();
			$host = function_exists('env') ? (env('REDIS_HOST', '127.0.0.1') ?: '127.0.0.1') : '127.0.0.1';
			$port = (int) (function_exists('env') ? (env('REDIS_PORT', '6379') ?: 6379) : 6379);

			if (!$this->redis->connect($host, $port, 2.0)) {
				throw new \RuntimeException('Redis connection failed');
			}
		} catch (\Throwable $e) {
			error_log('CacheManager: Redis unavailable, falling back to file driver: ' . $e->getMessage());
			$this->redis = null;
			$this->driver = 'file';
			$this->ensureDirectory($this->filePath);
		}
	}

	protected function redisGet(string $key, mixed $default): mixed
	{
		$value = $this->redis->get($this->prefixKey($key));
		if ($value === false) {
			return $default;
		}
		return unserialize($value);
	}

	protected function redisPut(string $key, mixed $value, int $ttl): bool
	{
		$prefixed = $this->prefixKey($key);
		$serialized = serialize($value);

		if ($ttl > 0) {
			return $this->redis->setex($prefixed, $ttl, $serialized);
		}
		return $this->redis->set($prefixed, $serialized);
	}

	protected function prefixKey(string $key): string
	{
		return 'fronote:' . $key;
	}
}
