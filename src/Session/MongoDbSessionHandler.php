<?php

namespace App\Session;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\BSON\UTCDateTime;

/**
 * Handler de session personnalisé utilisant MongoDB.
 *
 * Chaque session est un document dans la collection "sessions" :
 * {
 *   _id: "session_id",
 *   data: "données sérialisées",
 *   expires_at: ISODate(...)
 * }
 *
 * MongoDB supprime automatiquement les sessions expirées
 * grâce à un index TTL sur le champ expires_at.
 */
class MongoDbSessionHandler implements \SessionHandlerInterface
{
    private Collection $collection;
    private int $ttl;

    public function __construct(
        Client $client,
        string $dbName,
        int $ttl = 3600,
        string $collectionName = 'sessions',
    ) {
        $this->collection = $client->selectDatabase($dbName)->selectCollection($collectionName);
        $this->ttl = $ttl;

        // Crée l'index TTL automatiquement si inexistant
        // MongoDB supprimera les documents expirés automatiquement
        $this->ensureTtlIndex();
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $document = $this->collection->findOne(
            ['_id' => $id],
            ['projection' => ['data' => 1, 'expires_at' => 1]]
        );

        if ($document === null) {
            return '';
        }

        // Vérifie manuellement que la session n'est pas expirée
        // (l'index TTL MongoDB supprime avec un délai de ~60s)
        $expiresAt = $document['expires_at'] ?? null;
        if ($expiresAt instanceof UTCDateTime) {
            $expiresAtDateTime = $expiresAt->toDateTime();
            if ($expiresAtDateTime < new \DateTime()) {
                $this->destroy($id);
                return '';
            }
        }

        return $document['data'] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $expiresAt = new UTCDateTime((time() + $this->ttl) * 1000);

        $this->collection->updateOne(
            ['_id' => $id],
            [
                '$set' => [
                    'data'       => $data,
                    'expires_at' => $expiresAt,
                    'updated_at' => new UTCDateTime(),
                ],
                '$setOnInsert' => [
                    'created_at' => new UTCDateTime(),
                ],
            ],
            ['upsert' => true]
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->collection->deleteOne(['_id' => $id]);
        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        // MongoDB gère la suppression via l'index TTL
        // Cette méthode est appelée par PHP mais n'est pas nécessaire ici
        $result = $this->collection->deleteMany([
            'expires_at' => ['$lt' => new UTCDateTime(time() * 1000)],
        ]);

        return $result->getDeletedCount();
    }

    /**
     * Crée l'index TTL sur expires_at si inexistant.
     * expireAfterSeconds: 0 = MongoDB supprime le document quand expires_at est dépassé.
     */
    private function ensureTtlIndex(): void
    {
        try {
            $this->collection->createIndex(
                ['expires_at' => 1],
                ['expireAfterSeconds' => 0, 'name' => 'session_ttl_idx']
            );
        } catch (\Exception) {
            // L'index existe déjà, on ignore
        }
    }
}
