<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\Actions\Types\IntParam;
use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\San\Game;
use Bga\Games\San\SanCard;
use Constants;

class PlayerTurn extends GameState {

    function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_PLAYER_TURN,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate(_('${actplayer} must play cards from his hand')),
            descriptionMyTurn: clienttranslate('${you} must play cards from your hand'),
        );
    }

    function onEnteringState(int $activePlayerId, array $args) {
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `PlayerTurn` game state.
     */
    public function getArgs(int $activePlayerId): array {
        // Get some values from the current game situation from the database.
        return [
            "canPass" => !empty($this->game->cardManager->getPlayedCards($activePlayerId)),
            "canResetTurn" => $this->globals->get(Constants::CAN_RESET_TURN),
            "possibleCards" => $this->getPossibleCards($activePlayerId),
        ];
    }

    #[PossibleAction]
    public function actPlayCard(int $cardId, #[IntParam(min: 0, max: 3)] ?int $choice, int $activePlayerId, array $args) {
        /** @var SanCard|null $sanCard */
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
        if ($sanCard->chooseOne && !$choice) {
            throw new UserException(clienttranslate('You must choose which option to play'));
        }
        $this->game->cardManager->playCard($sanCard, $choice, $activePlayerId);

        if ($this->game->cardManager->hasImmediateAction($sanCard)) {
            $this->game->globals->set(Constants::GLB_CURRENT_CARD, $sanCard);
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
        $this->game->cardManager->revealPlayedCards($activePlayerId);
        return PlayerDecisions::class;

        /*  $end = $this->game->hasReachedEndOfGameRequirements();
        if ($end) {
            return CardShopping::class;
        } else {
            return NextPlayer::class;
        }*/
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

    function getPossibleCards(int $activePlayerId) {
        $hand = $this->game->cardManager->getPlayerHand($activePlayerId);
        $playedTypes = array_column($this->game->cardManager->getPlayedCards($activePlayerId), 'type_arg');
        //only one type is allowed from Hacking, Corruption or Propaganda. All other types are allowed
        $restrictedTypes = [
            Constants::CARD_TYPE_HACKING => true,
            Constants::CARD_TYPE_CORRUPTION => true,
            Constants::CARD_TYPE_PROPAGANDA => true,
        ];

        $playedRestrictedType = null;
        foreach ($playedTypes as $typeArg) {
            if (isset($restrictedTypes[$typeArg])) {
                $playedRestrictedType = $typeArg;
                break;
            }
        }

        if ($playedRestrictedType === null) {
            return $hand;
        }

        return array_filter(
            $hand,
            fn($card) => !isset($restrictedTypes[$card->type_arg]) || $card->type_arg === $playedRestrictedType
        );
    }
}
