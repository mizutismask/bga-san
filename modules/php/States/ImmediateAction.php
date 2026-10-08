<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\Actions\Types\IntParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\San\Game;
use Bga\Games\San\SanCard;
use Constants;

class ImmediateAction extends GameState {

    function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_IMMEDIATE_ACTION,
            type: StateType::ACTIVE_PLAYER,
        );
    }
    function onEnteringState(int $activePlayerId, array $args) {
        $card = $this->game->getCardToResolve();
        if ($card->specialEffect === Constants::SPECIAL_EFFECT_CORRUPT_FROM_HAND) {
            if (empty($this->game->cardManager->getPlayerHand($activePlayerId)) || empty($args['corruptionSlots'])) {
                $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
                return PlayerTurn::class;
            }
        }

        if ($card->specialEffect === Constants::SPECIAL_EFFECT_PLAY_FROM_DISCARD && empty($args['_private'][$activePlayerId]['discardCards'])) {
            $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
            return PlayerTurn::class;
        }


        if (in_array($card->specialEffect, [Constants::SPECIAL_EFFECT_COPY_PLAYED_CARD, Constants::SPECIAL_EFFECT_COPY_RIVER_CARD], true) && empty($args['_private'][$activePlayerId]['copyCards'])) {
            $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
            return PlayerTurn::class;
        }
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `PlayerTurn` game state.
     */
    public function getArgs(int $activePlayerId): array {
        $card = $this->game->getCardToResolve();
        $corruptionSlots = [];
        if ($card !== null && $card->specialEffect === Constants::SPECIAL_EFFECT_CORRUPT_FROM_HAND) {
            for ($slot = 1; $slot <= 6; $slot++) {
                $boardSlot = $this->game->mirrorSlot($slot, $activePlayerId);
                if (count($this->game->cardManager->getCorruptedCardsOnSlot($boardSlot, $activePlayerId)) < 2) {
                    $corruptionSlots[] = $slot;
                }
            }
        }
        $copyCards = [];
        $copyChoices = [];
        $copyFromRiver = $card !== null && $card->specialEffect === Constants::SPECIAL_EFFECT_COPY_RIVER_CARD;
        if ($copyFromRiver) {
            $copyCards = array_values($this->game->cardManager->getCardsInLocation(Constants::MATERIAL_LOCATION_RIVER));
        } elseif ($card !== null && $card->specialEffect === Constants::SPECIAL_EFFECT_COPY_PLAYED_CARD) {
            $copyCards = array_values(array_filter($this->game->cardManager->getPlayedCards($activePlayerId, true), fn($played) => $played->id !== $this->game->getCardToResolve(true)->id));
        }
        foreach ($copyCards as $played) {
            $copyChoices[$played->id] = $played->chooseOne
                ? ($played->type_arg === Constants::CARD_TYPE_HARDWARE ? 3 : 2)
                : 0;
        }
        return [
            '_private' => [$activePlayerId => [
                'copyChoices' => $copyChoices,
                'copyCards' => $copyCards,
                'copyFromRiver' => $copyFromRiver,
                'discardCards' => $card !== null && $card->specialEffect === Constants::SPECIAL_EFFECT_PLAY_FROM_DISCARD
                    ? array_values($this->game->cardManager->getCardsInLocation($this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $activePlayerId)))
                    : [],
            ]],
            "corruptionSlots" => $corruptionSlots,
            "canPass" => true,
            "canResetTurn" => $this->globals->get(Constants::CAN_RESET_TURN),
            "remainingDestroysFromHand" => $this->globals->get(Constants::GLBL_REMAINING_DESTROYS),
        ];
    }

    #[PossibleAction]
    public function actCorrupt(int $cardId, int $slot, int $activePlayerId, array $args) {
        $effectCard = $this->game->getCardToResolve();
        if ($effectCard === null || $effectCard->specialEffect !== Constants::SPECIAL_EFFECT_CORRUPT_FROM_HAND) {
            throw new UserException(clienttranslate('You cannot corrupt a card from your hand now'));
        }
        if (!in_array($slot, $args['corruptionSlots'], true)) {
            throw new UserException(clienttranslate('You cannot corrupt a card in this slot'));
        }
        $card = $this->game->cardManager->getCard($cardId);
        if ($card === null || $card->location !== $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_HAND, $activePlayerId)) {
            throw new UserException(clienttranslate('Select a card from your hand'));
        }
        $gameEnded = $this->game->cardManager->corruptCard($card, $slot, $activePlayerId, true);
        $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
        if ($gameEnded) {
            return EndScore::class;
        }
        return PlayerTurn::class;
    }


    #[PossibleAction]
    public function actDestroy(#[IntArrayParam] array $cardIds, int $activePlayerId, array $args) {
        $remainingDestroys = $this->game->globals->get(Constants::GLBL_REMAINING_DESTROYS, 0);
        if ($remainingDestroys < 1 || count($cardIds) > $remainingDestroys) {
            throw new UserException(clienttranslate('You cannot destroy a card now'));
        }
        $cards = [];
        foreach ($cardIds as $id) {
            if ($id < 0 || isset($cards[$id])) {
                throw new UserException(clienttranslate('Select a card from your hand'));
            }
            $card = $this->game->cardManager->getCard($id);
            if ($card === null || $card->location !== $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_HAND, $activePlayerId)) {
                throw new UserException(clienttranslate('Select a card from your hand'));
            }
            $cards[$id] = $card;
        }
        foreach ($cards as $card) {
            $this->game->cardManager->destroyCard($card, true, $activePlayerId);
            $this->game->notify->all('msg', "", []);
        }
        $amount = count($cards);
        $this->game->notify->all('msg', clienttranslate('${player_name} destroys ${amount} card(s) from his hand'), [
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'amount' => $amount,
        ]);
        $this->game->globals->delete(Constants::GLBL_REMAINING_DESTROYS);
        $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
        return PlayerTurn::class;
    }

    protected function playCardAndResolve(SanCard $card, int $choice, int $activePlayerId): string {
        $this->game->cardManager->playCard($card, $choice, $activePlayerId);
        if ($this->game->cardManager->hasImmediateAction($card)) {
            $this->game->globals->set(Constants::GLB_CURRENT_CARD, $card);
            if ($card->destroyCards > 0) {
                $this->game->globals->set(Constants::GLBL_REMAINING_DESTROYS, $card->destroyCards);
            }
            return ImmediateAction::class;
        }
        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actCopyPlayedCard(int $cardId, #[IntParam(min: 0, max: 3)] int $choice, int $activePlayerId) {
        $effectCard = $this->game->getCardToResolve();
        if ($effectCard === null || $effectCard->specialEffect !== Constants::SPECIAL_EFFECT_COPY_PLAYED_CARD) {
            throw new UserException(clienttranslate('You cannot copy a card now'));
        }
        $original = $this->game->cardManager->getCard($this->game->getCardToResolve(true)->id);
        $card = null;
        foreach ($this->game->cardManager->getPlayedCards($activePlayerId, true) as $played) {
            if ($played->id === $cardId && $played->id !== $original->id) {
                $card = clone $played;
                break;
            }
        }
        if ($card === null) {
            throw new UserException(clienttranslate('Select a card you played this turn'));
        }
        return $this->resolveCopy($card, $original, $choice, $cardId);
    }

    #[PossibleAction]
    public function actCopyRiverCard(int $cardId, #[IntParam(min: 0, max: 3)] int $choice, int $activePlayerId) {
        $effectCard = $this->game->getCardToResolve();
        if ($effectCard === null || $effectCard->specialEffect !== Constants::SPECIAL_EFFECT_COPY_RIVER_CARD) {
            throw new UserException(clienttranslate('You cannot copy a river card now'));
        }
        $card = $this->game->cardManager->getCard($cardId);
        if ($card === null || $card->location !== Constants::MATERIAL_LOCATION_RIVER) {
            throw new UserException(clienttranslate('Select a card from the river'));
        }
        $original = $this->game->cardManager->getCard($this->game->getCardToResolve(true)->id);
        return $this->resolveCopy(clone $card, $original, $choice);
    }

    private function resolveCopy(SanCard $card, SanCard $original, int $choice, ?int $sourceId = null): string {
        if (($card->chooseOne && ($choice < 1 || $choice > ($card->type_arg === Constants::CARD_TYPE_HARDWARE ? 3 : 2))) || (!$card->chooseOne && $choice !== 0)) {
            throw new UserException(clienttranslate('You must choose which option to play'));
        }
        $card->id = $original->id;
        $card->location = $original->location;
        $card->location_arg = $original->location_arg;
        $copies = $this->game->globals->get(Constants::GLB_COPIED_PLAYED_CARDS, []);
        $copies[$card->id] = $card;
        $this->game->globals->set(Constants::GLB_COPIED_PLAYED_CARDS, $copies);
        $sources = $this->game->globals->get(Constants::GLB_COPIED_PLAYED_CARD_SOURCES, []);
        if ($sourceId !== null) {
            $sources[$card->id] = $sourceId;
        } else {
            unset($sources[$card->id]);
        }
        $this->game->globals->set(Constants::GLB_COPIED_PLAYED_CARD_SOURCES, $sources);
        $this->game->globals->set(Constants::GLB_CARD_TO_AUTO_PLAY, ['cardId' => $card->id, 'choice' => $choice]);
        $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actPlayFromDiscard(int $cardId, #[IntParam(min: 0, max: 3)] int $choice, int $activePlayerId) {
        $effectCard = $this->game->getCardToResolve();
        if ($effectCard === null || $effectCard->specialEffect !== Constants::SPECIAL_EFFECT_PLAY_FROM_DISCARD) {
            throw new UserException(clienttranslate('You cannot play a card from your discard pile now'));
        }
        $card = $this->game->cardManager->getCard($cardId);
        if ($card === null || $card->location !== $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $activePlayerId)) {
            throw new UserException(clienttranslate('Select a card from your discard pile'));
        }
        if (($card->chooseOne && ($choice < 1 || $choice > ($card->type_arg === Constants::CARD_TYPE_HARDWARE ? 3 : 2))) || (!$card->chooseOne && $choice !== 0)) {
            throw new UserException(clienttranslate('You must choose which option to play'));
        }
        $nextState = $this->playCardAndResolve($card, $choice, $activePlayerId);
        if ($nextState === PlayerTurn::class) {
            $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
        }
        return $nextState;
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player pass, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     */
    #[PossibleAction]
    public function actPass(int $activePlayerId) {
        $card = $this->game->getCardToResolve();
        if ($card->specialEffect === Constants::SPECIAL_EFFECT_COPY_PLAYED_CARD || $card->specialEffect === Constants::SPECIAL_EFFECT_COPY_RIVER_CARD || $card->specialEffect === Constants::SPECIAL_EFFECT_CORRUPT_FROM_HAND || $card->specialEffect === Constants::SPECIAL_EFFECT_PLAY_FROM_DISCARD || $card->destroyCards) {
            $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
        }
        if ($card->destroyCards) {
            $this->game->globals->delete(Constants::GLBL_REMAINING_DESTROYS);
        }
        return PlayerTurn::class;
    }

    /** @return array{beginningCardIds: int[], virusCardIds: int[], cheapestCard: ?SanCard} */
    private function getZombieHandCards(int $playerId): array {
        $beginningCardIds = [];
        $virusCardIds = [];
        $cheapestCard = null;
        foreach ($this->game->cardManager->getPlayerHand($playerId) as $handCard) {
            if ($handCard->type_arg === Constants::CARD_TYPE_VIRUS) {
                $virusCardIds[] = $handCard->id;
            } elseif (($handCard->type >= 1 && $handCard->type <= 12) || ($handCard->type >= 18 && $handCard->type <= 29)) {
                $beginningCardIds[] = $handCard->id;
            }
            if ($cheapestCard === null || $handCard->cost < $cheapestCard->cost) {
                $cheapestCard = $handCard;
            }
        }
        return compact('beginningCardIds', 'virusCardIds', 'cheapestCard');
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
        $copyArgs = $this->getArgs($playerId)['_private'][$playerId];
        $copyCards = $copyArgs['copyCards'];
        if ($copyCards) {
            $copy = $copyCards[0];
            $action = $copyArgs['copyFromRiver'] ? 'actCopyRiverCard' : 'actCopyPlayedCard';
            return $this->$action($copy->id, ($copyArgs['copyChoices'][$copy->id] ?? 0) > 0 ? 1 : 0, $playerId);
        }
        //zombie level 1
        $card = $this->game->getCardToResolve();
        if ($card !== null && $card->specialEffect === Constants::SPECIAL_EFFECT_PLAY_FROM_DISCARD) {
            $cardToPlay = null;
            foreach ($this->game->cardManager->getCardsInLocation($this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DISCARD, $playerId)) as $discardCard) {
                if ($cardToPlay === null || $discardCard->cost > $cardToPlay->cost) {
                    $cardToPlay = $discardCard;
                }
            }
            if ($cardToPlay !== null) {
                return $this->actPlayFromDiscard($cardToPlay->id, $cardToPlay->chooseOne ? 1 : 0, $playerId);
            }
            return $this->actPass($playerId);
        }
        if ($card !== null && $card->specialEffect === Constants::SPECIAL_EFFECT_CORRUPT_FROM_HAND) {
            $args = $this->getArgs($playerId);
            if ($args['corruptionSlots']) {
                $handCards = $this->getZombieHandCards($playerId);
                $cardId = $handCards['beginningCardIds'][0] ?? $handCards['cheapestCard']?->id;
                if ($cardId !== null) {
                    return $this->actCorrupt($cardId, $args['corruptionSlots'][0], $playerId, $args);
                }
            }
            $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
            return PlayerTurn::class;
        }
        $remainingDestroys = $this->game->globals->get(Constants::GLBL_REMAINING_DESTROYS, 0);
        if ($remainingDestroys > 0) {
            if ($this->game->cardManager->getTotalCardsForPlayer($playerId) < 2 * $this->game->cardManager->getPlayerHandSize($playerId)) {
                return $this->actPass($playerId);
            }
            $handCards = $this->getZombieHandCards($playerId);
            $cardIds = array_slice(array_merge($handCards['virusCardIds'], $handCards['beginningCardIds']), 0, $remainingDestroys);
            if (empty($cardIds) && $handCards['cheapestCard'] !== null) {
                $cardIds[] = $handCards['cheapestCard']->id;
            }
            return $this->actDestroy($cardIds, $playerId, []);
        }
    }
}
