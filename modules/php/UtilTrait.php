<?php

namespace Bga\Games\San;

use Bga\GameFramework\UserException;

trait UtilTrait {

    function array_find(array $array, callable $fn) {
        foreach ($array as $value) {
            if ($fn($value)) {
                return $value;
            }
        }
        return null;
    }

    function array_find_index(array $array, callable $fn) {
        foreach ($array as $index => $value) {
            if ($fn($value)) {
                return $index;
            }
        }
        return null;
    }

    function array_some(array $array, callable $fn) {
        foreach ($array as $value) {
            if ($fn($value)) {
                return true;
            }
        }
        return false;
    }

    function array_every(array $array, callable $fn) {
        foreach ($array as $value) {
            if (!$fn($value)) {
                return false;
            }
        }
        return true;
    }

    function getIds(array $cards) {
        $ids = [];
        foreach ($cards as $card) {
            $ids[] = $card->id;
        }
        return $ids;
    }

    function getFirstElementInArray($pArray) {
        return $pArray[array_key_first($pArray)] ?? null;
    }

    public function getStateName() {
        return $this->gamestate->getCurrentMainState()->name;
    }

    public function getRandomKey(array &$array) {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomKey(): Array is empty", E_USER_WARNING);
            return null;
        }
        $rand = random_int(0, $size - 1);
        $slice = array_slice($array, $rand, 1, true);
        foreach ($slice as $key => $value) {
            return $key;
        }
    }

    public function getRandomValue(array &$array) {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomValue(): Array is empty", E_USER_WARNING);
            return null;
        }
        $rand = random_int(0, $size - 1);
        $slice = array_slice($array, $rand, 1, true);
        foreach ($slice as $key => $value) {
            return $value;
        }
    }

    public function getRandomSlice(array &$array, int $count) {
        $size = count($array);
        if ($size == 0) {
            trigger_error("getRandomSlice(): Array is empty", E_USER_WARNING);
            return null;
        }
        if (
            $count < 1 || $count > $size
        ) {
            trigger_error(
                "getRandomSlice(): Invalid count $count for array with size $size",
                E_USER_WARNING
            );
            return null;
        }
        $slice = [];
        $randUnique = [];
        while (count($randUnique) < $count) {
            $rand = random_int(0, $size - 1);
            if (array_key_exists($rand, $randUnique)) {
                continue;
            }
            $randUnique[$rand] = true;
            $slice += array_slice($array, $rand, 1, true);
        }
        return $slice;
    }

    /**
     * Auto initialize stats. Note for this to work your game stats ids have to be prefixed by game_ (verbatim)
     */
    public function initStats() {
        $all_stats = $this->getStatTypes();
        $player_stats = $all_stats['player'];
        // auto-initialize all stats that starts with game_
        // we need a prefix because there is some other system stuff
        foreach ($player_stats as $key => $value) {
            if (str_starts_with($key, 'game_')) {
                $this->initStat('player', $key, 0);
            }
            if ($key === 'turns_number') {
                $this->initStat('player', $key, 0);
            }
        }
        $table_stats = $all_stats['table'];
        foreach ($table_stats as $key => $value) {
            if (str_starts_with($key, 'game_')) {
                $this->initStat('table', $key, 0);
            }
            if ($key === 'turns_number') {
                $this->initStat('table', $key, 0);
            }
        }
    }

    function isStudio() {
        return ($this->getBgaEnvironment() == 'studio');
    }

    function debugConsole($info, $args = []) {
        $this->notifyAllPlayers("log", '', ['log' => $info, 'args' => $args]);
        $this->warn($info);
    }

    function getPart(string $haystack, int $i, bool $noException = false, string $separator = '_'): string {
        $parts = explode($separator, $haystack);
        $len = count($parts);
        if ($noException && $i >= $len)
            return "";
        if ($noException && $len + $i < 0)
            return "";

        return $parts[$i >= 0 ? $i : $len + $i];
    }

    function getPartsPrefix(string $haystack, int $i) {
        $parts = explode('_', $haystack);
        $len = count($parts);
        if ($i < 0) {
            $i = $len + $i;
        }
        if ($i <= 0)
            return '';
        for (; $i < $len; $i++) {
            unset($parts[$i]);
        }
        return implode('_', $parts);
    }

    function toJson($data, $options = JSON_PRETTY_PRINT) {
        return json_encode($data, $options);
    }

    function array_value_get($array, $field, $default = null) {
        if (array_key_exists($field, $array)) {
            return $array[$field];
        } else {
            return $default;
        }
    }
    function array_value_inc(&$array, $field, $inc = 1) {
        if (array_key_exists($field, $array)) {
            $array[$field] += $inc;
        } else {
            $array[$field] = $inc;
        }
    }

    function getColoredGameStateValue($gameStateValue, $color) {
        return $this->getGameStateValue($gameStateValue . "_" . strtoupper($this->getColorName($color)));
    }

    public function checkVersion(int $clientVersion): void {
        if ($clientVersion != $this->bga->tableOptions->get(300)) {
            throw new UserException(clienttranslate("A new version of this game is now available. Please reload the page (F5)."));
        }
    }

    function arrayGroupBy(array $data, $extractKeyFunction) {
        $dataByKey = [];
        foreach (array_values($data) as $token) {
            $key = $extractKeyFunction($token);
            if (!isset($dataByKey[$key])) {
                $dataByKey[$key] = [];
            }
            $dataByKey[$key][] = $token;
        }
        return $dataByKey;
    }

    function findLongestSubarray($array) {
        return array_reduce($array, function ($longest, $subarray) {
            return count($subarray) > count($longest) ? $subarray : $longest;
        }, []);
    }

    function array_contains_card(array $array, string $cardId) {
        return $this->array_some($array, fn($card) => $card->id == $cardId);
    }
}
