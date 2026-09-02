<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\StateType;
use Bga\Games\San\Game;
use Constants;

const ST_END_GAME = 99;

const SCORE_GAIN_PER_RARE_TREASURE = 3;
const SCORE_LOSE_PER_RATS = 1;
const SCORE_LOSE_PER_UNFILLED_ROOMS = 5;
const SCORE_SOLO_COLOR = 5;


class EndScore extends \Bga\GameFramework\States\GameState {

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_END_SCORE,
            type: StateType::GAME,
        );
    }

    /**
     * Game state action, example content.
     *
     * The onEnteringState method of state `EndScore` is called just before the end of the game.
     */
    public function onEnteringState() {
        // Here, we would compute scores if they are not updated live, and compute average statistics
        $this->scorePoints();
        $this->scoreTieBreaker();

        foreach ($this->game->getWinners() as $playerId) {
            $this->notify->all('highlightWinnerScore', '', [
                'playerId' => $playerId,
            ]);
        }

        if ($this->game->isStudio()) {
            $this->game->stMakeEveryoneActive();
            return DebugGameEnd::class;
        } else {
            return ST_END_GAME;
        }
    }

    public function scorePoints() {
        foreach ($this->game->getPlayers() as $playerId => $player) {
            $points = $this->getPoints($playerId);
            $this->playerScore->inc($playerId, $points, new NotificationMessage(clienttranslate('${player_name} gains ${points} points'), ['points' => $points]));
        }
    }

    private function scoreTieBreaker() {
        foreach ($this->game->loadPlayersBasicInfos() as $playerId => $playerInfo) {
            //$this->game->playerScoreAux->set($playerId, $this->game->playerFishCounter->get($playerId), new NotificationMessage(""));
        }
    }

    private function getPoints($playerId) {
        return 0;
    }
}
