<?php

declare(strict_types=1);

namespace Bga\GameFramework;

enum StateType: string
{
    case ACTIVE_PLAYER = 'activeplayer';
    case MULTIPLE_ACTIVE_PLAYER = 'multipleactiveplayer';
    case PRIVATE = 'private';
    case GAME = 'game';
    case MANAGER = 'manager';
}

abstract class Table
{
    public function __construct()
    {
    }

    protected function initGameStateLabels(array $stateLabels): void
    {
    }

    public function activeNextPlayer(): int|string
    {
        return 0;
    }

    public function getPlayerAfter(int $playerId): int
    {
        return 0;
    }

    public function giveExtraTime(int $playerId, ?int $specificTime = null): void
    {
    }
}

namespace Bga\GameFramework\States;

use Bga\GameFramework\StateType;

abstract class GameState
{
    public function __construct(
        $game,
        public int $id,
        public StateType $type,
        public bool $updateGameProgression = false,
    ) {
    }
}
