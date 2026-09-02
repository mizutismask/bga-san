<?php

namespace Bga\Games\San;

/**
 * A SanCard is a physical card. It contains informations from matching SanCardInfo, with technical informations like id and location.
 * Location : deck or hand
 * Location arg : order (in deck), playerId (in hand)
 * Type : the Wizard type
 * Type arg :  player color
 */
class SanCard extends SanCardInfo {
    public int $id;
    public string $location;
    public int $location_arg;
    public int $type;
    public int $type_arg;

    public function __construct($dbCard, array $additionalParameters) {
        array_key_exists('id', $dbCard) ? $this->id = intval($dbCard['id']) : null;
        array_key_exists('location', $dbCard) ? $this->location = $dbCard['location'] : null;
        array_key_exists('location_arg', $dbCard) ? $this->location_arg = intval($dbCard['location_arg']) : null;
        array_key_exists('type', $dbCard) ? $this->type = intval($dbCard['type']) : null;
        array_key_exists('type_arg', $dbCard) ? $this->type_arg = intval($dbCard['type_arg']) : null;
        $materialInfo = $additionalParameters["material"];
        $cardInfo = $materialInfo[$this->type];
        $this->cardCategory = $cardInfo->cardCategory;
        $this->propaganda = $cardInfo->propaganda;
        $this->hacking = $cardInfo->hacking;
        $this->corruption = $cardInfo->corruption;
        $this->draw = $cardInfo->draw;
        $this->income = $cardInfo->income;
        $this->cost = $cardInfo->cost;
        $this->moveCost = $cardInfo->moveCost;
        $this->virusSpaces = $cardInfo->virusSpaces;
        $this->trashAfterUse = $cardInfo->trashAfterUse;
        $this->chooseOne = $cardInfo->chooseOne;
        $this->destroyCards = $cardInfo->destroyCards;
        $this->specialEffect = $cardInfo->specialEffect;
        $this->text = $cardInfo->text;
    }
}
