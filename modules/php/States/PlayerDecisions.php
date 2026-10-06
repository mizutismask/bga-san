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

        $card = $this->game->cardManager->getCard($cardId);
        if ($this->game->cardManager->corruptCard($card, $slot, $activePlayerId)) {
            return EndScore::class;
        }
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
            $this->game->announceEndCondition(clienttranslate('${player_name} reaches the end of the propaganda track and wins the game'), [
                'player_name' => $this->game->getPlayerNameById($activePlayerId),
            ]);
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

        $hacking = $this->game->hackingCounter->get($activePlayerId);
        $defenses = 0;
        $attacks = 0;
        $card = null;
        if ($hacking > max(0, -$position * $direction)) {
            $card = $this->game->cardManager->getTopOfLocation($virusLocation);
            if ($card === null) {
                throw new UserException(clienttranslate('There is no opposing Virus card to advance on'));
            }
        }

        while ($defenses + $attacks < $hacking && $completedCard === null) {
            $isDefending = $position * $direction < 0;
            $position += $direction;
            if ($isDefending) {
                $defenses++;
            } else {
                $attacks++;
                if (abs($position) > $card->virusSpaces) {
                    $position = 0;
                    $completedCard = $card;
                }
            }

            $this->game->virusTokenPositionCounter->set($position, null);
            $this->game->notify->all('virusTokenMoved', '', ['position' => $position]);
        }

        $this->game->hackingCounter->inc($activePlayerId, -($defenses + $attacks));
        foreach (['defenses' => $defenses, 'attacks' => $attacks] as $type => $count) {
            if ($count === 0) {
                continue;
            }
            $message = $type === 'defenses'
                ? clienttranslate('${player_name} defends ${count} ${virusIcon}')
                : clienttranslate('${player_name} attacks ${count} ${virusIcon}');
            $this->game->notify->all('msg', $message, [
                'player_name' => $this->game->getPlayerNameById($activePlayerId),
                'count' => $count,
                'virusIcon' => 'virus',
            ]);
        }

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
                $this->game->announceEndCondition(clienttranslate('${player_name} has given his opponent all five Virus cards and wins the game'), [
                    'player_name' => $this->game->getPlayerNameById($activePlayerId),
                ]);
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
        if ($args['canProgressOnProp']) {
            return $this->actProgressOnProp($playerId, $args);
        }
        if ($args['canHack']) {
            return $this->actProgressOnHacking($playerId, $args);
        }
        if ($args['canCorrupt']) {
            $riverCards = $this->game->cardManager->getRiverCards();
            if (!empty($riverCards)) {

                //the card to be corrupted is the one after the opponent progression if its progression cost is under 5, or the one after the player progression if it’s over 4, or any card in the river if none of the above
                $card = reset($riverCards);
                $opponentId = (int) $this->game->getOpponentId($playerId);
                $opponentSlot = $this->game->mirrorSlot(
                    $this->game->propagandaProgressCounter->get($opponentId) + 1,
                    $opponentId
                );
                $playerSlot = $this->game->mirrorSlot(
                    $this->game->propagandaProgressCounter->get($playerId) + 1,
                    $playerId
                );
                foreach ($riverCards as $riverCard) {
                    if ($riverCard->location_arg === $opponentSlot && $riverCard->moveCost < 5) {
                        $card = $riverCard;
                        break;
                    }
                    if ($riverCard->location_arg === $playerSlot && $riverCard->moveCost > 4) {
                        $card = $riverCard;
                    }
                }
                for ($slot = 1; $slot <= CardManager::RIVER_SIZE; $slot++) {
                    $boardSlot = $this->game->mirrorSlot($slot, $playerId);
                    if (count($this->game->cardManager->getCorruptedCardsOnSlot($boardSlot, $playerId)) < 2) {
                        return $this->actCorrupt($card->id, $slot, $playerId, $args);
                    }
                }
            }
        }
        return $this->actPass($playerId);
    }
}
