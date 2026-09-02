<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\StateType;
use Bga\Games\San\Game;
use Constants;

class EndOfRound extends \Bga\GameFramework\States\GameState {

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_END_OF_ROUND,
            type: StateType::GAME
        );
    }

    function onEnteringState() {
        // Go to another gamestate
        $gameEnd = $this->game->hasReachedEndOfGameRequirements();
        if ($gameEnd) {
            return EndScore::class;
        } else {
            return PlayerTurn::class;
        }
    }
}
