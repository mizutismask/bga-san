<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\Actions\CheckAction;
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
        $card = $this->game->globals->get(Constants::GLB_CURRENT_CARD);
        if ($card->specialEffect === Constants::SPECIAL_EFFECT_CORRUPT_FROM_HAND) {
            if (empty($this->game->cardManager->getPlayerHand($activePlayerId)) || empty($args['corruptionSlots'])) {
                $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
                return PlayerTurn::class;
            }
            return;
        }
        
        //todo other effect
        return PlayerTurn::class;
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `PlayerTurn` game state.
     */
    public function getArgs(int $activePlayerId): array {
        $card = $this->game->globals->get(Constants::GLB_CURRENT_CARD);
        $corruptionSlots = [];
        if ($card !== null && $card->specialEffect === Constants::SPECIAL_EFFECT_CORRUPT_FROM_HAND) {
            for ($slot = 1; $slot <= 6; $slot++) {
                $boardSlot = $this->game->mirrorSlot($slot, $activePlayerId);
                if (count($this->game->cardManager->getCorruptedCardsOnSlot($boardSlot, $activePlayerId)) < 2) {
                    $corruptionSlots[] = $slot;
                }
            }
        }
        // Get some values from the current game situation from the database.
        return [
            "corruptionSlots" => $corruptionSlots,
            "canPass" => true,
            "canResetTurn" => $this->globals->get(Constants::CAN_RESET_TURN),
        ];
    }

    #[PossibleAction]
    public function actCorrupt(int $cardId, int $slot, int $activePlayerId, array $args) {
        $effectCard = $this->game->globals->get(Constants::GLB_CURRENT_CARD);
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
        $boardSlot = $this->game->mirrorSlot($slot, $activePlayerId);
        $position = count($this->game->cardManager->getCorruptedCardsOnSlot($boardSlot, $activePlayerId));
        $this->game->cardManager->corruptCard($card, $boardSlot, $position + 1, $activePlayerId, true);
        $corruptedCount = $this->game->cardManager->countCardsInLocation(
            $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $activePlayerId)
        );
        $this->game->notify->all('msg', clienttranslate('${player_name} corrupts a card from his hand (${corruptedCount}/12)'), [
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'corruptedCount' => $corruptedCount,
        ]);
        $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
        if ($corruptedCount >= 12) {
            $this->game->playerScore->set($activePlayerId, 1);
            $this->game->playerScore->set((int) $this->game->getOpponentId($activePlayerId), 0);
            return EndScore::class;
        }
        return PlayerTurn::class;
    }

    #[PossibleAction]
    public function actPlayCard(int $cardId, int $activePlayerId, array $args) {
        $sanCard = null;
        foreach ($args['possibleCards'] as $card) {
            if ($card->id === $cardId) {
                $sanCard = $card;
                break;
            }
        }
        if ($sanCard === null) {
            throw new UserException(clienttranslate('You cannot play this card'));
        }
        $this->game->cardManager->playCard($card, $activePlayerId);
        if ($this->game->cardManager->hasImmediateAction($card)) {
            $this->game->globals->set(Constants::GLB_CURRENT_CARD, $card);
            return ImmediateAction::class;
        }
        return PlayerTurn::class;
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player pass, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     */
    #[PossibleAction]
    public function actPass(int $activePlayerId) {
        $end = $this->game->hasReachedEndOfGameRequirements();
        if ($end) {
            if ($end && $this->globals->get(Constants::LAST_TURN) == 0) {
                $this->globals->set(Constants::LAST_TURN, $this->game->getLastPlayer()); //we play until the last player to finish the round
                if (!$this->game->isLastPlayer($activePlayerId)) {
                    $this->notify->all('lastTurn', clienttranslate('${player_name} triggered the end of the game, finishing round !'), ['player_name' => $this->game->getPlayerNameById($activePlayerId)]);
                    return NextPlayer::class;
                } else {
                    return EndOfRound::class;
                }
            }
        } else {
            return NextPlayer::class;
        }
    }

    #[CheckAction(false)]
    function actResetPlayerTurn() {
        $possible = $this->globals->get(Constants::CAN_RESET_TURN);
        if (!$possible) {
            throw new UserException(clienttranslate("Undo is not available"));
        }
        $this->game->undoRestorePoint();
        //$this->toggleResetTurn(false);
        $this->gamestate->reloadState();
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
        $card = $this->game->globals->get(Constants::GLB_CURRENT_CARD);
        if ($card !== null && $card->specialEffect === Constants::SPECIAL_EFFECT_CORRUPT_FROM_HAND) {
            $args = $this->getArgs($playerId);
            $hand = array_values($this->game->cardManager->getPlayerHand($playerId));
            if ($hand && $args['corruptionSlots']) {
                return $this->actCorrupt($hand[0]->id, $args['corruptionSlots'][0], $playerId, $args);
            }
            $this->game->globals->delete(Constants::GLB_CURRENT_CARD);
            return PlayerTurn::class;
        }
        //zombie level 1
        $args = $this->getArgs($playerId);
        $mandatoryMoveDone = $args['mandatoryMoveDone'];
        if ($mandatoryMoveDone) {
            return $this->actPass($playerId);
        } else {
            //random oshax move
            $oshaxValidMoves = $args['oshaxValidMoves'];
            $slot = $this->game->getRandomValue($oshaxValidMoves);
            return $this->actMoveOshax($slot, $playerId, $args);
        }
    }
}
