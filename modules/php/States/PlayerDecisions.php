<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\San\CardManager;
use Bga\Games\San\Game;
use Bga\Games\San\SanCard;
use Constants;

class PlayerDecisions extends GameState {

    function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_PLAYER_DECISION,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must choose how to use his symbols'),
            descriptionMyTurn: clienttranslate('You must choose how to use your symbols'),
        );
    }

    function onEnteringState(int $activePlayerId, array $args) {
        if($args['canCorrupt'] === false && $args['canProgressOnProp'] === false) {
            return CardShopping::class;
        }
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `PlayerTurn` game state.
     */
    public function getArgs(int $activePlayerId): array {
        // Get some values from the current game situation from the database.
        return [
            "canCorrupt" => $this->game->corruptionCounter->get($activePlayerId) > 2,
            "canProgressOnProp" => $this->canProgressOnProp($activePlayerId),
        ];
    }

    function canProgressOnProp(int $playerId): bool {
        $propPosition = $this->game->propagandaProgressCounter->get($playerId);

        $slot = $this->mirrorSlot($propPosition, $playerId);
        /** @var SanCard|null $nextCard */
        $nextCard = $this->game->cardManager->getCardsInLocation(Constants::MATERIAL_LOCATION_RIVER, $slot)[0] ?? null;
        if (!$nextCard) {
            return false;//todo
        }
        $opponentId = $this->game->getOpponentId($playerId);

        $nextCardCost = $nextCard->moveCost
            - count($this->game->cardManager->getCorruptedCardsOnSlot($slot, $playerId))
            + count($this->game->cardManager->getCorruptedCardsOnSlot(
                $this->mirrorSlot($propPosition, $opponentId),
                $opponentId
            ));

        return $this->game->propagandaCounter->get($playerId) >= max(0, $nextCardCost);
    }

    #[PossibleAction]
    public function actCorrupt(int $cardId, int $slot, int $activePlayerId, array $args) {
        if ($args['canCorrupt'] === false) {
            throw new UserException(clienttranslate('You don’t have enough corruption'));
        }

        $slot = $this->mirrorSlot($slot, $activePlayerId);
        $position = count($this->game->cardManager->getCorruptedCardsOnSlot($slot, $activePlayerId));

        if ($position >= 2) {
            throw new UserException(clienttranslate('You can only corrupt 2 cards per slot'));
        }

        $card = $this->game->cardManager->getCard($cardId);
        $this->game->cardManager->corruptCard($card, $slot, $position + 1, $activePlayerId);
        return PlayerDecisions::class;
    }

    #[PossibleAction]
    public function actProgressOnProp(int $activePlayerId, array $args) {
        if ($args['canProgressOnProp'] === false) {
            throw new UserException(clienttranslate('You don’t have enough propaganda to progress'));
        }

        $newPosition = $this->game->propagandaProgressCounter->inc($activePlayerId, 1);

        //todo hand size +1 eventually

        if ($newPosition == 7) {
            return EndScore::class;
        }

        return PlayerDecisions::class;
    }

    function mirrorSlot(int $slot, int $playerId) {
        if ($this->game->getPlayerNoById($playerId) == 1) {
            return $slot;
        } else {
            return 7 - $slot;
        }
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
            return CardShopping::class;
        } else {
            return NextPlayer::class;
        }
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
