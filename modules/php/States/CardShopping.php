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

class CardShopping extends GameState {

    function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_SHOPPING,
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate(_('${actplayer} can buy cards')),
            descriptionMyTurn: clienttranslate('${you} can spend ${income} to buy cards from the river'),
        );
    }

    function onEnteringState(int $activePlayerId, array $args) {
        if (empty($args['possibleCards'])) {
            return $this->actPass($activePlayerId);
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
            "canPass" => true,
            "income" => $this->game->incomeCounter->get($activePlayerId),
            "possibleCards" => $this->getPossibleCards($activePlayerId),
        ];
    }

    #[PossibleAction]
    public function actBuyCard(int $cardId, int $activePlayerId, array $args) {
        $sanCard = null;
        foreach ($args['possibleCards'] as $card) {
            if ($card->id === $cardId) {
                $sanCard = $card;
                break;
            }
        }
        if ($sanCard === null) {
            throw new UserException(clienttranslate('You don’t have enough income to buy this card'));
        }
        $riverRefilled = $this->game->cardManager->buyCard($card, $activePlayerId);
        if ($riverRefilled) {
            return CardShopping::class;
        }
        return EndScore::class;
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player pass, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     */
    #[PossibleAction]
    public function actPass(int $activePlayerId) {
        $this->game->cardManager->discardPlayedCards($activePlayerId);
        return NextPlayer::class;
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
        // buy the highest cost hardware card that you can afford, or the highest cost of the type you have the most in your entire deck that you can afford
        if (empty($args['possibleCards'])) {
            return $this->actPass($playerId);
        }

        $bestCard = null;
        foreach ($args['possibleCards'] as $card) {
            if ($card->type_arg === Constants::CARD_TYPE_HARDWARE
                && ($bestCard === null || $card->cost > $bestCard->cost)) {
                $bestCard = $card;
            }
        }

        if ($bestCard === null) {
            $typeCounts = [];
            foreach ([Constants::MATERIAL_LOCATION_PLAYER_DECK, Constants::MATERIAL_LOCATION_HAND, Constants::MATERIAL_LOCATION_PLAYER_DISCARD, Constants::MATERIAL_LOCATION_PLAYER_PLAY_AREA] as $location) {
                foreach ($this->game->cardManager->getCardsInLocation($this->game->getPlayerLocation($location, $playerId)) as $card) {
                    $typeCounts[$card->type_arg] = ($typeCounts[$card->type_arg] ?? 0) + 1;
                }
            }

            $bestCount = empty($typeCounts) ? 0 : max($typeCounts);
            foreach ($args['possibleCards'] as $card) {
                $count = $typeCounts[$card->type_arg] ?? 0;
                if ($count > 0 && $count === $bestCount
                    && ($bestCard === null || $card->cost > $bestCard->cost)) {
                    $bestCard = $card;
                }
            }
        }

        if ($bestCard === null) {
            return $this->actPass($playerId);
        }

        return $this->actBuyCard($bestCard->id, $playerId, $args);
    }

    function getPossibleCards(int $activePlayerId) {
        $income = $this->game->incomeCounter->get($activePlayerId);
        $possibleCards = [];

        foreach ($this->game->cardManager->getRiverCards() as $card) {
            if ($card->cost <= $income) {
                $possibleCards[] = $card;
            }
        }

        return $possibleCards;
    }
}
