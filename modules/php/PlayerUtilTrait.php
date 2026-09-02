<?php

namespace Bga\Games\San;

use Bga\GameFramework\VisibleSystemException;

/** @mixin Game */
trait PlayerUtilTrait {

    function getNonZombiePlayersIds() {
        $sql = "SELECT player_id FROM player WHERE player_eliminated = 0 AND player_zombie = 0 ORDER BY player_no";
        $dbResults = $this->getCollectionFromDB($sql);
        return array_map(fn($dbResult) => intval($dbResult['player_id']), array_values($dbResults));
    }

    /**
     *
     * @return integer player position (as player_no) from database
     */
    function getPlayerPosition($player_id): int {
        $players = $this->loadPlayersBasicInfos();
        if (!isset($players[$player_id])) {
            return -1;
        }
        return intval($players[$player_id]['player_no']);
    }


    /**
     * Returns the teammate of the given player (for 4 players).
     */
    public function getTeamMate(int $playerId): int {
        $players = $this->getPlayers();
        $playerNoMod = $players[$playerId]['player_no'] % 2;

        foreach ($players as $player) {
            if ($player['player_id'] != $playerId && $player['player_no'] % 2 == $playerNoMod) {
                return $player['player_id'];
            }
        }
        throw new VisibleSystemException('Team mate not found');
    }

    function getPlayersInOrder() {
        $result = array();

        $players = $this->loadPlayersBasicInfos();
        $next_player = $this->getNextPlayerTable();
        $player_id = $this->getCurrentPlayerId();

        // Check for spectator
        if (!key_exists($player_id, $players)) {
            $player_id = $next_player[0];
        }

        // Build array starting with current player
        for ($i = 0; $i < count($players); $i++) {
            $result[$player_id] = $players[$player_id];
            $player_id = $next_player[$player_id];
        }
        return $result;
    }

    function getPlayersIds() {
        return array_keys($this->loadPlayersBasicInfos());
    }

    function getOpponentId($currentPlayerId) {
        return $this->getFirstElementInArray(array_diff($this->getPlayersIds(), [$currentPlayerId]));
    }

    function getPlayerIdFromPosition($position) {
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $player) {
            if ($player['player_no'] == $position) {
                return $player_id;
            }
        }
        return null;
    }

    function getPlayers() {
        return $this->loadPlayersBasicInfos();
    }

    function getPlayerColor($playerId) {
        $sql = "SELECT player_id, player_color FROM player WHERE player_id = $playerId";
        return $this->getNonEmptyObjectFromDb($sql);
    }

    function getPlayerIdsInOrder($starting) {
        $player_ids = $this->getPlayersIds();
        $rotate_count = array_search($starting, $player_ids);
        if ($rotate_count === false) {
            return $player_ids;
        }
        for ($i = 0; $i < $rotate_count; $i++) {
            array_push($player_ids, array_shift($player_ids));
        }
        //var_dump("getPlayerIdsInOrder()",$player_ids); 
        return $player_ids;
    }
    function getPlayerIdByOrder($playerOrder = 1) {
        return $this->getUniqueIntValueFromDB("SELECT player_id FROM player where `player_no` = $playerOrder");
    }

    function getLastPlayer() {
        return $this->getPlayerIdByOrder($this->getPlayerCount());
    }

    function isLastPlayer(int $playerId) {
        return $this->getLastPlayer() == $playerId;
    }

    function getWinners() {
        $sql = "SELECT player_id FROM player WHERE player_score = (SELECT max(player_score) from player)";
        return $this->getObjectListFromDB($sql, true);
    }

    function isEveryPlayerScoreEqualTo(int $score) {
        return $this->getUniqueValueFromDB("SELECT count(*) from player where player_score = $score") == $this->getPlayerCount();
    }

    function incPlayerField(int $playerId, String $field, int $delta) {
        static::DbQuery("UPDATE player SET `$field` = `$field` + $delta where `player_id` = $playerId");
    }

    function updatePlayer(int $playerId, String $field, int $newValue) {
        static::DbQuery("UPDATE player SET $field = $newValue WHERE player_id = $playerId");
    }

    function updatePlayersExceptOne(int $playerId, String $field, int $newValue) {
        static::DbQuery("UPDATE player SET $field = $newValue WHERE player_id != $playerId");
    }

    function getPlayerFieldValue(int $playerId, String $field) {
        return $this->getUniqueValueFromDB("select $field from player WHERE player_id = $playerId");
    }

    function getUniquePlayerIdByFieldValue(string $field, string $value) {
        return $this->getUniqueValueFromDB("select player_id from player WHERE $field = $value");
    }

    function getMostlyActivePlayerId() {
        $type = $this->gamestate->getCurrentMainState()->type;
        if ($type === "multipleactiveplayer") {
            return intval($this->getCurrentPlayerId());
        } else {
            return intval($this->getActivePlayerId());
        }
    }

    function getMostlyActivePlayerOrder() {
        return $this->getPlayerPosition($this->getMostlyActivePlayerId());
    }

    function getPlayerGlobal(int $playerId, string $key, $defaultValue = null) {
        return $this->globals->get($key . "-" . $playerId, $defaultValue);
    }
    function setPlayerGlobal(int $playerId, string $key, $value) {
        $this->globals->set($key . "-" . $playerId, $value);
    }
    function incPlayerGlobal(int $playerId, string $key, $value) {
        return $this->globals->inc($key . "-" . $playerId, $value);
    }
}
