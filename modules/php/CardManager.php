<?php

namespace Bga\Games\San;

use Bga\Games\San\DeckManager;
use Constants;

const TABLE_CARD = "card";

class CardManager extends DeckManager {
    const RIVER_SIZE = 6;

    public function getPlayerHand(int $playerId) {
        return $this->cast($this->deck->getCardsInLocation("hand_{$playerId}"));
    }

    public function dealHands($notify = false) {
        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $playerId => $player) {
            $qty = $this->getPlayerHandSize($playerId);
            $this->addCardsToHand($qty, $playerId, $notify);
        }
    }

    public function moveActionCardToPlayerHand(int $cardId, int $playerId, bool $faceDown = false) {
        $this->moveCardToPlayerHand($cardId, $playerId, $faceDown, clienttranslate('${player_name} takes an action card'));
    }

    public function getPlayerHandSize(int $playerId) {
        return 6; //todo adjust with variables
    }

    public function replenishHands() {
        $players = $this->game->loadPlayersBasicInfos();
        $cardsAdded = false;
        foreach ($players as $playerId => $player) {
            $goal = $this->getPlayerHandSize($playerId);
            $cardsCount = count($this->getCardsInLocation($this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_HAND, $playerId)));
            if ($cardsCount < $goal) {
                $this->addCardsToHand($goal - $cardsCount, $playerId, true);
                $cardsAdded = true;
            }
        }
        //$this->game->notifyCounterChange();
        return $cardsAdded;
    }

    public function getPlayedCards(int $playerId) {
        return $this->cast($this->deck->getCardsInLocation($this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_PLAY_AREA, $playerId)));
    }

    public function playCard(SanCard $card, int $activePlayerId) {
        $this->moveCardToLocation($card->id, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_PLAY_AREA, $activePlayerId), $activePlayerId, true);

        if ($card->propaganda) {
            $this->game->propagandaCounter->inc($activePlayerId, $card->propaganda);
        }
        if ($card->hacking) {
            $this->game->hackingCounter->inc($activePlayerId, $card->hacking);
        }
        if ($card->corruption) {
            $this->game->corruptionCounter->inc($activePlayerId, $card->corruption);
        }
        if ($card->income) {
            $this->game->incomeCounter->inc($activePlayerId, $card->income);
        }
    }

    function buyCard(SanCard $card, int $activePlayerId): bool {
        $this->insertCardOnExtremePosition($card, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $activePlayerId), true, true, $activePlayerId);
        $this->game->incomeCounter->inc($activePlayerId, $card->income * -1);
        return $this->refillRiver();
    }

    public function hasImmediateAction(SanCard $card) {
        return $card->chooseOne || $card->destroyCards || $card->specialEffect || $card->draw;
    }

    public function refillRiver() {
        $count = $this->countCardsInLocation(Constants::MATERIAL_LOCATION_RIVER);
        if ($count < CardManager::RIVER_SIZE) {
            for ($i = $count; $i < CardManager::RIVER_SIZE; $i++) {
                $emptySlot = $this->getFirstEmptySlotInLocation(CardManager::RIVER_SIZE, Constants::MATERIAL_LOCATION_RIVER);
                $card = $this->castSingle($this->deck->pickCardForLocation(Constants::MATERIAL_LOCATION_DECK, Constants::MATERIAL_LOCATION_RIVER, $emptySlot), true);
                if ($card === null) {
                    return false;
                }
                $this->game->notify->all('materialMove', '', [
                    'type' => $this->materialType,
                    'from' => Constants::MATERIAL_LOCATION_DECK,
                    'to' => Constants::MATERIAL_LOCATION_RIVER,
                    'toArg' => $emptySlot,
                    'material' => [$card],
                ]);
            }
        }
        return true;
    }

    public function discardPlayedCards(int $activePlayerId): void {
        $cards = $this->getPlayedCards($activePlayerId);
        foreach ($cards as $card) {
            if ($card->trashAfterUse) {
                $this->moveCardToLocation($card->id, Constants::MATERIAL_LOCATION_DESTROYED, "", true);
            } else {
                $this->moveCardToLocation($card->id, Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $activePlayerId, true);
            }
        }
    }

    public function corruptCard(SanCard $card, int $slot, int $position, int $playerId): void {
        $this->moveCardToLocation($card, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $playerId), $slot * 10 + $position, true, $playerId);
        $this->game->corruptionCounter->inc($playerId, -3);
    }

    public function getCorruptedCardsOnSlot(int $slot, int $playerId): array {
        return $this->cast(
            (new QueryBuilder($this->game, $this->tableName))
                ->select($this->game->getTypicalTableFields())
                ->where('card_location', $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $playerId))
                ->where('card_location_arg', '>=', $slot * 10 + 1)
                ->where('card_location_arg', '<=', $slot * 10 + 2)
                ->get()
        );
    }
}
