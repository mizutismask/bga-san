<?php

namespace Bga\Games\San;

use Bga\GameFramework\Actions\Debug;
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

        //$this->gamestate->changeActivePlayer(2343492);
    }

    function debug_addCardToHand(int $cardType) {
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

    #[Debug(reload: true)]
    function debug_addCopyingCardsToRiver() {
        $copyEffects = [Constants::SPECIAL_EFFECT_COPY_PLAYED_CARD, Constants::SPECIAL_EFFECT_COPY_RIVER_CARD];
        $copyingCards = array_values(array_filter(
            $this->cardManager->getDeckCards(),
            fn($card) => in_array($card->specialEffect, $copyEffects, true)
        ));
        if (!$copyingCards) {
            return;
        }

        $riverCards = $this->cardManager->getRiverCards();
        usort($riverCards, fn($a, $b) => $a->location_arg <=> $b->location_arg);
        $playerId = (int) $this->getCurrentPlayerId();
        $index = 0;
        foreach ($riverCards as $card) {
            if (in_array($card->specialEffect, $copyEffects, true)) {
                continue;
            }
            $copyingCard = $copyingCards[$index++];
            $this->cardManager->moveCardToLocation($card, Constants::MATERIAL_LOCATION_DECK, $copyingCard->location_arg, true, $playerId);
            $this->cardManager->moveCardToLocation($copyingCard, Constants::MATERIAL_LOCATION_RIVER, $card->location_arg, true, $playerId);
            if ($index === count($copyingCards)) {
                break;
            }
        }
    }

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

    function debug_endGame() {
        $this->gamestate->jumpToState(Constants::STATE_ID_GAME_END);
    }
}
