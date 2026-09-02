<?php

namespace Bga\Games\San;

use Bga\GameFramework\Components\Deck;
use Bga\Games\San\Game;
use Bga\Games\San\QueryBuilder;
use Constants;

class DeckManager {
    protected Deck $deck;
    protected Game $game;
    protected string $cast;
    protected array $castParameters;
    protected string $materialType;
    protected string $tableName;

    public function __construct(Game $game, string $tableName, Deck $deck, string $cast, string $materialType, array $castParameters = []) {
        $this->game = $game;
        $this->deck = $deck;
        $this->cast = __NAMESPACE__ . '\\' . $cast; //fully qualified name because of namespaces
        $this->castParameters = $castParameters;
        $this->materialType = $materialType;
        $this->tableName = $tableName;
    }

    public function createCards(array $cards, bool $shuffle = true, string $destination = 'deck') {
        $this->deck->createCards($cards, $destination);
        if ($shuffle)
            $this->deck->shuffle('deck');
    }


    public function shuffleLocationByTypeArg(string $location, int $typeArg): void {
        $cards = $this->getCardsOfTypeArgFromLocation($this->tableName, $typeArg, $location);

        $shuffled = $this->game->getRandomSlice($cards, count($cards));
        foreach ($shuffled as $i => $card) {
            $this->deck->moveCard($card->id, $location, $i);
        }
        $cards = $this->getCardsOfTypeArgFromLocation($this->tableName, $typeArg, $location);
    }

    /**
     * Gets remaining cards count in deck (or in discard if 0 in deck)
     */
    public function getRemainingCardsInDeck(): int {
        $remaining = intval($this->deck->countCardInLocation('deck'));
        if ($remaining == 0) {
            $remaining = intval($this->deck->countCardInLocation('discard'));
        }
        return $remaining;
    }

    public function getCardsFromLocationLike(string $tableName, string $likePattern) {
        $query = new QueryBuilder($this->game, $tableName);
        return $query
            ->select($this->game->getTypicalTableFields())
            ->where('card_location', 'like', "$likePattern%")
            ->get();
    }

    public function countCardsInLocation(string $location, ?int $locationArg = null): int {
        return intval($this->deck->countCardInLocation($location, $locationArg));
    }

    public function countCardsInDiscard(): int {
        return intval($this->deck->countCardInLocation('discard'));
    }

    public function getPlayerHandCount(int $playerId) {
        return $this->deck->countCardInLocation("hand", $playerId);
    }

    public function getDiscardCards() {
        return $this->cast($this->deck->getCardsInLocation("discard"));
    }

    public function getRiverCards() {
        return $this->cast($this->deck->getCardsInLocation("river"));
    }

    public function getTopOfDiscard() {
        return $this->castSingle($this->deck->getCardOnTop("discard"), true);
    }

    public function getTopOfLocation(string $location) {
        return $this->castSingle($this->deck->getCardOnTop($location), true);
    }

    public function getCardsOfType($type, ?int $typeArg = null) {
        return $this->cast($this->deck->getCardsOfType($type, $typeArg));
    }

    public function countCardsOfType(string $tableName, int $type) {
        $sql = "SELECT count(card_id) FROM $tableName where card_type = '$type'";
        return $this->game->getUniqueIntValueFromDB($sql);
    }

    public function countCardsOfTypeInPlayerHand(string $tableName, int $type, int $playerId) {
        $sql = "SELECT count(card_id) FROM $tableName where card_type = '$type' and card_location = 'hand' and card_location_arg = '$playerId'";
        return $this->game->getUniqueIntValueFromDB($sql);
    }

    public function getDeckCards() {
        return $this->cast($this->deck->getCardsInLocation("deck"));
    }

    public function getCardsOfTypeArg(string $tableName, int $typeArg,) {
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM $tableName where card_type_arg = '$typeArg'";
        return $this->cast($this->game->getCollectionFromDb($sql));
    }

    public function getCardsInLocation(string $location, ?int $locationArg = null, ?string $orderBy = null) {
        return $this->cast($this->deck->getCardsInLocation($location, $locationArg, $orderBy));
    }

    public function getCardsOfTypeArgFromLocation(string $tableName, int $typeArg, string $location) {
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM $tableName where card_location = '$location' and card_type_arg = '$typeArg'";
        return $this->cast($this->game->getCollectionFromDb($sql));
    }

    public function getCardsOfTypeArgFromLocationAndLocationArg(string $tableName, int $typeArg, string $location, string $locationArg) {
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM $tableName where card_location = '$location' and card_location_arg = '$locationArg' and card_type_arg = '$typeArg'";
        return $this->cast($this->game->getCollectionFromDb($sql));
    }

    public function getCardOfTypeAndTypeArg(string $tableName, string|int $type, int $typeArg) {
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM $tableName where card_type_arg = '$typeArg' and card_type = '$type'";
        return $this->castSingle($this->game->getObjectFromDB($sql), true);
    }

    public function getCardsOfTypeArgFromLocationOrderBy(string $tableName, int $typeArg, string $location, string $orderBy, bool $desc = false) {
        $direction = $desc ? 'desc' : 'asc';
        $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg FROM $tableName 
        where card_location = '$location' and card_type_arg = $typeArg order by $orderBy $direction";
        return $this->cast($this->game->getCollectionFromDb($sql));
    }

    public function countCardsOfTypeArgFromLocation(string $tableName, int $typeArg, string $location) {
        $sql = "SELECT count(card_id) FROM $tableName where card_location = '$location' and card_type_arg = '$typeArg'";
        return $this->game->getUniqueIntValueFromDB($sql);
    }

    public function getCastedTopOfLocationForTypeArg(string $location, int $typeArg) {
        return  $this->castSingle($this->game->getTopOfLocationForTypeArg($this->tableName, $location, $typeArg), true);
    }

    public function moveCardToLocation($card, string $location, int $locationArg, bool $notify = true, $playerId = null, string $msg = "", array $msgArgs = []) {
        $this->deck->moveCard($card->id, $location, $locationArg);

        if ($notify && $playerId) {
            $this->game->notify->all("materialMove",  $msg, array_merge($msgArgs, [
                'playerId' => $playerId,
                'player_name' => $this->game->getPlayerNameById($playerId),
                'type' => $this->materialType,
                'from' => $card->location,
                'fromArg' => $card->location_arg,
                'to' => $location,
                'toArg' => $locationArg,
                'i18n' => ['cardName'],
                'material' => [$this->castSingle($this->deck->getCard($card->id))],
            ]));
        }
    }

    public function insertCardOnExtremePosition($card, string $location, bool $bOnTop, bool $notify = true, $playerId = null, string $msg = "", array $msgArgs = []): void {
        $this->deck->insertCardOnExtremePosition($card->id, $location, $bOnTop);
        if ($notify && $playerId) {
            $this->game->notify->all("materialMove",  $msg, array_merge($msgArgs, [
                'playerId' => $playerId,
                'player_name' => $this->game->getPlayerNameById($playerId),
                'type' => $this->materialType,
                'from' => $card->location,
                'fromArg' => $card->location_arg,
                'to' => $location,
                'i18n' => ['cardName'],
                'material' => [$this->castSingle($this->deck->getCard($card->id))],
            ]));
        }
    }

    public function addCardsToHand(int $qty, int $playerId, bool $notify = false) {
        $cards = $this->deck->pickCardsForLocation($qty, $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_PLAYER_DECK, $playerId), $this->game->getPlayerLocation(Constants::MATERIAL_LOCATION_HAND, $playerId), $playerId, false);
        if ($notify) {
            $this->game->notify->player($playerId, "materialMove",  "", [
                'playerId' => $playerId,
                'type' => $this->materialType,
                'from' => Constants::MATERIAL_LOCATION_DECK,
                'fromArg' =>  $playerId,
                'to' => Constants::MATERIAL_LOCATION_HAND,
                'toArg' => $playerId,
                'material' => $this->cast($cards),
            ]);
        }
    }

    public function swapHands(int $playerFrom, int $playerTo) {
        $fromCards = $this->cast($this->deck->getCardsInLocation("hand", $playerFrom));
        $toCards = $this->cast($this->deck->getCardsInLocation("hand", $playerTo));

        $this->deck->moveCards($this->game->getIds($fromCards), "hand", $playerTo);
        $this->deck->moveCards($this->game->getIds($toCards), "hand", $playerFrom);

        $this->game->notify->all('msg', clienttranslate('${player_name} swaps hands with ${player_name2}'), array(
            'player_name' => $this->game->getPlayerNameById($playerFrom),
            'player_name2' => $this->game->getPlayerNameById($playerTo),
        ));

        foreach ([$playerFrom, $playerTo] as $player) {
            $this->game->notify->player($player, "materialMove", '', [
                'type' => $this->materialType,
                'from' => Constants::MATERIAL_LOCATION_HAND,
                'fromArg' => $playerTo,
                'to' => Constants::MATERIAL_LOCATION_HAND,
                'toArg' => $playerFrom,
                'material' => $this->cast($this->deck->getPlayerHand($playerFrom)),
            ]);

            $this->game->notify->player($player, "materialMove", '', [
                'type' => $this->materialType,
                'from' => Constants::MATERIAL_LOCATION_HAND,
                'fromArg' => $playerFrom,
                'to' => Constants::MATERIAL_LOCATION_HAND,
                'toArg' => $playerTo,
                'material' => $this->cast($this->deck->getPlayerHand($playerTo)),
            ]);
        }
        $this->game->notifyCounterChange();
    }

    public function stealCard(int $thiefId, int $victimId) {
        $hand = $this->getPlayerHand($victimId);
        if ($hand) {
            $card = $this->game->getRandomValue($hand);
            $this->deck->moveCard($card->id, "hand", $thiefId);

            $this->game->notify->all('msg', clienttranslate('${player_name} steals a card from ${player_name2}'), array(
                'player_name' => $this->game->getPlayerNameById($thiefId),
                'player_name2' => $this->game->getPlayerNameById($victimId),
            ));

            $this->game->notify->player($victimId, "materialMove", '', [
                'type' => $this->materialType,
                'from' => Constants::MATERIAL_LOCATION_HAND,
                'to' => Constants::MATERIAL_LOCATION_DISCARD,
                'material' => [$card],
            ]);
            $this->game->notify->player($thiefId, "materialMove", '', [
                'type' => $this->materialType,
                'from' => Constants::MATERIAL_LOCATION_HAND,
                'fromArg' => $victimId,
                'to' => Constants::MATERIAL_LOCATION_HAND,
                'toArg' => $thiefId,
                'material' => [$card],
            ]);

            $this->game->notifyCounterChange();
        } else {
            $this->game->notify->all('msg', clienttranslate('${player_name2} has no card to be stolen'), array(
                'player_name2' => $this->game->getPlayerNameById($victimId),
            ));
        }
    }

    public function getPlayerHand(int $playerId) {
        return $this->cast($this->deck->getPlayerHand($playerId));
    }

    protected function cast(array $cards, bool $optional = false) {
        return array_values(
            array_map(function ($c) use ($optional) {
                return $this->castSingle($c, $optional);
            }, $cards)
        );
    }

    protected function castSingle($c, bool $optional = false) {
        if (!$optional && (!$c || !array_key_exists('id', $c))) {
            throw new \BgaSystemException("$this->cast doesn't exists " . json_encode($c));
        }
        return $c ? $this->newInstance($c) : null;
    }

    private function newInstance($c) {
        return $this->castParameters ? new $this->cast($c, $this->castParameters) : new $this->cast($c);
    }

    public function discardCard(int $playerId, int $cardId, $msg = "", $msgParameters) {
        $this->deck->playCard($cardId);
        $this->game->notify->all("materialMove",  $msg ? $msg : clienttranslate('${player_name} discards a card'), [
            'player_name' => $this->game->getPlayerNameById($playerId),
            'type' => $this->materialType,
            'from' => Constants::MATERIAL_LOCATION_HAND,
            'to' => Constants::MATERIAL_LOCATION_DISCARD,
            'toArg' => $playerId,
            'material' => $this->cast([($this->deck->getCard($cardId))]),
            'i18n' => ['cardName'],
            ...$msgParameters,
        ]);
    }

    public function discardCards(array $cards) {
        $ids = $this->game->getIds($cards);
        $this->deck->moveCards($ids, "discard");
        $this->game->notify->all("materialMove",  "", [
            'player_name' => $this->game->getPlayerNameById($this->game->getMostlyActivePlayerId()),
            'type' => $this->materialType,
            'from' => Constants::MATERIAL_LOCATION_HAND,
            'to' => Constants::MATERIAL_LOCATION_DISCARD,
            'material' => $this->cast($this->deck->getCards($ids)),
        ]);
    }

    public function getPlayersWithMoreCardsThan(int $playerId) {
        $cardsCount = $this->deck->countCardInLocation("hand", $playerId);
        $players = $this->game->getPlayers();
        $playersWithMore = [];
        foreach ($players as $otherPlayerId => $player) {
            if ($otherPlayerId != $playerId && $this->deck->countCardInLocation("hand", $otherPlayerId) > $cardsCount) {
                $playersWithMore[] = $otherPlayerId;
            }
        }
        return $playersWithMore;
    }

    public function getPlayersWithLessOrSameAmountOfCardsThan(int $playerId) {
        $cardsCount = $this->deck->countCardInLocation("hand", $playerId);
        $players = $this->game->getPlayers();
        $playersWithMore = [];
        foreach ($players as $otherPlayerId => $player) {
            if ($otherPlayerId != $playerId && $this->deck->countCardInLocation("hand", $otherPlayerId) <= $cardsCount) {
                $playersWithMore[] = $otherPlayerId;
            }
        }
        return $playersWithMore;
    }

    public function getPlayersWithCards() {
        $allPlayers = $this->game->getPlayers();
        $players = [];
        foreach ($allPlayers as $otherPlayerId => $player) {
            if ($this->deck->countCardInLocation("hand", $otherPlayerId) > 0) {
                $players[] = $otherPlayerId;
            }
        }
        return $players;
    }

    public function getPlayersWithCardsOfTypeArg(int $typeArg) {
        $allPlayers = $this->game->getPlayers();
        $players = [];
        foreach ($allPlayers as $otherPlayerId => $player) {
            if ($this->getCardsOfTypeArgFromLocation($this->tableName, $typeArg, "hand") > 0) {
                $players[] = $otherPlayerId;
            }
        }
        return $players;
    }

    public function swapCardsBetweenPlayerHands(int $card1Id, int $card2Id) {
        $card1 = $this->castSingle($this->deck->getCard($card1Id));
        $card2 = $this->castSingle($this->deck->getCard($card2Id));
        $this->deck->moveCard($card1->id, $card2->location, $card2->location_arg);
        $this->deck->moveCard($card2->id, $card1->location, $card1->location_arg);

        $this->game->notify->all("materialMove", '', [
            'type' => $this->materialType,
            'from' => Constants::MATERIAL_LOCATION_HAND,
            'to' => Constants::MATERIAL_LOCATION_RIVER,
            'material' => [$this->castSingle($this->deck->getCard($card1Id))],
        ]);

        $this->game->notify->all("materialMove", '', [
            'type' => $this->materialType,
            'from' => Constants::MATERIAL_LOCATION_RIVER,
            'to' =>  Constants::MATERIAL_LOCATION_HAND,
            'toArg' => $card1->location_arg,
            'material' => [$this->castSingle($this->deck->getCard($card2Id))],
        ]);
    }

    public function pickAndDiscard($notify = true) {
        $newCard = $this->castSingle($this->deck->pickCardForLocation('deck', 'discard'));

        if ($notify) {
            $this->game->notify->all('materialMove', "", [
                'type' => Constants::MATERIAL_TYPE_ACTION_CARD,
                'from' => Constants::MATERIAL_LOCATION_DECK,
                'to' => Constants::MATERIAL_LOCATION_DISCARD,
                'material' => [$newCard],
            ]);
        }
        return $newCard;
    }

    public function pickForLocation(string $toLocation, $notify = true) {
        $newCard = $this->castSingle($this->deck->pickCardForLocation('deck', $toLocation));

        if ($notify) {
            $this->game->notify->all('materialMove', "", [
                'type' => $this->materialType,
                'from' => Constants::MATERIAL_LOCATION_DECK,
                'to' => Constants::MATERIAL_LOCATION_DISCARD,
                'material' => [$newCard],
            ]);
        }
        return $newCard;
    }

    public function pickCardsToPlayerHand(int $qty, int $playerId, $notify = true) {
        $newCards = $this->cast($this->deck->pickCards($qty, 'deck', $playerId));

        if ($notify) {
            $this->game->notify->player($playerId, 'materialMove', "", [
                'type' => $this->materialType,
                'from' => Constants::MATERIAL_LOCATION_DECK,
                'to' => Constants::MATERIAL_LOCATION_HAND,
                'toArgs' => $playerId,
                'material' => $newCards,
            ]);
        }
        return $newCards;
    }

    public function pickTypeForLocation(int $type, string $location, int $locationArg, int $quantity = 999, bool $notify = true) {
        $query = new QueryBuilder($this->game, $this->tableName);
        $cards = $this->cast($query
            ->select($this->game->getTypicalTableFields())
            ->where('card_type', '=', "$type")
            ->limit($quantity)
            ->get());

        $ids = $this->game->getIds($cards);
        $this->deck->moveCards($ids, $location, $locationArg);
        $refreshed = $this->getCards($ids);

        if ($notify) {
            $this->game->notify->all('materialMove', "", [
                'type' => $this->materialType,
                'from' => Constants::MATERIAL_LOCATION_DECK,
                'to' => $location,
                'toArg' => $locationArg,
                'material' => $refreshed,
            ]);
        }
        return $refreshed;
    }

    public function moveCardToPlayerHand(int $cardId, int $playerId, bool $faceDown = false, string $notifMsg) {
        $card = $this->castSingle($this->deck->getCard($cardId));
        $from = $card->location;
        $this->deck->moveCard($cardId, "hand", $playerId);
        $card = $this->castSingle($this->deck->getCard($cardId));

        if ($faceDown) {
            $this->game->notify->player($playerId, 'materialMove', "", [
                'type' => $this->materialType,
                'from' => $from,
                'to' => Constants::MATERIAL_LOCATION_HAND,
                'toArg' => $playerId,
                'material' => [$card],
            ]);
            $this->game->notify->all('msg', $notifMsg ?? clienttranslate('${player_name} takes a card'), [
                'player_name' => $this->game->getPlayerNameById($playerId),
            ]);
        } else {
            $this->game->notify->all('materialMove',  $notifMsg ?? clienttranslate('${player_name} takes a card'), [ //${cardType}
                'player_name' => $this->game->getPlayerNameById($playerId),
                'type' => $this->materialType,
                'from' => $from,
                'to' => Constants::MATERIAL_LOCATION_HAND,
                'toArg' => $playerId,
                'cardType' => $card->type,
                'material' => [$card],
            ]);
        }
    }

    function moveAllCardsInLocation(?string $from_location, ?string $to_location, ?int $from_location_arg = null, int $to_location_arg = 0): void {
        $this->deck->moveAllCardsInLocation($from_location, $to_location, $from_location_arg, $to_location_arg);
    }

    public function getFirstCardInLocation(string $location) {
        $cards = $this->deck->getCardsInLocation($location);
        return $cards[array_key_first($cards)] ?? null;
    }

    public function getFirstEmptySlotInLocation(int $locationMaxSize, $location = "river") {
        $i = 0;
        $full = true;
        while ($i < $locationMaxSize && $full) {
            $full = intval($this->deck->countCardInLocation($location, $i)) > 0;
            if ($full) $i++;
        }
        return $i;
    }


    public function replaceRiver() {
        $oldCards = $this->getRiverCards();
        $this->deck->moveCards($this->game->getIds($oldCards), "discard");
        $this->game->notify->all('materialMove', "", [
            'type' => $this->materialType,
            'from' => Constants::MATERIAL_LOCATION_RIVER,
            'to' => Constants::MATERIAL_LOCATION_DISCARD,
            'material' => $oldCards,
        ]);

        $this->initRiver(count($oldCards));
        $this->game->notify->all('materialMove', "", [
            'type' => $this->materialType,
            'from' => Constants::MATERIAL_LOCATION_DECK,
            'to' => Constants::MATERIAL_LOCATION_RIVER,
            'material' => $this->getRiverCards(),
        ]);
    }

    /**
     * Fills river with cards.
     */
    public function initRiver(int $cardsCount) {
        $riverCards = array_values($this->deck->getCardsOnTop($cardsCount, "deck"));
        foreach ($riverCards as $i => $card) {
            $this->deck->moveCard($card["id"], "river", $i + 1);
        }
    }

    public function getCard(int $cardId, bool $optional = false) {
        return $this->castSingle($this->deck->getCard($cardId), $optional);
    }

    public function getCards(array $cardIds) {
        return $this->cast($this->deck->getCards($cardIds), false);
    }

    public function getCardOnLocation(string $location, bool $optional = false) {
        return $this->castSingle($this->getFirstCardInLocation($location), $optional);
    }

    public function shiftLocationArgsForCards(array $cards, int $shiftAmount) {
        $ids = $this->game->dbArrayParam($this->game->getIds($cards));
        $this->game->DbQuery("UPDATE $this->tableName SET card_location_arg = card_location_arg + $shiftAmount WHERE 'card_id' IN ($ids)");
    }

    public function getCardsFromAllHandsButPlayer(int $playerId) {
        $query = new QueryBuilder($this->game, $this->tableName);
        return $this->cast($query
            ->select($this->game->getTypicalTableFields())
            ->where('card_location', 'hand')
            ->where('card_location_arg', '<>', $playerId)
            ->orderBy('card_location_arg')
            ->orderBy('card_id')
            ->get());
    }

    public function getAllCards() {
        $query = new QueryBuilder($this->game, $this->tableName);
        return $this->cast($query
            ->select($this->game->getTypicalTableFields())
            ->orderBy('card_id')
            ->get());
    }
}
