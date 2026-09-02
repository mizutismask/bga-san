<?php

namespace Bga\Games\San;

/**
 * A SanCardInfo is the graphic representation of a card (informations on it : power, value, description…).
 */
class SanCardInfo {
    public function __construct(
        public string $cardCategory,
        public int $propaganda,
        public int $hacking,
        public int $corruption,
        public int $draw,
        public int $income,
        public int $cost,
        public int $moveCost,
        public int $virusSpaces,
        public int $trashAfterUse,
        public int $chooseOne,
        public int $destroyCards,
        public int $specialEffect,
        public string $text,
    ) {
    }
}
