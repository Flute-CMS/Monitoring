<?php

namespace Flute\Modules\Monitoring\database\Entities;

use Cycle\ActiveRecord\ActiveRecord;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Flute\Core\Database\Entities\Server;

#[Entity]
class ServerStatus extends ActiveRecord
{
    #[Column(type: "primary")]
    public int $id;

    #[BelongsTo(target: Server::class)]
    public Server $server;

    #[Column(type: "boolean", default: false)]
    public bool $online = false;

    #[Column(type: "int", default: 0)]
    public int $players = 0;

    #[Column(type: "int", default: 0)]
    public int $max_players = 0;

    #[Column(type: "string", nullable: true)]
    public ?string $map = null;

    #[Column(type: "string", nullable: true)]
    public ?string $game = null;

    #[Column(type: "json", nullable: true)]
    public ?string $players_data = null;

    #[Column(type: "json", nullable: true)]
    public ?string $additional = null;

    #[Column(type: "timestamp", default: "CURRENT_TIMESTAMP")]
    public \DateTimeImmutable $updated_at;

    public function __construct()
    {
        $this->updated_at = new \DateTimeImmutable('now');
    }

    /**
     * Get players data as array
     */
    public function getPlayersData() : array
    {
        return json_decode($this->players_data ?? '[]', true) ?: [];
    }

    /**
     * Set players data from array
     */
    public function setPlayersData(array $data) : void
    {
        $this->players_data = json_encode($data);
    }

    public function getAdditional() : array
    {
        return json_decode($this->additional ?? '[]', true) ?: [];
    }

    public function setAdditional(array $data) : void
    {
        $this->additional = json_encode($data);
    }

    /**
     * Update the updated_at timestamp
     */
    public function touch() : void
    {
        $timezone = new \DateTimeZone(config('app.timezone', 'UTC'));
        $this->updated_at = new \DateTimeImmutable('now', $timezone);
    }
}