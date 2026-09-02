<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\StateType;
use Bga\Games\San\Game;
use Constants;

class NextRound extends \Bga\GameFramework\States\GameState {

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_NEXT_ROUND,
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
        $round = $this->globals->inc("round", 1);

        foreach ($this->game->getPlayers() as $playerId => $player) {
            //$this->game->setPlayerGlobal($playerId, Constants::GLBL_DISCOVERY_TAKEN, true);
        }

        $this->notify->all('newRound', clienttranslate('&#10148; Round ${round}'), ["round" => $round]);
        return NextPlayer::class;
    }
}
