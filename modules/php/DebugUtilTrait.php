<?php

namespace Bga\Games\San;

use Bga\GameFramework\Actions\Debug;
use Constants;

/** @mixin Game */
trait DebugUtilTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////

    function debugSetup() {
        if (!$this->isStudio()) {
            return;
        }

        //$this->debugSetDestinationInHand(7, 2343492);
        //$this->gamestate->changeActivePlayer(2343492);
    }

    function debug_addTickets(int $amount = 1) {
        $this->propagandaCounter->inc($this->getCurrentPlayerId(), $amount);
    }

    /*function debug_CompleteDestinations() {
        $players = $this->getPlayersIds();
        $restriction = " limit " . ($this->getInitialDestinationCardNumber() - 1);
        foreach ($players as $playerId) {
            static::DbQuery("UPDATE `destination` set `completed` = true WHERE `card_location_arg`= $playerId" . $restriction);
        }
        $this->gamestate->jumpToState(ST_PLAYER_CHOOSE_ACTION);
    }*/

     /*        #[Debug(reload: true)]
    function debug_EmptyDestinationDeck() {
        $this->destinations->moveAllCardsInLocation('deck', 'void');
    }*/

    /**
     * To easily test zombie code.
     */
    public function debug_playAutomatically(int $moves = 50) {
        $count = 0;
        while (intval($this->gamestate->getCurrentMainStateId()) < 90 && $count < $moves) {
            $count++;
            foreach ($this->gamestate->getActivePlayerList() as $playerId) {
                $playerId = (int)$playerId;
                $this->gamestate->runStateClassZombie($this->gamestate->getCurrentState($playerId), $playerId);
            }
        }
    }

    public function debug_jumpToScore() {
        $this->gamestate->jumpToState(Constants::STATE_ID_END_SCORE);
    }

    function debug_endGame() {
        $this->gamestate->jumpToState(Constants::STATE_ID_GAME_END);
    }
}
