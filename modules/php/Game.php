<?php

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * San implementation : © Séverine Kamycki <mizutismask@gmail.com>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */

declare(strict_types=1);

namespace Bga\Games\San;

use Bga\GameFramework\Components\Counters\PlayerCounter;
use Bga\GameFramework\Components\Deck;
use Bga\GameFramework\Table;
use Bga\Games\San\ExpansionManager;
use Bga\Games\San\Material;
use Bga\Games\San\States\NextPlayer;
use Constants;

require_once("constants.inc.php");

class Game extends \Bga\GameFramework\Table {
    use UtilTrait;
    use PlayerUtilTrait;
    use DBUtilTrait;
    use GameUtilTrait;
    use DebugUtilTrait;

    private Deck $cards;
    public CardManager $cardManager;
    public PlayerCounter $propagandaCounter, $propagandaProgressCounter, $hackingCounter, $corruptionCounter, $incomeCounter;
    public ContextManager $contextManager;
    public ExpansionManager $expansionManager;

    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        $this->initGameStateLabels(array(
            //    "my_first_game_variant" => 100,
            //    "my_second_game_variant" => 101,
            //      ...
        ));

        $this->expansionManager = new ExpansionManager($this);

        $this->propagandaCounter = $this->counterFactory->createPlayerCounter("propaganda");
        $this->propagandaProgressCounter = $this->counterFactory->createPlayerCounter("propagandaProgress");
        $this->hackingCounter = $this->counterFactory->createPlayerCounter("hacking");
        $this->corruptionCounter = $this->counterFactory->createPlayerCounter("corruption");
        $this->incomeCounter = $this->counterFactory->createPlayerCounter("income");

        $this->cards = $this->deckFactory->createDeck("card");
        $this->cards->autoreshuffle = false;
        $this->expansionManager = new ExpansionManager($this);
        $this->cardManager = new CardManager($this, TABLE_CARD, $this->cards, "SanCard", Constants::MATERIAL_TYPE_CARD, ["material" => Material::getCards()[Constants::EXPANSION]]);
        $this->contextManager = new ContextManager($this);
    }

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = array()): string {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];
        //won’t setup probably because of namespace case error or import or compile error
        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("(%s, '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                addslashes($player["player_name"]),
            ]);
        }

        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                "INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $this->reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Init global values with their initial values
        //$this->setGameStateInitialValue( 'my_first_global_variable', 0 );
        //initialize everything to be compliant with undo framework
        //foreach ($this->GAMESTATELABELS as $value_label => $ID) if ($ID >= 10 && $ID < 90) $this->setGameStateInitialValue($value_label, 0);

        $this->initStats();
        $this->propagandaCounter->initDb(array_keys($players));
        $this->propagandaProgressCounter->initDb(array_keys($players));
        $this->hackingCounter->initDb(array_keys($players));
        $this->corruptionCounter->initDb(array_keys($players));
        $this->incomeCounter->initDb(array_keys($players));

        // TODO: setup the initial game situation here
        $this->globals->set(Constants::LAST_TURN, 0); // last turn is the id of the last player, 0 if it's not last turn
        $this->setupTable($players);

        // does not activate player since it’s done within stNextPlayer
        return NextPlayer::class;

        /************ End of the game initialization *****/
    }

    function setupTable(array $players) {
        $this->setupSharedItems();
        //$this->cardManager->dealHands();
        foreach ($players as $playerId => $player) {
        }
    }

    function setupSharedItems() {
        $this->cardManager->createCards($this->expansionManager->getCardsToGenerate(), false, "destroyed");
        $locationPlayer1Deck = $this->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DECK, $this->getPlayerIdFromPosition(1));
        $query = "UPDATE card SET card_location = '" . $locationPlayer1Deck . "', card_location_arg = card_type-1 WHERE card_type BETWEEN 1 AND 12";
        Table::DbQuery($query);

        $locationPlayer2Deck = $this->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DECK, $this->getPlayerIdFromPosition(2));
        $query = "UPDATE card SET card_location = '" . $locationPlayer2Deck . "', card_location_arg = card_type-1 WHERE card_type BETWEEN 18 AND 29";
        Table::DbQuery($query);

        $query = "UPDATE card SET card_location = '" . Constants::MATERIAL_LOCATION_DECK . "', card_location_arg = card_type-1 WHERE card_type BETWEEN 35 AND 82";
        Table::DbQuery($query);

        $locationPlayer1Virus = $this->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_VIRUS, $this->getPlayerIdFromPosition(1));
        $locationPlayer2Virus = $this->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_VIRUS, $this->getPlayerIdFromPosition(2));

        // Virus cards should be sorted from 1 to 5 for each player
        $query = "UPDATE card SET card_location = '" . $locationPlayer1Virus . "', card_location_arg = 5 - (card_type - 13) WHERE card_type BETWEEN 13 AND 17;";
        Table::DbQuery($query);
        $query = "UPDATE card SET card_location = '" . $locationPlayer2Virus . "', card_location_arg = 5 - (card_type - 30) WHERE card_type BETWEEN 30 AND 34;";
        Table::DbQuery($query);

        $this->cards->shuffle($locationPlayer1Deck);
        $this->cards->shuffle($locationPlayer2Deck);
        $this->cards->shuffle(Constants::MATERIAL_LOCATION_DECK);

        $this->cardManager->initRiver(6);
        $this->cardManager->dealHands();
    }

    function getPlayerLocation(string $location, int $playerId) {
        return $location . "_" . $playerId;
    }

    function hasReachedEndOfGameRequirements(): bool {
        //TODO
        return $this->globals->get("round") == 4;
    }

    /**
     * Activates next player, also giving him extra time.
     */
    function activateNextPlayerCustom() {
        $player_id = $this->activeNextPlayer();
        $this->cardManager->replenishHands();
        $this->giveExtraTime($player_id);
        $this->playerStats->inc('turns_number', 1, $player_id);
        $this->tableStats->inc('turns_number', 1);
        $this->notify->all('msg', clienttranslate('&#10148; Start of ${player_name}\'s turn'), ['player_name' => $this->getPlayerNameById($player_id)]);
        //$this->makeSavepoint();
        return $player_id;
    }


    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas(int $currentPlayerId): array {
        $stateName = $this->getStateName();
        $isEnd = $stateName === 'EndScore' || $stateName === 'gameEnd' || $stateName === 'DebugGameEnd';

        $result = [];
        $result['expansion'] = $this->expansionManager->getExpansion();
        $result['version'] = $this->getGameVersion();

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score, player_no playerNo FROM player ";
        $result['players'] = $this->getCollectionFromDb($sql);
        $result['playerOrderWorkingWithSpectators'] = $this->getPlayerIdsInOrder($currentPlayerId);
        $result['turnOrderClockwise'] = true;
        $result['river'] = $this->cardManager->getRiverCards();
        $result['riverDeckTopCard'] = $this->cardManager->getTopOfLocation(Constants::MATERIAL_LOCATION_DECK);
        $result['riverDeckCount'] = $this->cardManager->countCardsInLocation(Constants::MATERIAL_LOCATION_DECK);
        $result['corruptedCards'] = [];
        $result['virusCards'] = [];
        $result['playedCards'] = [];
        $revealedPlayedCards = $this->globals->get('revealedPlayedCards', []);
        foreach (array_keys($result['players']) as $playerId) {
            foreach ($this->cardManager->getPlayedCards($playerId) as $card) {
                if ((int) $playerId === (int) $currentPlayerId || in_array($card->id, $revealedPlayedCards, true)) {
                    $result['playedCards'][] = $card;
                }
            }
            $result['virusCards'][$playerId] = $this->cardManager->getCardsInLocation($this->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_VIRUS, $playerId));
            $result['corruptedCards'][$playerId] = $this->cardManager->getCardsInLocation($this->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_CORRUPTION, $playerId));
        }

        //counters
        $this->propagandaCounter->fillResult($result);
        $this->propagandaProgressCounter->fillResult($result);
        $this->hackingCounter->fillResult($result);
        $this->corruptionCounter->fillResult($result);
        $this->incomeCounter->fillResult($result);

        $result['hand'] = $this->cardManager->getPlayerHand($currentPlayerId);

        foreach ($result['players'] as $playerId => &$player) {
            $currentPlayerOrder = intval($player['playerNo']);
            $player['playerNo'] = $currentPlayerOrder;
            $player['symbol'] = $currentPlayerOrder == 1 ? "moon" : "star";
            //$player['discard'] = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $currentPlayerOrder, MATERIAL_LOCATION_DISCARD);

            // $player['cardsCount'] = intval($this->actionCards->countCardInLocation("hand", $playerId));
        }

        // TODO: Gather all information about current game situation (visible by player $current_player_id).
        if (!$isEnd) {
            $result['lastTurn'] = $this->globals->get(Constants::LAST_TURN) > 0;
        }
        return $result;
    }

    function notifyCounterChange() {
        /*$deckCount = $this->tileManager->countCardsInLocation('deck');
        if ($this->tileCounter->get() !== $deckCount) {
            $this->tileCounter->set($deckCount);
        }

        foreach ($this->getPlayers() as $playerId => $player) {
            $handCount = $this->tileManager->getPlayerHandCount($playerId);
            if ($this->tilesCounter->get($playerId) !== $handCount) {
                $this->tilesCounter->set($playerId, $handCount);
            }
        }*/
    }


    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression() {
        $stateName = $this->getStateName();
        if ($stateName === 'EndScore' || $stateName === 'GameEnd' || $stateName === 'DebugGameEnd') {
            // game is over
            return 100;
        }
        /*$roundProgression = 100 * count($this->cardManager->getGridCards()) / 12;
        
        $round = intval($this->globals->get(GLB_ROUND));
        return (100 * $this->getMaxScore() / 2) + $roundProgression / ($round == 3 ? 3 : 2);*/
        return 0;
    }

    function getGameVersion(): int {
        return $this->bga->tableOptions->get(300);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    
    function makeSavepoint($player_id = null) {
        $this->undoSavepoint();
    }

    function toggleResetTurn(bool $value) {
        $this->globals->set(Constants::CAN_RESET_TURN, $value);
    }
    /*
        In this space, you can put any utility methods useful for your game logic
    */

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// DB upgrade
    //////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */

    function upgradeTableDb($from_version) {
        $changes = [
            // [2307071828, "INSERT INTO DBPREFIX_global (`global_id`, `global_value`) VALUES (24, 0)"], 
        ];

        foreach ($changes as [$version, $sql]) {
            if ($from_version <= $version) {
                try {
                    $this->warn("upgradeTableDb apply 1: from_version=$from_version, change=[ $version, $sql ]");
                    $this->applyDbUpgradeToAllDB($sql);
                } catch (\Exception $e) {
                    // See https://studio.boardgamearena.com/bug?id=64
                    // BGA framework can produce invalid SQL with non-existant tables when using DBPREFIX_.
                    // The workaround is to retry the query on the base table only.
                    $this->error("upgradeTableDb apply 1 failed: from_version=$from_version, change=[ $version, $sql ]");
                    $sql = str_replace("DBPREFIX_", "", $sql);
                    $this->warn("upgradeTableDb apply 2: from_version=$from_version, change=[ $version, $sql ]");
                    $this->applyDbUpgradeToAllDB($sql);
                }
            }
        }
        $this->warn("upgradeTableDb complete: from_version=$from_version");
    }
}
