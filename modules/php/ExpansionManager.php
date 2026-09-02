<?php

namespace Bga\Games\San;

use Constants;

class ExpansionManager {
    public function __construct(private Game $game) {
    }

    function getExpansion() {
        return 0; //$this->game->isSpiritCardsOn() ? SPIRITS : EXPANSION;
        //TODO see if necessary
    }

    function getBoard() {
        return $this->game->tableOptions->get(101);
    }

    
    function getCardsToGenerate() {
        $cards = [];
        foreach (Material::getCards()[Constants::EXPANSION] as $type => $card) {
            $cards[] = ['type' => $type, 'type_arg' => $card->cardCategory, 'nbr' => 1];
        }
        return $cards;
    }


    function getSquareTypes(int $boardId, int $width, int $height) {
        /* $grid = $this->getEmptyGrid($width, $height);
        switch ($boardId) {
            case 1:
                $mountains = [0, 3, 9, 14, 20, 22, 29, 32, 33, 40, 43, 45, 51, 56, 62, 78, 83, 89, 91, 94, 101, 108, 110, 111, 112, 113, 127, 130, 137, 138, 143];
                foreach ($mountains as $square) {
                    $grid[$square]->landType = LandType::MOUNTAIN;
                }
                $water = [1, 13, 18, 19, 25, 26, 38, 58, 59, 64, 65, 68, 69, 70, 73, 76, 84, 87, 92, 93, 98, 103, 100, 106, 115, 118, 120, 128, 129, 136];
                foreach ($water as $square) {
                    $grid[$square]->landType = LandType::WATER;
                }
                foreach ($this->getDiscoverySquares($boardId) as $square) {
                    $grid[$square]->landType = LandType::DISCOVERY;
                }
                break;
            case 2:
                $mountains = [0, 1, 3, 10, 15, 21, 35, 42, 43, 52, 53, 59, 62, 63, 72, 73, 80, 92, 104, 115, 116, 117, 118, 119, 120, 121, 129, 133, 134, 135, 136, 137, 141];
                foreach ($mountains as $square) {
                    $grid[$square]->landType = LandType::MOUNTAIN;
                }
                $water = [8, 13, 14, 20, 25, 26, 32, 44, 54, 55, 56, 64, 65, 66, 74, 75, 76, 78, 84, 85, 86, 90, 95, 102, 114, 122, 126, 130, 138];
                foreach ($water as $square) {
                    $grid[$square]->landType = LandType::WATER;
                }
                foreach ($this->getDiscoverySquares($boardId) as $square) {
                    $grid[$square]->landType = LandType::DISCOVERY;
                }
        }
        return $grid;*/
    }

    function getNationTilesToGenerate() {
        $cards = [];
        switch ($this->getExpansion()) {
            default:
                /* $cards = array(
                    array('type' => Constants::NATION_ELF, 'type_arg' => 0, 'nbr' => 14),
                    array('type' => Constants::NATION_DRAGON, 'type_arg' => 0, 'nbr' => 9),
                    array('type' => Constants::NATION_DWARF, 'type_arg' => 0, 'nbr' => 10),
                    array('type' => Constants::NATION_FAIRY, 'type_arg' => 0, 'nbr' => 7),
                    array('type' => Constants::NATION_GHOST, 'type_arg' => 0, 'nbr' => 8),
                    array('type' => Constants::NATION_GOBLIN, 'type_arg' => 0, 'nbr' => 13),
                    array('type' => Constants::NATION_SORCERER, 'type_arg' => 0, 'nbr' => 8),
                    array('type' => Constants::NATION_MERMAID, 'type_arg' => 0, 'nbr' => 11),
                );*/
                break;
        }

        return $cards;
    }

    function getTreasureTilesToGenerate() {
        $cards = [];
        switch ($this->getExpansion()) {
            default:
                $cards = array(
                    array('type' => 0, 'type_arg' => 0, 'nbr' => 20),
                );
                break;
        }

        return $cards;
    }

    function getHandSize(): int {
        return 6;
    }

    function isZoneDefenseOn(): bool {
        return $this->game->tableOptions->get(105) == 1;
    }
}
