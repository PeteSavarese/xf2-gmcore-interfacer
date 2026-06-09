<?php

namespace Shoutbox\Repository;

use XF;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Repository for managing shoutbox messages.
 *
 * Handles message queries, caching of the latest message ID for long-polling,
 * and generation counter tracking to detect modifications (creates, edits, deletes).
 */
class Message extends Repository
{
	/** @var string Cache namespace identifier for shoutbox addon */
	protected const CACHE_ADDON_ID = 'Shoutbox';

	/** @var string Cache key for storing the highest message ID */
	protected const CACHE_LAST_ID_KEY = 'last_id';

	/** @var string Cache key for generation counter (increments on any modification) */
	protected const CACHE_GENERATION_KEY = 'generation';

	/**
	 * Find the most recent shoutbox messages in reverse chronological order.
	 *
	 * @param int $limit Maximum number of messages to retrieve (default: 50)
	 * @return Finder Finder instance configured to fetch latest messages with User relation
	 */
	public function findLatest(int $limit = 50): Finder
	{
		return $this->finder('Shoutbox:Message')
			->with('User')
			->order('id', 'DESC')
			->limit($limit);
	}

	/**
	 * Find shoutbox messages with IDs greater than a specified ID.
	 *
	 * Used for incremental polling.
   * Fetches only new messages since last time polled
	 *
	 * @param int $afterId Only fetch messages with ID > this value
	 * @param int $limit Maximum number of messages to retrieve (default: 50)
	 * @return Finder Finder instance configured to fetch messages after the given ID
	 */
	public function findAfterId(int $afterId, int $limit = 50): Finder
	{
		return $this->finder('Shoutbox:Message')
			->with('User')
			->where('id', '>', $afterId)
			->order('id')
			->limit($limit);
	}

	/**
	 * Find shoutbox messages with IDs less than a specified ID.
	 * Used for loading older history when the user scrolls to the top.
	 *
	 * @param int $beforeId Only fetch messages with ID < this value
	 * @param int $limit Maximum number of messages to retrieve (default: 50)
	 * @return Finder Finder instance configured to fetch messages before the given ID
	 */
	public function findBeforeId(int $beforeId, int $limit = 50): Finder
	{
		return $this->finder('Shoutbox:Message')
			->with('User')
			->where('id', '<', $beforeId)
			->order('id', 'DESC')
			->limit($limit);
	}

	/**
	 * Retrieve the highest message ID from cache.
	 * Used for long-polling optimization. If cache is empty,
	 * seeds the cache with the current MAX(id) from the db.
	 *
	 * @return int Highest message ID currently in shoutbox
	 */
	public function getLastIdFromCache(): int
	{
		$cache = XF::app()->simpleCache();
		$value = $cache->getValue(self::CACHE_ADDON_ID, self::CACHE_LAST_ID_KEY);
		if ($value !== null) {
			return (int)$value;
		}

		// No cache rn. Seed from DB so long-poll has baseline
		$maxId = (int)XF::db()->fetchOne('SELECT MAX(id) FROM shoutbox_message');
		$cache->setValue(self::CACHE_ADDON_ID, self::CACHE_LAST_ID_KEY, $maxId);

		return $maxId;
	}

	/**
	 * Update the cached highest message ID.
	 *
	 * @param int $lastId New highest message ID to cache
	 * @return void
	 */
	public function setLastIdCache(int $lastId): void
	{
		XF::app()->simpleCache()->setValue(self::CACHE_ADDON_ID, self::CACHE_LAST_ID_KEY, $lastId);
	}

	/**
	 * Retrieve the current generation counter from cache.
	 *
	 * The generation counter increments on any modification (create, edit, delete).
	 * Clients track this to detect when a full refresh is needed (e.g., message deletions).
	 * If cache is empty, initializes to 1
	 *
	 * @return int Current generation counter value
	 */
	public function getGenerationFromCache(): int
	{
		$cache = XF::app()->simpleCache();
		$value = $cache->getValue(self::CACHE_ADDON_ID, self::CACHE_GENERATION_KEY);
		if ($value !== null) {
			return (int)$value;
		}

		// Initialize generation counter
		$cache->setValue(self::CACHE_ADDON_ID, self::CACHE_GENERATION_KEY, 1);

		return 1;
	}

	/**
	 * Increment the generation counter to signal clients that a modification occurred.
	 *
	 * Should be called after:
	 * - Creating a new message
	 * - Editing an existing message
	 * - Deleting a message
	 *
	 * This forces clients to do a full refresh on their next poll, ensuring
	 * deletions and edits are reflected in real-time.
	 *
	 * @return int New generation counter value
	 */
	public function bumpGeneration(): int
	{
		$cache = XF::app()->simpleCache();
		$current = $this->getGenerationFromCache();
		$next = $current + 1;
		$cache->setValue(self::CACHE_ADDON_ID, self::CACHE_GENERATION_KEY, $next);

		return $next;
	}
}
