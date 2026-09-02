<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\StateType;
use Bga\Games\San\Game;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Constants;

class DebugGameEnd extends GameState {

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_DEBUG_GAME_END,
            type: StateType::MULTIPLE_ACTIVE_PLAYER,
            description: 'Debug Game End',
            descriptionMyTurn: 'Debug Game End',
        );
    }

    /**
     * Game state action, example content.
     *
     * The onEnteringState method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    function onEnteringState() {
    }

    #[PossibleAction]
    function endGame() {
        $this->gamestate->nextState("endGame");
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: play a random card).
     * 
     * See more about Zombie Mode: https://en.doc.boardgamearena.com/Zombie_Mode
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, 
     * but use the $playerId passed in parameter and $this->game->getPlayerNameById($playerId) instead.
     */
    function zombie(int $playerId) {
        $this->gamestate->setPlayerNonMultiactive($playerId, '');
    }
}
