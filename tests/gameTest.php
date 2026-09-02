<?php
define("APP_GAMEMODULE_PATH", "../misc/"); // include path to stubs, which defines "table.game.php" and other classes
require_once('./gameBaseTest.php');

class GameTest extends GameTestBase { // this is your game class defined in ggg.game.php
    function __construct() {
        // parent::__construct();
        include '../material.inc.php'; // this is how this normally included, from constructor
    }

    /** Redefine some function of the game to mock data. Todo : rename getData to match your function */
    function getData($playerId = null) {

        return [];
    }

    // class tests
    function testYourTestNameColorsAllowed() {
        /*
       $result = $this->isColorAllowedOnTopOfOtherColor());

        $equal = $result == null;

        $this->displayResult(__FUNCTION__, $equal, $result);
        */
    }

    function testAll() {
        $this->testYourTestNameColorsAllowed();
    }
}

$test1 = new GameTest();
$test1->testAll();
