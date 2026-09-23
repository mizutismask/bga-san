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

    public function playCard(SanCard $card, int $choice, int $activePlayerId) {
        $revealed = $this->game->globals->get('revealedPlayedCards', []);
        $this->game->globals->set('revealedPlayedCards', array_values(array_diff($revealed, [$card->id])));
        $location = $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_PLAY_AREA, $activePlayerId);
        $this->moveCardToLocation($card, $location, $activePlayerId, false);
        $this->game->notify->player($activePlayerId, 'materialMove', '', [
            'type' => $this->materialType,
            'from' => $card->location,
            'fromArg' => $card->location_arg,
            'to' => $location,
            'toArg' => $activePlayerId,
            'material' => [$this->castSingle($this->deck->getCard($card->id))],
        ]);

        $counters = [
            Constants::CARD_TYPE_PROPAGANDA => $this->game->propagandaCounter,
            Constants::CARD_TYPE_HACKING => $this->game->hackingCounter,
            Constants::CARD_TYPE_CORRUPTION => $this->game->corruptionCounter,
        ];
        if (!$choice) {
            $actions = [
                [Constants::CARD_TYPE_PROPAGANDA, $card->propaganda],
                [Constants::CARD_TYPE_HACKING, $card->hacking],
                [Constants::CARD_TYPE_CORRUPTION, $card->corruption],
                [Constants::ACTION_DRAW, $card->draw],
            ];
        } else {
            $actions = array_map(fn($action) => [$action, 1], $this->getChoiceActions($card, $choice));
            $this->game->contextManager->insertContextLog('playCard', $card->id, $choice, json_encode($actions));
        }

        foreach ($actions as [$action, $amount]) {
            if (!$amount) {
                continue;
            }
            if (isset($counters[$action])) {
                $counters[$action]->inc($activePlayerId, $amount);
            } elseif ($action === Constants::ACTION_DRAW) {
                $this->addCardsToHand($amount, $activePlayerId, true);
            }
        }
        if ($card->income) {
            $this->game->incomeCounter->inc($activePlayerId, $card->income);
        }
    }

    private function getChoiceActions(SanCard $card, int $choice) {
        if ((int) $card->cardCategory === Constants::CARD_TYPE_HARDWARE) {
            return match ($choice) {
                1 => [Constants::CARD_TYPE_PROPAGANDA],
                2 => [Constants::CARD_TYPE_HACKING],
                3 => [Constants::CARD_TYPE_CORRUPTION],
                default => [],
            };
        } else {
            return match ($card->type_arg) {
                37 => $choice == 1 ? [Constants::CARD_TYPE_PROPAGANDA, Constants::CARD_TYPE_PROPAGANDA] : [Constants::ACTION_DRAW, Constants::ACTION_DRAW],
                38 => $choice == 1 ? [Constants::CARD_TYPE_PROPAGANDA, Constants::CARD_TYPE_PROPAGANDA] : [Constants::ACTION_DRAW, Constants::ACTION_DRAW],
                47 => $choice == 1 ? [Constants::CARD_TYPE_HACKING, Constants::CARD_TYPE_HACKING] : [Constants::ACTION_DRAW, Constants::ACTION_DRAW],
                48 => $choice == 1 ? [Constants::CARD_TYPE_HACKING, Constants::CARD_TYPE_HACKING] : [Constants::ACTION_DRAW, Constants::ACTION_DRAW],
                57 => $choice == 1 ? [Constants::CARD_TYPE_CORRUPTION, Constants::CARD_TYPE_CORRUPTION] : [Constants::ACTION_DRAW, Constants::ACTION_DRAW],
                58 => $choice == 1 ? [Constants::CARD_TYPE_CORRUPTION, Constants::CARD_TYPE_CORRUPTION] : [Constants::ACTION_DRAW, Constants::ACTION_DRAW],
                default => [],
            };
        }
    }

    public function revealPlayedCards(int $playerId): void {
        $cards = $this->getPlayedCards($playerId);
        $revealed = $this->game->globals->get('revealedPlayedCards', []);
        $this->game->globals->set('revealedPlayedCards', array_values(array_unique(array_merge($revealed, array_map(fn($card) => $card->id, $cards)))));
        $location = $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_PLAY_AREA, $playerId);
        $this->game->notify->all('materialMove', '', [
            'type' => $this->materialType,
            'from' => $location,
            'fromArg' => $playerId,
            'to' => $location,
            'toArg' => $playerId,
            'material' => $cards,
        ]);
    }

    function buyCard(SanCard $card, int $activePlayerId): bool {
        $this->insertCardOnExtremePosition($card, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $activePlayerId), true, true, $activePlayerId);
        $this->game->incomeCounter->inc($activePlayerId, $card->income * -1);
        return $this->refillRiver();
    }

    public function hasImmediateAction(SanCard $card) {
        return $card->destroyCards || $card->specialEffect || $card->draw;
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
                $this->moveCardToLocation($card, Constants::MATERIAL_LOCATION_DESTROYED, 0, true, $activePlayerId);
            } else {
                $this->moveCardToLocation($card, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $activePlayerId), $activePlayerId, true, $activePlayerId);
            }
        }
    }

    public function corruptCard(SanCard $card, int $slot, int $position, int $playerId): void {
        $this->moveCardToLocation($card, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $playerId), $slot * 10 + $position, true, $playerId);
        $this->game->corruptionCounter->inc($playerId, -3);
        $this->refillRiver();
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
