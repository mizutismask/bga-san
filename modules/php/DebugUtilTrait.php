<?php

namespace Bga\Games\San;

use Bga\GameFramework\UserException;
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

    function debug_addCardToHand(int $cardType = 1) {
        $playerId = (int) $this->getCurrentPlayerId();
        $cards = $this->cardManager->getCardsOfType($cardType);
        $card = reset($cards);
        if ($card) {
            if ($card->location === $this->getPlayerLocation(Constants::MATERIAL_LOCATION_HAND, $playerId))
                throw new UserException(clienttranslate('This card is already in hand'));

            $this->cardManager->moveCardToLocation(
                $card,
                $this->getPlayerLocation(Constants::MATERIAL_LOCATION_HAND, $playerId),
                $playerId,
                true,
                $playerId
            );
        } else {
            throw new UserException(clienttranslate('No card of the requested type exists'));
        }
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
