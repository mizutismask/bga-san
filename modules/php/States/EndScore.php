<?php

declare(strict_types=1);

namespace Bga\Games\San\States;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\StateType;
use Bga\Games\San\Game;
use Constants;

const ST_END_GAME = 99;

const SCORE_GAIN_PER_RARE_TREASURE = 3;
const SCORE_LOSE_PER_RATS = 1;
const SCORE_LOSE_PER_UNFILLED_ROOMS = 5;
const SCORE_SOLO_COLOR = 5;


class EndScore extends \Bga\GameFramework\States\GameState {

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: Constants::STATE_ID_END_SCORE,
            type: StateType::GAME,
        );
    }

    /**
     * Game state action, example content.
     *
     * The onEnteringState method of state `EndScore` is called just before the end of the game.
     */
    public function onEnteringState() {
        $this->recordStatistics();

        if ($this->game->isStudio()) {
            $this->game->stMakeEveryoneActive();
            return DebugGameEnd::class;
        } else {
            return ST_END_GAME;
        }
    }

    private function recordStatistics(): void {
        $locations = [
            Constants::MATERIAL_LOCATION_PLAYER_DECK,
            Constants::MATERIAL_LOCATION_HAND,
            Constants::MATERIAL_LOCATION_PLAYER_DISCARD,
            Constants::MATERIAL_LOCATION_PLAYER_PLAY_AREA,
        ];
        $categoryStats = [
            Constants::CARD_TYPE_PROPAGANDA => 'game_propaganda_owned_cards',
            Constants::CARD_TYPE_CORRUPTION => 'game_corruption_owned_cards',
            Constants::CARD_TYPE_HACKING => 'game_hacking_owned_cards',
            Constants::CARD_TYPE_HARDWARE => 'game_material_owned_cards',
        ];
        foreach ($this->game->getPlayersIds() as $playerId) {
            $playerId = (int) $playerId;
            $corrupted = $this->game->cardManager->countCardsInLocation(
                $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $playerId)
            );
            $propaganda = $this->game->propagandaProgressCounter->get($playerId);
            $givenViruses = 5 - $this->game->cardManager->countCardsInLocation(
                $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_VIRUS, (int) $this->game->getOpponentId($playerId))
            );
            $categories = [];
            foreach ($locations as $location) {
                $cards = $this->game->cardManager->getCardsInLocation($this->game->getPlayerLocation($location, $playerId));
                foreach ($cards as $card) {
                    $categories[] = $card->cardCategory;
                }
            }
            $categoryCounts = array_count_values($categories);
            $values = [
                'game_corrupted_cards' => $corrupted,
                'game_propaganda_progression' => $propaganda,
                'game_virus_cards_given' => $givenViruses,
                'game_owned_cards' => count($categories),
            ];
            foreach ($categoryStats as $category => $name) {
                $values[$name] = $categoryCounts[$category] ?? 0;
            }
            foreach ($values as $name => $value) {
                $this->game->playerStats->set($name, $value, $playerId);
            }
        }
        $this->game->tableStats->set('winning_type', (int) $this->game->globals->get('winningType'));
    }
}
