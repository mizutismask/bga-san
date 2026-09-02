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

    const GLB_CURRENT_CARD = "currentCard";

    /**
     * Options
     */
    const EXPANSION = 0; // 0 => base game

    /*
     * State constants
     */
    const STATE_ID_BGA_GAME_SETUP = 1;

    const STATE_ID_NEXT_PLAYER = 2;
    const STATE_ID_NEXT_ROUND = 3;
    const STATE_ID_END_OF_ROUND = 4;
    const STATE_ID_PLAYER_TURN = 30;
    const STATE_ID_IMMEDIATE_ACTION = 31;
    const STATE_ID_PLAYER_DECISION = 32;
    const STATE_ID_SHOPPING = 33;
    const STATE_ID_END_SCORE = 96;
    const STATE_ID_DEBUG_GAME_END = 97;
    const STATE_ID_GAME_END = 99;
}
