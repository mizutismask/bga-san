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
        if ($args['canCorrupt'] === false && $args['canProgressOnProp'] === false && $args['canHack'] === false) {
            return CardShopping::class;
        }
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `PlayerTurn` game state.
     */
    public function getArgs(int $activePlayerId): array {
        $propagandaCost = $this->game->getPropagandaCost($activePlayerId);
        return [
            "canCorrupt" => $this->game->corruptionCounter->get($activePlayerId) > 2,
            "canHack" => $this->game->hackingCounter->get($activePlayerId) > 0,
            "propagandaCost" => $propagandaCost,
            "canProgressOnProp" => $this->game->propagandaCounter->get($activePlayerId) >= $propagandaCost,
        ];
    }

    #[PossibleAction]
    public function actCorrupt(int $cardId, int $slot, int $activePlayerId, array $args) {
        if ($args['canCorrupt'] === false) {
            throw new UserException(clienttranslate('You don’t have enough corruption'));
        }

        $slot = $this->game->mirrorSlot($slot, $activePlayerId);
        $position = count($this->game->cardManager->getCorruptedCardsOnSlot($slot, $activePlayerId));

        if ($position >= 2) {
            throw new UserException(clienttranslate('You can only corrupt 2 cards per slot'));
        }

        $card = $this->game->cardManager->getCard($cardId);
        $this->game->cardManager->corruptCard($card, $slot, $position + 1, $activePlayerId);
        $this->game->notify->all('msg', clienttranslate('${player_name} corrupts a card (${corruptedCount}/12)'), [
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'corruptedCount' => $this->game->cardManager->countCardsInLocation(
                $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $activePlayerId)
            ),
        ]);
        return PlayerDecisions::class;
    }

    #[PossibleAction]
    public function actProgressOnProp(int $activePlayerId, array $args) {
        if ($args['canProgressOnProp'] === false) {
            throw new UserException(clienttranslate('You don’t have enough propaganda to progress'));
        }

        $newPosition = $this->game->propagandaProgressCounter->inc($activePlayerId, 1);
        $this->game->propagandaCounter->inc($activePlayerId, -$args['propagandaCost']);
        $this->game->notify->all('msg', clienttranslate('${player_name} crosses card ${position} on the propaganda track'), [
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
            'position' => $newPosition,
        ]);

        if ($newPosition == 2 || $newPosition == 4) {
            $handSize = $this->game->handSizeCounter->inc($activePlayerId, 1);
            $this->game->notify->all('msg', clienttranslate('${player_name} increases their hand size to ${handSize}'), [
                'player_name' => $this->game->getPlayerNameById($activePlayerId),
                'handSize' => $handSize,
            ]);
        }

        if ($newPosition >= CardManager::RIVER_SIZE) {
            $this->game->playerScore->set($activePlayerId, 1);
            $this->game->playerScore->set((int) $this->game->getOpponentId($activePlayerId), 0);
            return EndScore::class;
        }

        return PlayerDecisions::class;
    }

    #[PossibleAction]
    public function actProgressOnHacking(int $activePlayerId, array $args) {
        if ($args['canHack'] === false) {
            throw new UserException(clienttranslate('You don’t have enough virus to hack'));
        }
        /*
         * Virus token position:
         *   0:          Central port space.
         *   1 to 7:     Matching space on player 2's card.
         *   -7 to -1:   Matching space on player 1's card.
         */

        $position = $this->game->virusTokenPositionCounter->get();
        $direction = $this->game->getPlayerNoById($activePlayerId) == 1 ? 1 : -1;
        $opponentId = $this->game->getOpponentId($activePlayerId);
        $virusLocation = $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_VIRUS, $opponentId);
        $completedCard = null;

        $isDefending = $position * $direction < 0;
        if ($isDefending) {
            // Retreat towards the central port on our own Virus card.
            $newPosition = $position + $direction;
        } else {
            $card = $this->game->cardManager->getTopOfLocation($virusLocation);
            if ($card === null) {
                throw new UserException(clienttranslate('There is no opposing Virus card to advance on'));
            }

            $newPosition = $position + $direction;
            if (abs($newPosition) > $card->virusSpaces) {
                $newPosition = 0;
                $completedCard = $card;
            }
        }

        $this->game->hackingCounter->inc($activePlayerId, -1);
        $this->game->virusTokenPositionCounter->set($newPosition);
        $message = $isDefending
            ? clienttranslate('${player_name} defends on Virus track')
            : clienttranslate('${player_name} attacks on Virus track');
        $this->game->notify->all('msg', $message, [
            'player_name' => $this->game->getPlayerNameById($activePlayerId),
        ]);

        if ($completedCard !== null) {
            $this->game->cardManager->insertCardOnExtremePosition(
                $completedCard,
                $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DECK, $opponentId),
                true,
                true,
                $opponentId
            );
            $remainingVirusCards = $this->game->cardManager->countCardsInLocation($virusLocation);
            $this->game->notify->all('msg', clienttranslate('${player_name} adds a Virus card to the top of ${player_name2}\'s deck (${givenCount}/5)'), [
                'player_name' => $this->game->getPlayerNameById($activePlayerId),
                'player_name2' => $this->game->getPlayerNameById($opponentId),
                'givenCount' => 5 - $remainingVirusCards,
            ]);

            if ($remainingVirusCards === 0) {
                $this->game->playerScore->set($activePlayerId, 1);
                $this->game->playerScore->set($opponentId, 0);
                return EndScore::class;
            }
        }

        return PlayerDecisions::class;
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
        if (!$end) {
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
