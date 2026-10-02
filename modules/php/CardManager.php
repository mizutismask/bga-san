<?php

namespace Bga\Games\San;

use Bga\Games\San\DeckManager;
use Constants;

const TABLE_CARD = "card";

class CardManager extends DeckManager {
    const RIVER_SIZE = 6;

    public function getTotalCardsForPlayer(int $playerId): int {
        $total = 0;
        foreach ([Constants::MATERIAL_LOCATION_PLAYER_DECK, Constants::MATERIAL_LOCATION_HAND, Constants::MATERIAL_LOCATION_PLAYER_DISCARD, Constants::MATERIAL_LOCATION_PLAYER_PLAY_AREA] as $location) {
            $total += $this->countCardsInLocation($this->game->getPlayerLocation($location, $playerId));
        }
        return $total;
    }

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
        return $this->game->handSizeCounter->get($playerId);
    }

    /**
     * Complete hand in deckbuilding style, pick in the deck all that’s available then shuffle and complete if necessary
     * @return void 
     */
    public function replenishHands(int $playerId): void {
        $goal = $this->getPlayerHandSize($playerId);
        $cardsCount = count($this->getCardsInLocation($this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_HAND, $playerId)));
        if ($cardsCount < $goal) {
            $deckLocation = $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DECK, $playerId);
            $cardsNeeded = $goal - $cardsCount;
            $availableCards = $this->countCardsInLocation($deckLocation);
            if ($availableCards < $cardsNeeded) {
                if ($availableCards > 0) {
                    $this->addCardsToHand($availableCards, $playerId, true);
                    $cardsNeeded -= $availableCards;
                }
                $discardLocation = $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $playerId);
                $this->deck->moveAllCardsInLocation($discardLocation, $deckLocation);
                $this->deck->shuffle($deckLocation);
            }
            $this->addCardsToHand($cardsNeeded, $playerId, true);
        }
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

        if (!$choice) {
            $actions = [
                [Constants::CARD_TYPE_PROPAGANDA, $card->propaganda],
                [Constants::CARD_TYPE_HACKING, $card->hacking],
                [Constants::CARD_TYPE_CORRUPTION, $card->corruption],
                [Constants::ACTION_DRAW, $card->draw],
            ];
        } else {
            $actions = array_map(fn($action) => [$action, 1], $this->getChoiceActions($card, $choice));
        }

        if ($choice || $this->getPerCardCounter($card) !== null) {
            $this->game->contextManager->insertContextLog('playCard', $card->id, $choice, json_encode($actions));
        }
        foreach ($actions as [$action, $amount]) {
            if ($amount && $action === Constants::ACTION_DRAW) {
                $this->addCardsToHand($amount, $activePlayerId, true);
                $this->game->notify->all('msg', clienttranslate('${player_name} draws ${qty} card(s)'), [
                    'player_name' => $this->game->getPlayerNameById($activePlayerId),
                    'qty' => $amount,
                ]);
            }
        }

        $this->recalculateCounters($activePlayerId);
    }

    private function getPerCardCounter(SanCard $card): ?int {
        return match ($card->specialEffect) {
            Constants::SPECIAL_EFFECT_PROPAGANDA_PER_PROPAGANDA_CARD => Constants::CARD_TYPE_PROPAGANDA,
            Constants::SPECIAL_EFFECT_HACKING_PER_VIRUS_CARD => Constants::CARD_TYPE_HACKING,
            Constants::SPECIAL_EFFECT_CORRUPTION_PER_CORRUPTION_CARD => Constants::CARD_TYPE_CORRUPTION,
            default => null,
        };
    }

    public function recalculateCounters(int $playerId): void {
        $cards = $this->getPlayedCards($playerId);
        $typeCounts = array_count_values(array_column($cards, 'type_arg'));
        $recordedActions = [];
        foreach ($this->game->contextManager->getAllContextLogs('playCard') as $context) {
            if ((int) $context['player'] === $playerId && !$context['resolved']) {
                $recordedActions[(int) $context['param1']] ??= json_decode($context['param3'], true, 512, JSON_THROW_ON_ERROR);
            }
        }

        $counters = [
            Constants::CARD_TYPE_PROPAGANDA => $this->game->propagandaCounter,
            Constants::CARD_TYPE_HACKING => $this->game->hackingCounter,
            Constants::CARD_TYPE_CORRUPTION => $this->game->corruptionCounter,
        ];
        $totals = array_fill_keys(array_keys($counters), 0);
        $income = 0;
        foreach ($cards as $card) {
            $actions = $recordedActions[$card->id] ?? [
                [Constants::CARD_TYPE_PROPAGANDA, $card->propaganda],
                [Constants::CARD_TYPE_HACKING, $card->hacking],
                [Constants::CARD_TYPE_CORRUPTION, $card->corruption],
            ];
            $perCardCounter = $this->getPerCardCounter($card);
            foreach ($actions as [$action, $amount]) {
                if (isset($counters[$action]) && $action !== $perCardCounter) {
                    $totals[$action] += $amount;
                }
            }
            if ($perCardCounter !== null) {
                $totals[$perCardCounter] += $typeCounts[$card->type_arg];
            }
            $income += $card->income;
        }

        foreach ($counters as $action => $counter) {
            $counter->set($playerId, $totals[$action]);
        }
        $this->game->incomeCounter->set($playerId, $income);
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
            return match ($card->type) {
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

    public function getCardTypeName(SanCard $card): string {
        return match ((int) $card->cardCategory) {
            Constants::CARD_TYPE_PROPAGANDA => clienttranslate('Propaganda'),
            Constants::CARD_TYPE_HACKING => clienttranslate('Hacking'),
            Constants::CARD_TYPE_CORRUPTION => clienttranslate('Corruption'),
            Constants::CARD_TYPE_HARDWARE => clienttranslate('Hardware'),
            Constants::CARD_TYPE_VIRUS => clienttranslate('Virus'),
        };
    }

    function buyCard(SanCard $card, int $activePlayerId): bool {
        $this->insertCardOnExtremePosition($card, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $activePlayerId), true, true, $activePlayerId);
        $this->game->incomeCounter->inc($activePlayerId, $card->cost * -1);
        $this->game->notify->all('msg', clienttranslate('${player_name} buys a ${card_type} card for ${income}${incomeIcon}'), [
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'card_type' => $this->getCardTypeName($card),
            'i18n' => ['card_type'],
            'incomeIcon' => 'income',
            'income' => $card->cost,
        ]);
        return $this->refillRiver();
    }

    public function hasImmediateAction(SanCard $card) {
        return $card->destroyCards || !in_array($card->specialEffect, [
            Constants::SPECIAL_EFFECT_NONE,
            Constants::SPECIAL_EFFECT_PROPAGANDA_PER_PROPAGANDA_CARD,
            Constants::SPECIAL_EFFECT_HACKING_PER_VIRUS_CARD,
            Constants::SPECIAL_EFFECT_CORRUPTION_PER_CORRUPTION_CARD,
        ], true);
    }

    public function refillRiver() {
        $count = $this->countCardsInLocation(Constants::MATERIAL_LOCATION_RIVER);
        if ($count < CardManager::RIVER_SIZE) {
            for ($i = $count; $i < CardManager::RIVER_SIZE; $i++) {
                $emptySlot = $this->getFirstEmptySlotInLocation(CardManager::RIVER_SIZE, Constants::MATERIAL_LOCATION_RIVER);
                $card = $this->castSingle($this->deck->pickCardForLocation(Constants::MATERIAL_LOCATION_DECK, Constants::MATERIAL_LOCATION_RIVER, $emptySlot), true);
                if ($card === null) {
                    $this->game->announceEndCondition(clienttranslate('The game ends because the river can no longer be refilled.'));
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
            $this->game->notify->all('riverDeckUpdated', '', [
                'topCard' => $this->getTopOfLocation(Constants::MATERIAL_LOCATION_DECK),
                'count' => $this->countCardsInLocation(Constants::MATERIAL_LOCATION_DECK),
            ]);
        }
        return true;
    }

    public function discardPlayedCards(int $activePlayerId): void {
        $cards = $this->getPlayedCards($activePlayerId);
        foreach ($cards as $card) {
            if ($card->trashAfterUse) {
                $this->destroyCard($card, false, $activePlayerId);
            } else {
                $this->moveCardToLocation($card, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $activePlayerId), $activePlayerId, false);
            }
        }
    }

    public function destroyCard(SanCard $card, bool $notify, int $playerId) {
        $this->moveCardToLocation($card, Constants::MATERIAL_LOCATION_DESTROYED, 0, $notify, $playerId);
        $this->game->notifyCounterChange();
    }

    public function corruptCard(SanCard $card, int $slot, int $playerId, bool $fromHand = false): bool {
        $slot = $this->game->mirrorSlot($slot, $playerId);
        $position = count($this->getCorruptedCardsOnSlot($slot, $playerId)) + 1;
        if ($position > 2) {
            throw new \Bga\GameFramework\UserException(clienttranslate('You can only corrupt 2 cards per slot'));
        }
        $this->moveCardToLocation($card, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $playerId), $slot * 10 + $position, true, $playerId);
        if (!$fromHand) {
            $this->game->corruptionCounter->inc($playerId, -3);
            $this->refillRiver();
        }
        $corruptedCount = $this->countCardsInLocation(
            $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $playerId)
        );
        $message = $fromHand
            ? clienttranslate('${player_name} corrupts a card from his hand (${corruptedCount}/12)')
            : clienttranslate('${player_name} corrupts a card (${corruptedCount}/12)');
        $this->game->notify->all('msg', $message, [
            'player_name' => $this->game->getPlayerNameById($playerId),
            'corruptedCount' => $corruptedCount,
        ]);
        if ($corruptedCount >= 12) {
            $this->game->announceEndCondition(clienttranslate('${player_name} corrupts twelve cards.'), [
                'player_name' => $this->game->getPlayerNameById($playerId),
            ]);
            $this->game->playerScore->set($playerId, 1);
            $this->game->playerScore->set((int) $this->game->getOpponentId($playerId), 0);
            return true;
        }
        return false;
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
