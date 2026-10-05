<?php

class Constants {
    /*
     * Custom framework constants
     */
    const MATERIAL_TYPE_CARD = "CARD";
    const MATERIAL_TYPE_TOKEN = "TOKEN";
    const MATERIAL_TYPE_TILE = "TILE";
    const MATERIAL_TYPE_ACTION_CARD = "ACTION_CARD";
    const MATERIAL_TYPE_FIRST_PLAYER_TOKEN = "FIRST_PLAYER_TOKEN";

    const MATERIAL_LOCATION_HAND = "hand";
    const MATERIAL_LOCATION_DECK = "deck";
    const MATERIAL_LOCATION_STOCK = "stock";
    const MATERIAL_LOCATION_DISCARD = "discard";
    const MATERIAL_LOCATION_PLAYER_DISCARD = "plyr_discard";
    const MATERIAL_LOCATION_RIVER = "river";
    const MATERIAL_LOCATION_DESTROYED = "destroyed";
    const MATERIAL_LOCATION_PLAYER_DECK = "player_deck";
    const MATERIAL_LOCATION_PLAYER_PLAY_AREA = "play_area";
    const MATERIAL_LOCATION_PLAYER_CORRUPTION = "corr";
    const MATERIAL_LOCATION_PLAYER_VIRUS = "virus";

    /*
     * Game constants
     */
    const LAST_TURN = 'LAST_TURN';
    const CAN_RESET_TURN = "CAN_RESET_TURN";

    const CARD_TYPE_CORRUPTION = 1;
    const CARD_TYPE_PROPAGANDA = 2;
    const CARD_TYPE_HACKING = 3;
    const CARD_TYPE_HARDWARE = 4;
    const CARD_TYPE_VIRUS = 5;

    const SPECIAL_EFFECT_NONE = 0;
    const SPECIAL_EFFECT_PROPAGANDA_PER_PROPAGANDA_CARD = 3;
    const SPECIAL_EFFECT_HACKING_PER_VIRUS_CARD = 5;
    const SPECIAL_EFFECT_CORRUPTION_PER_CORRUPTION_CARD = 7;
    const SPECIAL_EFFECT_PLAY_FROM_DISCARD = 8;
    const SPECIAL_EFFECT_COPY_PLAYED_CARD = 9;
    const SPECIAL_EFFECT_CORRUPT_FROM_HAND = 10;
    const SPECIAL_EFFECT_PLAY_ALL_CARD_TYPES = 11;
    const SPECIAL_EFFECT_COPY_RIVER_CARD = 12;
    const SPECIAL_EFFECT_GAIN_PROPAGANDA_HACKING_AND_CORRUPTION = 13;

    const ACTION_DRAW = 4;

    const GLB_CURRENT_CARD = "currentCard";
    const GLB_COPIED_PLAYED_CARDS = "copiedPlayedCards";
    const GLBL_REMAINING_DESTROYS = "remainingDestroys";

    /**
     * Options
     */
    const EXPANSION = 0; // 0 => base game

    /*
     * State constants
     */
    const STATE_ID_BGA_GAME_SETUP = 1;

    const STATE_ID_NEXT_PLAYER = 2;
    const STATE_ID_PLAYER_TURN = 30;
    const STATE_ID_IMMEDIATE_ACTION = 31;
    const STATE_ID_PLAYER_DECISION = 32;
    const STATE_ID_SHOPPING = 33;
    const STATE_ID_END_SCORE = 96;
    const STATE_ID_DEBUG_GAME_END = 97;
    const STATE_ID_GAME_END = 99;
}
