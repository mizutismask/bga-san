<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\StateType;
use Bga\Games\San\Game;
use Constants;

class NextPlayer extends \Bga\GameFramework\States\GameState {

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_NEXT_PLAYER,
            type: StateType::GAME,
            updateGameProgression: true,
        );
    }

    /**
     * Game state action, example content.
     *
     * The onEnteringState method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    function onEnteringState() {

        $activePlayerId = $this->game->activateNextPlayerCustom();
        $this->game->cardManager->replenishHands();

        //$this->game->globals->set(Constants::GLBL_REMAINING_OSHAX_MOVES, 2);
        //$this->game->setPlayerGlobal($activePlayerId, Constants::GLBL_DISCOVERY_TAKEN, false);

        $this->game->contextManager->reset();

        return PlayerTurn::class;
    }
}
